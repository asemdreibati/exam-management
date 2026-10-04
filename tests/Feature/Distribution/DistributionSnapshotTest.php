<?php

namespace Tests\Feature\Distribution;

use App\Services\Distribution\DistributionResult;
use App\Services\Distribution\DistributionWriter;
use App\Services\Distribution\MembersDistributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\DistributionScenario;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Locks the distribution to recorded results: for each random scenario,
 * the assignments per role (in order) and the rows saved.
 *
 * The snapshots in tests/fixtures/distribution were recorded from the
 * original max-flow implementation (with the exclusion and overlap fixes),
 * so any change in behaviour shows up here. If a change is intended,
 * re-record with UPDATE_SNAPSHOTS=1 and review the fixture diff.
 */
class DistributionSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private const SCENARIOS = ['small' => 25, 'tight' => 200, 'large' => 1];

    public static function scenarios(): array
    {
        $cases = [];
        foreach (self::SCENARIOS as $size => $count) {
            foreach (range(1, $count) as $seed) {
                $cases["$size #$seed"] = [$size, $seed];
            }
        }

        return $cases;
    }

    #[DataProvider('scenarios')]
    public function test_distribution_matches_recorded_result(string $size, int $seed): void
    {
        $scenario = DistributionScenario::random($seed, $size);
        $rotation = $scenario->build();

        $result = app(MembersDistributor::class)->distribute($rotation);
        if ($result->succeeded()) {
            (new DistributionWriter())->save($rotation, $result);
        }
        $actual = $this->describe($scenario, $result);

        $file = base_path("tests/fixtures/distribution/$size.json");
        $snapshots = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
        if (getenv('UPDATE_SNAPSHOTS')) {
            $snapshots[$seed] = $actual;
            ksort($snapshots);
            file_put_contents($file, json_encode($snapshots, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
        }

        $this->assertArrayHasKey($seed, $snapshots, "No snapshot for $size #$seed; record with UPDATE_SNAPSHOTS=1.");
        $this->assertSame($snapshots[$seed], $actual);
    }

    /**
     * The result in names instead of database ids.
     */
    private function describe(DistributionScenario $scenario, DistributionResult $result): array
    {
        $roles = [];
        foreach (['RoomHead' => $result->roomHeads, 'Secertary' => $result->secretaries, 'Observer' => $result->observers] as $role => $assignments) {
            foreach ($assignments['users_observations'] ?? [] as $member => $observations) {
                foreach ($observations as $observation) {
                    $roles[$role][] = implode(' ', [
                        $scenario->userName($member),
                        $scenario->courseName($observation['course']),
                        $scenario->roomName($observation['room']),
                    ]);
                }
            }
        }

        $saved = DB::table('course_room_rotation_user')->get()
            ->map(fn ($row) => implode(' ', [
                $scenario->userName($row->user_id),
                $scenario->courseName($row->course_id),
                $scenario->roomName($row->room_id),
                $row->roleIn,
            ]))
            ->sort()->values()->all();

        return ['unfilledRole' => $result->unfilledRole?->name, 'assignments' => $roles, 'saved' => $saved];
    }
}
