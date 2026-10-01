<?php

namespace App\Services\Distribution\MaxFlow;

use App\Services\Distribution\MemberRole;
use App\Models\Rotation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Everything the distribution needs about a rotation, loaded up front in a
 * handful of queries.
 *
 * Wherever the legacy algorithm depended on the order rows came back in
 * (member pools, course order, room order), the same queries are used so
 * ties are broken exactly as before.
 */
final class RotationData
{
    private const DOCTOR = 'دكتور';

    /**
     * @param array<string, int[]> $pools member ids per role name, in legacy order
     * @param array<int, int> $quotas number_of_observation per member id
     * @param array<int, int[]> $excludedCourses objected and (for doctors) taught course ids per member id, duplicates kept
     * @param int[] $courses course ids ordered by exam date then time
     * @param array<int, array{date: string, time: string, duration: string}> $sittings per course id
     * @param array<int, int[]> $courseRooms room ids per course id
     * @param int[] $rooms distinct room ids used by the rotation, ascending
     */
    private function __construct(
        public readonly array $pools,
        public readonly array $quotas,
        public readonly array $excludedCourses,
        public readonly array $courses,
        public readonly array $sittings,
        public readonly array $courseRooms,
        public readonly array $rooms,
    ) {
    }

    public static function load(Rotation $rotation): self
    {
        $pools = self::loadPools($rotation);
        $memberIds = array_values(array_unique(array_merge(...array_values($pools))));
        $members = User::whereIn('id', $memberIds)->toBase()->get(['id', 'role', 'number_of_observation']);

        $excluded = array_fill_keys($memberIds, []);
        $objections = DB::table('course_rotation_user')
            ->where('rotation_id', $rotation->id)
            ->whereIn('user_id', $memberIds)
            ->get(['user_id', 'course_id']);
        foreach ($objections as $row) {
            $excluded[(int) $row->user_id][] = (int) $row->course_id;
        }
        $doctorIds = $members->where('role', self::DOCTOR)->pluck('id')->all();
        foreach (DB::table('course_teacher')->whereIn('user_id', $doctorIds)->get(['user_id', 'course_id']) as $row) {
            $excluded[(int) $row->user_id][] = (int) $row->course_id;
        }

        $courses = self::ints($rotation->coursesProgram()
            ->orderBy('course_rotation.date', 'asc')
            ->orderBy('course_rotation.time', 'asc')
            ->toBase()->pluck('id')->toArray());

        $sittings = [];
        foreach (DB::table('course_rotation')->where('rotation_id', $rotation->id)->get() as $row) {
            $sittings[(int) $row->course_id] = ['date' => $row->date, 'time' => $row->time, 'duration' => $row->duration];
        }

        $courseRooms = array_fill_keys($courses, []);
        foreach (DB::table('course_room_rotation')->where('rotation_id', $rotation->id)->get(['course_id', 'room_id']) as $row) {
            $courseRooms[(int) $row->course_id][] = (int) $row->room_id;
        }

        $rooms = self::ints(array_merge(array_unique(
            $rotation->distributionRoom()->orderBy('id')->toBase()->pluck('id')->toArray()
        )));

        return new self(
            $pools,
            $members->mapWithKeys(fn ($member) => [(int) $member->id => (int) $member->number_of_observation])->all(),
            $excluded,
            $courses,
            $sittings,
            $courseRooms,
            $rooms,
        );
    }

    /**
     * @return int[]
     */
    public function membersOf(MemberRole $role): array
    {
        return $this->pools[$role->name];
    }

    /**
     * Member pools per role, selected with the original queries.
     *
     * @return array<string, int[]>
     */
    private static function loadPools(Rotation $rotation): array
    {
        $doctors = User::where('role', self::DOCTOR)->toBase()->get()->pluck('id')->toArray();
        $roomHeads = $rotation->initial_members()
            ->wherePivot('options', '{"1":"on"}')
            ->orWherePivot('options', '{"1":"on","2":"on"}')
            ->wherePivot('rotation_id', $rotation->id)
            ->toBase()->get()->pluck('id')->toArray();
        $roomHeads = array_unique(array_merge(array_intersect($doctors, $roomHeads), $roomHeads));

        $secretaries = $rotation->initial_members()
            ->wherePivot('options', '{"2":"on"}')
            ->orWherePivot('options', '{"1":"on","2":"on"}')
            ->wherePivot('rotation_id', $rotation->id)
            ->toBase()->get()->pluck('id')->toArray();

        $observers = array_merge(array_unique(array_merge(
            User::where('is_active', 1)->where('temporary_role')->whereNotIn('id', $roomHeads)->toBase()->get()->pluck('id')->toArray(),
            User::whereIn('id', $secretaries)->whereNotIn('id', $roomHeads)->toBase()->get()->pluck('id')->toArray()
        )));

        return [
            MemberRole::RoomHead->name => self::ints($roomHeads),
            MemberRole::Secertary->name => self::ints($secretaries),
            MemberRole::Observer->name => self::ints($observers),
        ];
    }

    /**
     * @return int[] the ids as a list of ints, in the same order
     */
    private static function ints(array $ids): array
    {
        return array_values(array_map('intval', $ids));
    }
}
