<?php

namespace Tests\Feature\Distribution;

use App\Http\Controllers\MaxFlow\EnumPersonType;
use App\Http\Controllers\MaxFlow\Graph;
use App\Services\Distribution\DistributionResult;
use App\Services\Distribution\MembersDistributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DistributionScenario;
use Tests\TestCase;

/**
 * Hand-built scenarios for individual distribution rules.
 */
class DistributionRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_is_not_assigned_to_a_course_they_teach(): void
    {
        $scenario = (new DistributionScenario())
            ->course('A', '2024-01-10', '09:00:00', ['R1'])
            ->course('B', '2024-01-11', '09:00:00', ['R2'])
            ->member('doctor', 2, DistributionScenario::ROOM_HEAD, 'دكتور')
            ->teaches('doctor', 'A')
            ->member('S1', 2, DistributionScenario::SECRETARY)
            ->member('O1', 2);

        $result = $this->distribute($scenario);

        $this->assertSame(['B'], $this->coursesOf($scenario, $result->roomHeads, 'doctor'));
    }

    public function test_member_who_objects_to_one_overlapping_course_is_kept_out_of_the_whole_sitting(): void
    {
        // A and B overlap, so they share one same-time node in the graph.
        $scenario = (new DistributionScenario())
            ->course('A', '2024-01-10', '09:00:00', ['R1'], '2:00')
            ->course('B', '2024-01-10', '10:00:00', ['R2'])
            ->member('H1', 2, DistributionScenario::ROOM_HEAD)
            ->objects('H1', 'A')
            ->member('H2', 2, DistributionScenario::ROOM_HEAD)
            ->member('S1', 2, DistributionScenario::SECRETARY)
            ->member('S2', 2, DistributionScenario::SECRETARY)
            ->member('O1', 2)
            ->member('O2', 2);

        $result = $this->distribute($scenario);

        $this->assertSame([], $this->coursesOf($scenario, $result->roomHeads, 'H1'));
        $this->assertCount(1, $this->coursesOf($scenario, $result->roomHeads, 'H2'));
    }

    public function test_exam_overlapping_a_non_adjacent_exam_shares_its_same_time_group(): void
    {
        // Sorted by start: A (09:00-12:00), B (09:00-09:30), C (10:00-11:30).
        // C does not overlap B, the exam before it, but it does overlap A.
        $rotation = (new DistributionScenario())
            ->course('A', '2024-01-10', '09:00:00', ['R1'], '3:00')
            ->course('B', '2024-01-10', '09:00:00', ['R2'], '0:30')
            ->course('C', '2024-01-10', '10:00:00', ['R3'])
            ->course('D', '2024-01-10', '12:00:00', ['R4'])
            ->member('H1', 4, DistributionScenario::ROOM_HEAD)
            ->build();

        [, $groupCount] = (new Graph(EnumPersonType::RoomHead, $rotation))->coursesInSameTimes();

        // {A, B, C} and {D}; D starts exactly when A ends.
        $this->assertSame(2, $groupCount);
    }

    private function distribute(DistributionScenario $scenario): DistributionResult
    {
        return app(MembersDistributor::class)->distribute($scenario->build());
    }

    /**
     * @return string[] names of the courses the member was assigned to in the given role result
     */
    private function coursesOf(DistributionScenario $scenario, array $roleResult, string $member): array
    {
        $observations = $roleResult['users_observations'][$scenario->userId($member)] ?? [];
        $courses = array_map(fn ($observation) => $scenario->courseName($observation['course']), $observations);
        sort($courses);

        return $courses;
    }
}
