<?php

namespace Tests\Feature\Distribution;

use App\Models\Rotation;
use App\Services\Distribution\DistributionResult;
use App\Services\Distribution\MembersDistributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\DistributionScenario;
use Tests\TestCase;

/**
 * Rules every distribution must satisfy, whatever engine produces it.
 */
class DistributionInvariantsTest extends TestCase
{
    use RefreshDatabase;

    public function seeds(): array
    {
        return array_map(fn ($seed) => [$seed], range(1, 25));
    }

    /**
     * @dataProvider seeds
     */
    public function test_random_scenario_distribution_respects_all_rules(int $seed): void
    {
        $rotation = DistributionScenario::random($seed)->build();

        $result = app(MembersDistributor::class)->distribute($rotation);

        $this->assertTrue($result->succeeded(), "Seed $seed: no {$result->unfilledRole?->name} could be assigned.");
        $this->assertDistributionIsValid($rotation, $result);
    }

    private function assertDistributionIsValid(Rotation $rotation, DistributionResult $result): void
    {
        $assignments = $this->flatten($result);
        $sittings = $this->sittings($rotation);
        $pools = $this->pools($rotation);
        $objected = $this->objectedCourses($rotation);
        $taught = $this->coursesTaughtByDoctors();
        $quotas = DB::table('users')->pluck('number_of_observation', 'id');

        $perUser = [];
        $seats = [];
        foreach ($assignments as [$role, $userId, $courseId, $roomId]) {
            $this->assertContains($userId, $pools[$role], "User $userId is not eligible as $role.");
            $this->assertNotContains($courseId, $objected[$userId] ?? [], "User $userId objected to course $courseId.");
            $this->assertNotContains($courseId, $taught[$userId] ?? [], "Doctor $userId teaches course $courseId.");

            $seat = "$courseId-$roomId-$role";
            $this->assertArrayNotHasKey($seat, $seats, "Seat $seat was filled twice.");
            $seats[$seat] = true;

            $perUser[$userId][] = $courseId;
        }

        foreach ($perUser as $userId => $courseIds) {
            $this->assertLessThanOrEqual($quotas[$userId], count($courseIds), "User $userId exceeds their quota.");
            foreach ($courseIds as $i => $a) {
                foreach (array_slice($courseIds, $i + 1) as $b) {
                    $this->assertFalse($this->overlap($sittings[$a], $sittings[$b]), "User $userId is in courses $a and $b at the same time.");
                }
            }
        }
    }

    /**
     * @return array<int, array{string, int, int, int}> [role, user, course, room]
     */
    private function flatten(DistributionResult $result): array
    {
        $rows = [];
        foreach (['RoomHead' => $result->roomHeads, 'Secertary' => $result->secretaries, 'Observer' => $result->observers] as $role => $info) {
            foreach ($info['users_observations'] ?? [] as $userId => $observations) {
                foreach ($observations as $observation) {
                    $rows[] = [$role, $userId, $observation['course'], $observation['room']];
                }
            }
        }

        return $rows;
    }

    /**
     * @return array<int, array{int, int}> course id => [start, end] in minutes since the epoch day
     */
    private function sittings(Rotation $rotation): array
    {
        $sittings = [];
        foreach (DB::table('course_rotation')->where('rotation_id', $rotation->id)->get() as $row) {
            [$hours, $minutes] = array_map('intval', explode(':', $row->duration));
            $start = intdiv(strtotime("$row->date $row->time UTC"), 60);
            $sittings[$row->course_id] = [$start, $start + $hours * 60 + $minutes];
        }

        return $sittings;
    }

    private function overlap(array $a, array $b): bool
    {
        return $a[0] < $b[1] && $b[0] < $a[1];
    }

    /**
     * Members eligible for each role, as RotationData selects them.
     */
    private function pools(Rotation $rotation): array
    {
        $options = DB::table('initial_members_for_each_rotation')->where('rotation_id', $rotation->id)->pluck('options', 'user_id');
        $heads = $options->filter(fn ($o) => in_array($o, [DistributionScenario::ROOM_HEAD, DistributionScenario::ROOM_HEAD_AND_SECRETARY], true))->keys()->all();
        $secretaries = $options->filter(fn ($o) => in_array($o, [DistributionScenario::SECRETARY, DistributionScenario::ROOM_HEAD_AND_SECRETARY], true))->keys()->all();
        $observers = DB::table('users')->where('is_active', 1)->whereNull('temporary_role')->pluck('id')
            ->merge($secretaries)->diff($heads)->unique()->values()->all();

        return ['RoomHead' => $heads, 'Secertary' => $secretaries, 'Observer' => $observers];
    }

    private function objectedCourses(Rotation $rotation): array
    {
        $objected = [];
        foreach (DB::table('course_rotation_user')->where('rotation_id', $rotation->id)->get() as $row) {
            $objected[$row->user_id][] = $row->course_id;
        }

        return $objected;
    }

    private function coursesTaughtByDoctors(): array
    {
        $taught = [];
        $rows = DB::table('course_teacher')->join('users', 'users.id', '=', 'course_teacher.user_id')
            ->where('users.role', 'دكتور')->get(['course_teacher.user_id', 'course_teacher.course_id']);
        foreach ($rows as $row) {
            $taught[$row->user_id][] = $row->course_id;
        }

        return $taught;
    }
}
