<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private int $facultyId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->facultyId = DB::table('faculties')->insertGetId(['name' => 'Test Faculty']);
    }

    private function makeUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['faculty_id' => $this->facultyId], $attributes));
    }

    private function makeAdmin(): User
    {
        return User::factory()->admin()->create(['faculty_id' => $this->facultyId]);
    }

    public function test_user_cannot_promote_themselves_to_admin(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->patch(route('users.update', $user), [
            'email' => $user->email,
            'username' => 'renamed',
            'temporary_role' => User::ADMIN_TEMPORARY_ROLES[0],
            'number_of_observation' => 0,
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('renamed', $user->username);
        $this->assertFalse($user->isAdmin());
        $this->assertNotSame(0, $user->number_of_observation);
    }

    public function test_user_cannot_update_another_user(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();

        $this->actingAs($user)->patch(route('users.update', $other), [
            'email' => $other->email,
            'username' => $other->username,
            'password' => 'hijacked',
        ])->assertForbidden();

        $this->assertFalse(Hash::check('hijacked', $other->fresh()->password));
    }

    public function test_password_change_requires_correct_old_password(): void
    {
        $user = $this->makeUser(['password' => 'old-secret']);
        $payload = [
            'email' => $user->email,
            'username' => $user->username,
            'new_password' => 'new-secret',
            'new_password_verification' => 'new-secret',
        ];

        $this->actingAs($user)->patch(route('users.update', $user), $payload + ['old_password' => 'wrong']);
        $this->assertTrue(Hash::check('old-secret', $user->fresh()->password));

        $this->actingAs($user)->patch(route('users.update', $user), $payload + ['old_password' => 'old-secret']);
        $this->assertTrue(Hash::check('new-secret', $user->fresh()->password));
    }

    public function test_admin_can_change_another_users_role(): void
    {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();

        $this->actingAs($admin)->patch(route('users.update', $user), [
            'email' => $user->email,
            'username' => $user->username,
            'temporary_role' => 'رئيس قسم',
            'number_of_observation' => 3,
            'property' => '2',
        ])->assertRedirect(route('users.index'));

        $user->refresh();
        $this->assertSame('رئيس قسم', $user->temporary_role);
        $this->assertSame(3, (int) $user->number_of_observation);
        $this->assertSame('عضو هيئة تدريسية', $user->property);
    }

    public function test_non_admin_cannot_delete_users(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();

        $this->actingAs($user)->delete(route('users.destroy', $other))->assertForbidden();
        $this->actingAs($user)->delete(route('users.destroy', $user))->assertForbidden();

        $this->assertNotNull($other->fresh());
        $this->assertNotNull($user->fresh());
    }

    public function test_admin_can_delete_users(): void
    {
        $user = $this->makeUser();

        $this->actingAs($this->makeAdmin())->delete(route('users.destroy', $user))
            ->assertRedirect(route('users.index'));

        $this->assertNull($user->fresh());
    }

    public function test_non_admin_cannot_manage_accounts_or_observation_counts(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->patch(route('users.isActive', $user))->assertForbidden();
        $this->actingAs($user)->post(route('users.store'), [
            'email' => 'new@example.com',
            'username' => 'new-user',
            'password' => 'secret',
            'role' => 'موظف',
        ])->assertForbidden();
        $this->actingAs($user)->patch(route('users.setObservations'), [
            'role_user' => 'all_users',
            'reset_vlaue' => 0,
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
        $this->assertNotSame(0, (int) $user->fresh()->number_of_observation);
    }

    public function test_non_admin_is_forbidden_on_admin_routes_without_user_parameter(): void
    {
        $this->actingAs($this->makeUser())
            ->get(route('courses.index'))
            ->assertForbidden();
    }
}
