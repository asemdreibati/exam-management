<?php

namespace Tests\Feature\Distribution;

use App\Models\Rotation;
use App\Services\Distribution\DistributionResult;
use App\Services\Distribution\DistributionWriter;
use App\Services\Distribution\LegacyDistributionWriter;
use App\Services\Distribution\MaxFlowMembersDistributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\DistributionScenario;
use Tests\TestCase;

/**
 * The optimized writer must save exactly the rows the legacy writer
 * saves, including members copied into rooms shared by same-time courses.
 */
class WriterParityTest extends TestCase
{
    use RefreshDatabase;

    public function scenarios(): array
    {
        $cases = [];
        foreach (['small' => 25, 'tight' => 25, 'large' => 3] as $size => $count) {
            foreach (range(1, $count) as $seed) {
                $cases["$size #$seed"] = [$seed, $size];
            }
        }

        return $cases;
    }

    /**
     * @dataProvider scenarios
     */
    public function test_optimized_writer_saves_the_same_rows_as_the_legacy_writer(int $seed, string $size): void
    {
        $rotation = DistributionScenario::random($seed, $size)->build();
        $result = (new MaxFlowMembersDistributor())->distribute($rotation);
        $this->assertTrue($result->succeeded());

        $legacyRows = $this->rowsSavedBy(fn () => (new LegacyDistributionWriter())->save($rotation, $result), $rotation);
        $optimizedRows = $this->rowsSavedBy(fn () => (new DistributionWriter())->save($rotation, $result), $rotation);

        $this->assertNotEmpty($legacyRows);
        $this->assertSame($legacyRows, $optimizedRows);
    }

    /**
     * Run a save in a transaction that is rolled back, returning the rows it wrote.
     *
     * @return string[] "user course room role" per row, sorted
     */
    private function rowsSavedBy(callable $save, Rotation $rotation): array
    {
        DB::beginTransaction();
        $save();
        $rows = DB::table('course_room_rotation_user')->where('rotation_id', $rotation->id)->get()
            ->map(fn ($row) => "$row->user_id $row->course_id $row->room_id $row->roleIn")
            ->sort()->values()->all();
        DB::rollBack();

        return $rows;
    }
}
