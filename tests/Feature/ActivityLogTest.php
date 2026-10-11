<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_and_failed_logins_are_recorded(): void
    {
        $user = User::factory()->create(['role' => 'operator', 'status' => true]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $failed = ActivityLog::query()->where('action', 'auth.failed')->firstOrFail();
        $this->assertNull($failed->user_id);
        $this->assertSame($user->email, $failed->meta['email']);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect();

        $login = ActivityLog::query()->where('action', 'auth.login')->firstOrFail();
        $this->assertSame($user->id, $login->user_id);
        $this->assertSame($user->email, $login->meta['email']);

        $this->post(route('logout'))->assertRedirect(route('login'));

        $logout = ActivityLog::query()->where('action', 'auth.logout')->firstOrFail();
        $this->assertSame($user->id, $logout->user_id);
    }

    public function test_admin_writes_are_recorded_with_their_subject(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'status' => true]);
        $operator = User::factory()->create(['role' => 'operator', 'status' => true]);

        $this->actingAs($owner)
            ->patch(route('admin.users.status', $operator))
            ->assertRedirect();

        $log = ActivityLog::query()->where('action', 'admin.users.status')->firstOrFail();
        $this->assertSame($owner->id, $log->user_id);
        $this->assertSame($operator->getMorphClass(), $log->subject_type);
        $this->assertSame($operator->id, $log->subject_id);
        $this->assertFalse($operator->fresh()->status);
    }

    public function test_admin_reads_are_not_recorded(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'status' => true]);

        $this->actingAs($owner)->get(route('admin.users.index'))->assertOk();

        $this->assertSame(0, ActivityLog::query()->count());
    }

    public function test_passwords_never_reach_the_activity_log(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'status' => true]);

        $this->actingAs($owner)->post(route('admin.users.store'), [
            'name' => 'Field Admin',
            'email' => 'field-admin@example.test',
            'role' => 'temp_admin',
            'password' => 'SuperSecret12345!',
            'password_confirmation' => 'SuperSecret12345!',
        ])->assertRedirect(route('admin.users.index'));

        $log = ActivityLog::query()->where('action', 'admin.users.store')->firstOrFail();
        $this->assertStringNotContainsString('SuperSecret', json_encode($log->meta));
    }
}
