<?php

namespace Tests\Feature\Distribution;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\DistributionScenario;
use Tests\TestCase;

class DistributeMembersEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_distribution_saves_every_role_assignment(): void
    {
        $rotation = DistributionScenario::random(1)->build();
        $admin = User::factory()->admin()->create(['faculty_id' => $rotation->faculty_id]);

        $this->actingAs($admin)
            ->post(route('rotations.distributeMembersOfFaculty', $rotation))
            ->assertRedirect("/rotations/$rotation->id/show");

        $saved = DB::table('course_room_rotation_user')->where('rotation_id', $rotation->id);
        $this->assertGreaterThan(0, (clone $saved)->where('roleIn', 'RoomHead')->count());
        $this->assertGreaterThan(0, (clone $saved)->where('roleIn', 'Secertary')->count());
        $this->assertGreaterThan(0, (clone $saved)->where('roleIn', 'Observer')->count());
    }

    public function test_non_admin_cannot_run_the_distribution(): void
    {
        $rotation = DistributionScenario::random(1)->build();
        $user = User::factory()->create(['faculty_id' => $rotation->faculty_id]);

        $this->actingAs($user)
            ->post(route('rotations.distributeMembersOfFaculty', $rotation))
            ->assertForbidden();

        $this->assertSame(0, DB::table('course_room_rotation_user')->count());
    }
}
