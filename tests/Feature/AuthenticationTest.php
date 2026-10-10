<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login_and_registration_is_not_available(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('login'))->assertOk();
        $this->get('/register')->assertNotFound();
    }

    public function test_active_user_can_sign_in_and_sign_out(): void
    {
        $user = User::factory()->create([
            'password' => 'correct-password',
            'status' => true,
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('draw-mode.index'));

        $this->assertAuthenticatedAs($user);
        $this->get(route('draw-mode.index'))->assertOk();
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_inactive_account_cannot_sign_in(): void
    {
        $user = User::factory()->create([
            'password' => 'correct-password',
            'status' => false,
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_operator_cannot_access_admin_user_management(): void
    {
        $operator = User::factory()->create([
            'role' => 'operator',
            'status' => true,
        ]);

        $this->actingAs($operator)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_initial_admin_is_created_as_owner_from_artisan_command(): void
    {
        $this->artisan('thai2d3d:make-owner')
            ->expectsQuestion('Owner name', 'First Owner')
            ->expectsQuestion('Owner email', 'owner@example.test')
            ->expectsQuestion('Password (at least 12 characters)', 'a-very-secure-password')
            ->expectsQuestion('Confirm password', 'a-very-secure-password')
            ->assertSuccessful();

        $owner = User::query()
            ->where('email', 'owner@example.test')
            ->firstOrFail();

        $this->assertSame('owner', $owner->role);
        $this->assertTrue($owner->status);
        $this->assertTrue(password_get_info($owner->password)['algo'] !== null);
    }

    public function test_owner_is_created_only_when_no_users_exist(): void
    {
        User::factory()->create(['role' => 'operator']);

        $this->artisan('thai2d3d:make-owner')
            ->expectsOutputToContain('only be created before any other user accounts exist')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['role' => 'owner']);
    }

    public function test_owner_can_create_an_admin_and_send_password_setup_link(): void
    {
        Notification::fake();
        $owner = User::factory()->create([
            'role' => 'owner',
            'status' => true,
        ]);

        $this->actingAs($owner)
            ->post(route('admin.users.store'), [
                'name' => 'New Admin',
                'email' => 'admin@example.test',
                'role' => 'admin',
            ])
            ->assertRedirect(route('admin.users.index'));

        $admin = User::query()
            ->where('email', 'admin@example.test')
            ->firstOrFail();

        $this->assertSame('admin', $admin->role);
        Notification::assertSentTo($admin, ResetPassword::class);
    }

    public function test_owner_can_manage_admin_accounts(): void
    {
        $owner = User::factory()->create([
            'role' => 'owner',
            'status' => true,
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => true,
        ]);

        $this->actingAs($owner)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('value="admin"', false);

        $this->patch(route('admin.users.status', $admin))
            ->assertRedirect();

        $this->assertFalse($admin->fresh()->status);
    }

    public function test_owner_dashboard_does_not_link_to_admin_business_settings(): void
    {
        $owner = User::factory()->create([
            'role' => 'owner',
            'status' => true,
        ]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Manage users')
            ->assertDontSee('Application settings');
    }

    public function test_admin_cannot_create_another_admin(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.store'), [
                'name' => 'Unapproved Admin',
                'email' => 'unapproved@example.test',
                'role' => 'admin',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', [
            'email' => 'unapproved@example.test',
        ]);
    }

    public function test_admin_cannot_deactivate_another_admin(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => true,
        ]);
        $anotherAdmin = User::factory()->create([
            'role' => 'admin',
            'status' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.status', $anotherAdmin))
            ->assertForbidden();

        $this->assertTrue($anotherAdmin->fresh()->status);
    }

    public function test_admin_can_create_operator_and_send_password_setup_link(): void
    {
        Notification::fake();
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'New Operator',
                'email' => 'operator@example.test',
            ])
            ->assertRedirect(route('admin.users.index'));

        $operator = User::query()
            ->where('email', 'operator@example.test')
            ->firstOrFail();

        $this->assertSame('operator', $operator->role);
        $this->assertSame($admin->id, $operator->admin_id);
        $this->assertTrue($operator->status);
        $this->assertNotSame('12345678', $operator->password);
        $this->assertTrue(password_get_info($operator->password)['algo'] !== null);
        Notification::assertSentTo($operator, ResetPassword::class);
    }

    public function test_owner_cannot_create_an_operator(): void
    {
        $owner = User::factory()->create([
            'role' => 'owner',
            'status' => true,
        ]);

        $this->actingAs($owner)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.store'), [
                'name' => 'Direct Operator',
                'email' => 'direct-operator@example.test',
                'role' => 'operator',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'direct-operator@example.test']);
    }

    public function test_admin_sees_only_operators_created_under_that_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        $otherAdmin = User::factory()->create(['role' => 'admin', 'status' => true]);
        $operator = User::factory()->create([
            'role' => 'operator',
            'admin_id' => $admin->id,
            'status' => true,
        ]);
        $otherOperator = User::factory()->create([
            'role' => 'operator',
            'admin_id' => $otherAdmin->id,
            'status' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee($operator->email)
            ->assertDontSee($otherOperator->email);

        $this->actingAs($admin)
            ->patch(route('admin.users.status', $otherOperator))
            ->assertNotFound();
    }

    public function test_password_reset_link_request_does_not_reveal_if_email_exists(): void
    {
        $response = $this->from(route('password.request'))
            ->post(route('password.email'), [
                'email' => 'not-registered@example.test',
            ]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHas(
            'status',
            'If an active account uses that email, a password reset link will be sent.'
        );
    }

    public function test_password_reset_token_updates_the_password(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $token = null;
        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            }
        );

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertRedirect(route('login'));

        $this->assertTrue(
            Hash::check('new-secure-password', $user->fresh()->password)
        );
    }

    public function test_deactivated_session_is_logged_out_on_next_request(): void
    {
        $operator = User::factory()->create([
            'role' => 'operator',
            'status' => true,
        ]);

        $this->actingAs($operator)->get(route('dashboard'))->assertOk();

        $operator->forceFill(['status' => false])->save();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
