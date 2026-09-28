<?php

namespace App\Services\Distribution;

use App\Models\Rotation;
use Illuminate\Support\Facades\DB;

/**
 * Saves a distribution: every assignment, plus the members of rooms that
 * courses held around the same time share.
 *
 * Produces the same rows as LegacyDistributionWriter, working in memory
 * and inserting in bulk instead of querying and inserting row by row.
 *
 * Must run inside a transaction so a failure leaves no partial result.
 */
final class DistributionWriter
{
    private const ROLES = ['RoomHead', 'Secertary', 'Observer'];

    private const INSERT_CHUNK = 500;

    /** @var array<int, array<int, array<string, int[]>>> member ids per course, room and role */
    private array $staff = [];

    /** @var array<int, array{user_id: int, course_id: int, rotation_id: int, room_id: int, roleIn: string}> */
    private array $rows = [];

    public function save(Rotation $rotation, DistributionResult $result): void
    {
        $this->staff = [];
        $this->rows = [];

        foreach ([$result->roomHeads, $result->secretaries, $result->observers] as $assignments) {
            foreach ($assignments['users_observations'] ?? [] as $member => $observations) {
                foreach ($observations as $observation) {
                    $this->assign($rotation, $observation['course'], $observation['room'], $observation['roleIn'], $member);
                }
            }
        }
        $this->shareStaffOfCommonRooms($rotation);

        foreach (array_chunk($this->rows, self::INSERT_CHUNK) as $chunk) {
            DB::table('course_room_rotation_user')->insert($chunk);
        }
    }

    /**
     * When courses held around the same time share a room, the members
     * already placed in that room for one of them also staff it for the
     * others that have nobody there yet.
     */
    private function shareStaffOfCommonRooms(Rotation $rotation): void
    {
        // Same query and order as the legacy writer's loop.
        $program = $rotation->coursesProgram()->get();
        $sittings = [];
        foreach ($program as $course) {
            $sittings[$course->id] = $course->pivot;
        }
        $courseRooms = array_fill_keys(array_keys($sittings), []);
        foreach (DB::table('course_room_rotation')->where('rotation_id', $rotation->id)->get(['course_id', 'room_id']) as $row) {
            $courseRooms[(int) $row->course_id][] = (int) $row->room_id;
        }

        $handled = [];
        foreach (array_keys($sittings) as $course) {
            [$sharedRooms, $commonCourses] = $this->coursesAroundTheSameTime($course, $sittings, $courseRooms);
            if (count($commonCourses) === 1) {
                continue;
            }
            foreach ($courseRooms[$course] as $room) {
                if (in_array($room, $handled[$course] ?? []) || !in_array($room, $sharedRooms)) {
                    continue;
                }

                $members = $this->staffOf($course, $room);
                $withoutMembers = [];
                $membersFound = false;
                foreach ($commonCourses as $other) {
                    if (!in_array($room, $courseRooms[$other])) {
                        continue;
                    }
                    // Once one course with members is found, every later course
                    // sharing the room is treated as staffed too (legacy behaviour).
                    if ($membersFound || $this->hasStaff($other, $room)) {
                        $members = $this->staffOf($other, $room);
                        $membersFound = true;
                    } else {
                        $withoutMembers[] = $other;
                    }
                    $handled[$other][] = $room;
                }

                foreach ($withoutMembers as $other) {
                    foreach (self::ROLES as $role) {
                        foreach ($members[$role] as $member) {
                            $this->assign($rotation, $other, $room, $role, $member);
                        }
                    }
                }
            }
        }
    }

    /**
     * Courses whose exam starts within this course's duration before or
     * after its own start on the same date (as
     * Stock::getDisabledAndJoiningRoomsAndCommonCoursesWithTime decides),
     * and the rooms they use. Courses without rooms are left out; the
     * course itself comes last.
     *
     * @return array{0: int[], 1: int[]} [shared rooms, common course ids]
     */
    private function coursesAroundTheSameTime(int $course, array $sittings, array $courseRooms): array
    {
        $date = $sittings[$course]->date;
        $time = $sittings[$course]->time;
        $duration = $sittings[$course]->duration;
        $latest = gmdate('H:i:s', strtotime($time) + strtotime($duration));
        $earliest = gmdate('H:i:s', strtotime($time) - strtotime($duration));

        $rooms = [];
        $common = [];
        foreach ($sittings as $other => $sitting) {
            if ($other === $course || $sitting->date !== $date || !$courseRooms[$other]) {
                continue;
            }
            $startsAfter = $sitting->time >= $time && $sitting->time <= $latest;
            $startsBefore = $sitting->time <= $time && $sitting->time >= $earliest;
            if ($startsAfter || $startsBefore) {
                array_push($rooms, ...$courseRooms[$other]);
                $common[] = $other;
            }
        }
        $common[] = $course;

        return [array_unique($rooms), $common];
    }

    private function assign(Rotation $rotation, int $course, int $room, string $role, int $member): void
    {
        $this->staff[$course][$room][$role][] = $member;
        $this->rows[] = [
            'user_id' => $member,
            'course_id' => $course,
            'rotation_id' => $rotation->id,
            'room_id' => $room,
            'roleIn' => $role,
        ];
    }

    /**
     * @return array<string, int[]> member ids per role
     */
    private function staffOf(int $course, int $room): array
    {
        $staff = $this->staff[$course][$room] ?? [];

        return array_map(fn ($role) => $staff[$role] ?? [], array_combine(self::ROLES, self::ROLES));
    }

    private function hasStaff(int $course, int $room): bool
    {
        return !empty($this->staff[$course][$room]);
    }
}
