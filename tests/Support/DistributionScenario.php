<?php

namespace Tests\Support;

use App\Models\Rotation;
use Illuminate\Support\Facades\DB;

/**
 * Builds a rotation with its exam program, rooms, members, objections and
 * teaching assignments directly in the database, for distribution tests.
 * Everything is referenced by short names; ids are resolved on build.
 */
final class DistributionScenario
{
    public const ROOM_HEAD = '{"1":"on"}';
    public const SECRETARY = '{"2":"on"}';
    public const ROOM_HEAD_AND_SECRETARY = '{"1":"on","2":"on"}';

    private array $courses = [];
    private array $rooms = [];
    private array $members = [];
    private array $objections = [];
    private array $teaching = [];

    private array $courseIds = [];
    private array $roomIds = [];
    private array $userIds = [];

    /**
     * @param string[] $rooms names of the rooms the course's students sit in
     */
    public function course(string $name, string $date, string $time, array $rooms, string $duration = '1:30'): self
    {
        $this->courses[$name] = compact('date', 'time', 'duration', 'rooms');
        foreach ($rooms as $room) {
            $this->rooms[$room] = true;
        }

        return $this;
    }

    /**
     * @param string|null $options one of the ROOM_HEAD / SECRETARY constants, or null for a plain observer
     */
    public function member(string $name, int $quota, ?string $options = null, string $role = 'موظف'): self
    {
        $this->members[$name] = compact('quota', 'options', 'role');

        return $this;
    }

    public function objects(string $member, string $course): self
    {
        $this->objections[] = [$member, $course];

        return $this;
    }

    public function teaches(string $member, string $course): self
    {
        $this->teaching[] = [$member, $course];

        return $this;
    }

    public function build(): Rotation
    {
        $now = now();
        $facultyId = DB::table('faculties')->insertGetId(['name' => 'Faculty']);
        $rotationId = DB::table('rotations')->insertGetId([
            'name' => 'Rotation', 'year' => 2024, 'start_date' => '2024-01-01',
            'end_date' => '2024-02-01', 'faculty_id' => $facultyId,
        ]);

        foreach (array_keys($this->rooms) as $room) {
            $this->roomIds[$room] = DB::table('rooms')->insertGetId([
                'room_name' => $room, 'capacity' => 50, 'faculty_id' => $facultyId,
            ]);
        }

        foreach ($this->courses as $name => $course) {
            $this->courseIds[$name] = $courseId = DB::table('courses')->insertGetId([
                'course_name' => $name, 'semester' => 1, 'studing_year' => 1, 'faculty_id' => $facultyId,
            ]);
            DB::table('course_rotation')->insert([
                'course_id' => $courseId, 'rotation_id' => $rotationId, 'date' => $course['date'],
                'time' => $course['time'], 'duration' => $course['duration'], 'students_number' => 30,
            ]);
            foreach ($course['rooms'] as $room) {
                DB::table('course_room_rotation')->insert([
                    'course_id' => $courseId, 'room_id' => $this->roomIds[$room],
                    'rotation_id' => $rotationId, 'num_student_in_room' => 30,
                ]);
            }
        }

        foreach ($this->members as $name => $member) {
            $this->userIds[$name] = $userId = DB::table('users')->insertGetId([
                'email' => "$name@example.com", 'username' => $name, 'password' => 'x',
                'number_of_observation' => $member['quota'], 'role' => $member['role'], 'faculty_id' => $facultyId,
            ]);
            if ($member['options'] !== null) {
                DB::table('initial_members_for_each_rotation')->insert([
                    'user_id' => $userId, 'rotation_id' => $rotationId, 'options' => $member['options'],
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        foreach ($this->objections as [$member, $course]) {
            DB::table('course_rotation_user')->insert([
                'user_id' => $this->userIds[$member], 'course_id' => $this->courseIds[$course],
                'rotation_id' => $rotationId, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        foreach ($this->teaching as [$member, $course]) {
            DB::table('course_teacher')->insert([
                'user_id' => $this->userIds[$member], 'course_id' => $this->courseIds[$course], 'section_type' => 'نظري',
            ]);
        }

        return Rotation::findOrFail($rotationId);
    }

    public function courseId(string $name): int
    {
        return $this->courseIds[$name];
    }

    public function userId(string $name): int
    {
        return $this->userIds[$name];
    }

    public function courseName(int $id): string
    {
        return array_search($id, $this->courseIds, true);
    }

    public function userName(int $id): string
    {
        return array_search($id, $this->userIds, true);
    }

    /**
     * A reproducible, realistically shaped random scenario: several exam
     * days with overlapping sittings, rooms shared by same-time courses,
     * mixed member pools, objections and teaching doctors.
     */
    public static function random(int $seed): self
    {
        mt_srand($seed);
        $scenario = new self();

        $sittings = [['09:00:00', '1:30'], ['09:00:00', '2:00'], ['10:00:00', '1:30'], ['12:00:00', '2:00'], ['13:00:00', '1:30']];
        $roomNames = array_map(fn ($i) => "R$i", range(1, mt_rand(5, 8)));
        $days = mt_rand(3, 5);
        $courseCount = mt_rand(7, 12);
        $roomsBySitting = [];

        for ($c = 1; $c <= $courseCount; $c++) {
            $date = sprintf('2024-01-%02d', mt_rand(1, $days) + 9);
            [$time, $duration] = $sittings[mt_rand(0, count($sittings) - 1)];
            $sitting = "$date $time";
            $rooms = [];
            for ($r = mt_rand(1, 3); $r > 0; $r--) {
                // Mostly fresh rooms; sometimes share one with a course in the same sitting.
                $shared = $roomsBySitting[$sitting] ?? [];
                $rooms[] = ($shared && mt_rand(1, 5) === 1)
                    ? $shared[mt_rand(0, count($shared) - 1)]
                    : $roomNames[mt_rand(0, count($roomNames) - 1)];
            }
            $rooms = array_values(array_unique($rooms));
            $roomsBySitting[$sitting] = array_values(array_unique(array_merge($roomsBySitting[$sitting] ?? [], $rooms)));
            $scenario->course("C$c", $date, $time, $rooms, $duration);
        }

        $pools = [
            ['H', self::ROOM_HEAD, mt_rand(5, 8)],
            ['S', self::SECRETARY, mt_rand(5, 8)],
            ['B', self::ROOM_HEAD_AND_SECRETARY, mt_rand(1, 3)],
            ['O', null, mt_rand(8, 14)],
        ];
        foreach ($pools as [$prefix, $options, $count]) {
            for ($m = 1; $m <= $count; $m++) {
                $name = "$prefix$m";
                $isDoctor = $prefix === 'H' && mt_rand(0, 1) === 1;
                $scenario->member($name, mt_rand(2, 6), $options, $isDoctor ? 'دكتور' : 'موظف');
                for ($o = mt_rand(0, 2); $o > 0; $o--) {
                    $scenario->objects($name, 'C' . mt_rand(1, $courseCount));
                }
                if ($isDoctor) {
                    $scenario->teaches($name, 'C' . mt_rand(1, $courseCount));
                }
            }
        }
        // Objections cover every course sitting at the same date and time,
        // as CourseRotationUser_ObjectionController::store records them.
        $expanded = [];
        foreach ($scenario->objections as [$member, $course]) {
            $at = $scenario->courses[$course]['date'] . ' ' . $scenario->courses[$course]['time'];
            foreach ($scenario->courses as $other => $details) {
                if ($details['date'] . ' ' . $details['time'] === $at) {
                    $expanded[] = [$member, $other];
                }
            }
        }
        $scenario->objections = array_values(array_unique($expanded, SORT_REGULAR));
        $scenario->teaching = array_values(array_unique($scenario->teaching, SORT_REGULAR));

        return $scenario;
    }
}
