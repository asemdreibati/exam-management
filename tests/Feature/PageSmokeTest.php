<?php

namespace Tests\Feature;

use App\Models\Rotation;
use App\Models\User;
use App\Services\Distribution\DistributionWriter;
use App\Services\Distribution\MembersDistributor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\DistributionScenario;
use Tests\TestCase;

/**
 * Every page renders for an admin on a rotation with a saved distribution,
 * and logging in works. Guards against framework and package upgrades
 * breaking views, routes or authentication.
 */
class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    private Rotation $rotation;
    private User $admin;
    private int $memberId;
    private int $courseId;
    private int $roomId;

    protected function setUp(): void
    {
        parent::setUp();

        $scenario = DistributionScenario::random(1);
        $this->rotation = $scenario->build();
        $result = app(MembersDistributor::class)->distribute($this->rotation);
        (new DistributionWriter())->save($this->rotation, $result);

        $this->admin = User::factory()->admin()->create(['faculty_id' => $this->rotation->faculty_id]);
        $this->memberId = $scenario->userId('H1');
        $this->courseId = $scenario->courseId('C1');
        $this->roomId = DB::table('course_room_rotation')->where('course_id', $this->courseId)->value('room_id');
    }

    public function pages(): array
    {
        return [
            'home' => ['home.index', []],
            'users' => ['users.index', []],
            'users search' => ['users.search', ['se' => 'H']],
            'create user' => ['users.create', []],
            'show user' => ['users.show', ['user' => 'member']],
            'edit user' => ['users.edit', ['user' => 'member']],
            'user profile' => ['users.profile', ['user' => 'member']],
            'user observations' => ['users.observations', ['user' => 'member']],
            'add teaching courses' => ['users.create_user_courses', ['user' => 'member']],
            'edit teaching courses' => ['users.edit_user_courses', ['user' => 'member']],
            'courses' => ['courses.index', []],
            'create course' => ['courses.create', []],
            'edit course' => ['courses.edit', ['course' => 'course']],
            'rooms' => ['rooms.index', []],
            'create room' => ['rooms.create', []],
            'edit room' => ['rooms.edit', ['room' => 'room']],
            'rotations' => ['rotations.index', []],
            'create rotation' => ['rotations.create', []],
            'edit rotation' => ['rotations.edit', ['rotation' => 'rotation']],
            'exam program' => ['rotations.program.show', ['rotation' => 'rotation']],
            'add course to program' => ['rotations.program.add_course_to_the_program', ['rotation' => 'rotation']],
            'program course' => ['rotations.course.show', ['rotation' => 'rotation', 'course' => 'course']],
            'edit program course' => ['rotations.course.edit', ['rotation' => 'rotation', 'course' => 'course']],
            'course room' => ['rotations.get_room_for_course', ['rotation' => 'rotation', 'course' => 'course', 'specific_room' => 'room']],
            'initial members' => ['rotations.create_initial_members', ['rotation' => 'rotation']],
            'edit initial members' => ['rotations.edit_initial_members', ['rotation' => 'rotation']],
            'create objections' => ['rotations.objections.create', ['rotation' => 'rotation', 'user' => 'member']],
            'edit objections' => ['rotations.objections.edit', ['rotation' => 'rotation', 'user' => 'member']],
            'user objections' => ['objections.user.index', ['user' => 'member']],
            'user objections in rotation' => ['objections.user.show', ['rotation' => 'rotation', 'user' => 'member']],
            'user observations in rotation' => ['rotations.observations.user.show', ['rotation' => 'rotation', 'user' => 'member']],
        ];
    }

    /**
     * @dataProvider pages
     */
    public function test_page_renders_for_admin(string $route, array $parameters): void
    {
        $this->actingAs($this->admin)
            ->get(route($route, $this->resolve($parameters)))
            ->assertOk();
    }

    public function test_observations_export_downloads_a_spreadsheet(): void
    {
        $this->actingAs($this->admin)
            ->get(route('observations.export', $this->rotation))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_login_page_renders_for_guests(): void
    {
        $this->get(route('login.show'))->assertOk();
    }

    public function test_member_can_log_in_with_username_or_email(): void
    {
        $user = User::factory()->create(['faculty_id' => $this->rotation->faculty_id, 'password' => 'secret-pass']);

        $this->post(route('login.perform'), ['username' => $user->username, 'password' => 'secret-pass'])->assertRedirect();
        $this->assertAuthenticatedAs($user);

        auth()->logout();
        $this->post(route('login.perform'), ['username' => $user->email, 'password' => 'secret-pass'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create(['faculty_id' => $this->rotation->faculty_id, 'password' => 'secret-pass']);

        $this->post(route('login.perform'), ['username' => $user->username, 'password' => 'wrong'])->assertRedirect('login');
        $this->assertGuest();
    }

    private function resolve(array $parameters): array
    {
        $values = [
            'member' => $this->memberId,
            'course' => $this->courseId,
            'room' => $this->roomId,
            'rotation' => $this->rotation->id,
        ];

        return array_map(fn ($value) => $values[$value] ?? $value, $parameters);
    }
}
