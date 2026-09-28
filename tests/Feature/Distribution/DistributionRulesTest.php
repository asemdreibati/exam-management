<?php

namespace Tests\Feature\Distribution;

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
