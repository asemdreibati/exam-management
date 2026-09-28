<?php

namespace Tests\Feature\Distribution;

use App\Services\Distribution\LegacyMaxFlowMembersDistributor;
use App\Services\Distribution\MaxFlowMembersDistributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DistributionScenario;
use Tests\TestCase;

/**
 * The optimized engine must reproduce the legacy engine's assignments
 * exactly: same members, courses, rooms and order.
 */
class EngineParityTest extends TestCase
{
    use RefreshDatabase;

    public function scenarios(): array
    {
        $cases = [];
        foreach (range(1, 25) as $seed) {
            $cases["small #$seed"] = [$seed, 'small'];
        }
        // Few members with tight quotas: exercises rerouted paths and the
        // first-room rule, which the other sizes rarely reach.
        foreach (range(1, 200) as $seed) {
            $cases["tight #$seed"] = [$seed, 'tight'];
        }
        foreach (range(1, 3) as $seed) {
            $cases["large #$seed"] = [$seed, 'large'];
        }

        return $cases;
    }

    /**
     * @dataProvider scenarios
     */
    public function test_optimized_engine_matches_legacy_engine(int $seed, string $size): void
    {
        $rotation = DistributionScenario::random($seed, $size)->build();

        $legacy = (new LegacyMaxFlowMembersDistributor())->distribute($rotation);
        $optimized = (new MaxFlowMembersDistributor())->distribute($rotation);

        $this->assertSame($legacy->unfilledRole, $optimized->unfilledRole);
        $this->assertSame($legacy->roomHeads, $optimized->roomHeads);
        $this->assertSame($legacy->secretaries, $optimized->secretaries);
        $this->assertSame($legacy->observers, $optimized->observers);
    }
}
