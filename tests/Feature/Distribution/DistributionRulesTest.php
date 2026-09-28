<?php

namespace Tests\Feature\Distribution;

use App\Services\Distribution\DistributionResult;
use App\Services\Distribution\LegacyMaxFlowMembersDistributor;
use App\Services\Distribution\MaxFlowMembersDistributor;
use App\Services\Distribution\MembersDistributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DistributionScenario;
use Tests\TestCase;

/**
 * Hand-built scenarios for individual distribution rules, run against
 * every engine.
 */
class DistributionRulesTest extends TestCase
{
    use RefreshDatabase;

    public function engines(): array
    {
        return [
            'legacy' => [LegacyMaxFlowMembersDistributor::class],
            'optimized' => [MaxFlowMembersDistributor::class],
        ];
    }

    /**
     * @dataProvider engines
     */
    public function test_doctor_is_not_assigned_to_a_course_they_teach(string $engine): void
    {
        $scenario = (new DistributionScenario())
            ->course('A', '2024-01-10', '09:00:00', ['R1'])
            ->course('B', '2024-01-11', '09:00:00', ['R2'])
            ->member('doctor', 2, DistributionScenario::ROOM_HEAD, 'دكتور')
            ->teaches('doctor', 'A')
            ->member('S1', 2, DistributionScenario::SECRETARY)
            ->member('O1', 2);

        $result = $this->distribute($engine, $scenario);

        $this->assertSame(['B'], $this->coursesOf($scenario, $result->roomHeads, 'doctor'));
    }

    /**
     * @dataProvider engines
     */
    public function test_member_who_objects_to_one_overlapping_course_is_kept_out_of_the_whole_sitting(string $engine): void
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

        $result = $this->distribute($engine, $scenario);

        $this->assertSame([], $this->coursesOf($scenario, $result->roomHeads, 'H1'));
        $this->assertCount(1, $this->coursesOf($scenario, $result->roomHeads, 'H2'));
    }

    /**
     * @dataProvider engines
     */
    public function test_member_is_not_assigned_to_exams_overlapping_through_a_non_adjacent_exam(string $engine): void
    {
        // Sorted by start: A (09:00-12:00), B (09:00-09:30), C (10:00-11:30).
        // C does not overlap B, the exam before it, but it does overlap A.
        $scenario = (new DistributionScenario())
            ->course('A', '2024-01-10', '09:00:00', ['R1'], '3:00')
            ->course('B', '2024-01-10', '09:00:00', ['R2'], '0:30')
            ->course('C', '2024-01-10', '10:00:00', ['R3'])
            ->member('H1', 3, DistributionScenario::ROOM_HEAD)
            ->member('S1', 3, DistributionScenario::SECRETARY)
            ->member('O1', 3);

        $result = $this->distribute($engine, $scenario);

        $this->assertCount(1, $this->coursesOf($scenario, $result->roomHeads, 'H1'));
    }

    private function distribute(string $engine, DistributionScenario $scenario): DistributionResult
    {
        /** @var MembersDistributor $distributor */
        $distributor = new $engine();

        return $distributor->distribute($scenario->build());
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
