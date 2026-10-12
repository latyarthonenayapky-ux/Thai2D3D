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

    public function test_the_activity_log_page_filters_by_account_and_exports_a_csv(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        $operator = User::factory()->create(['role' => 'operator', 'status' => true]);
        ActivityLog::query()->create([
            'user_id' => $operator->id,
            'action' => 'sale_entry.create',
            'meta' => ['input' => '121500'],
            'ip' => '10.0.0.5',
        ]);
        ActivityLog::query()->create([
            'user_id' => $admin->id,
            'action' => 'settings.round.close',
            'meta' => ['round_id' => 7],
            'ip' => '10.0.0.9',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.activity-log.index', ['user_id' => $operator->id]))
            ->assertOk()
            ->assertSee('sale_entry.create')
            ->assertDontSee('settings.round.close');

        $response = $this->actingAs($admin)
            ->get(route('admin.activity-log.export', ['user_id' => $operator->id]))
            ->assertOk();

        $this->assertStringContainsString('attachment; filename=wya-activity-log-', $response->headers->get('content-disposition'));
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('When,Account,Role,Action,Subject,"IP address",Detail', $csv);
        $this->assertStringContainsString('sale_entry.create', $csv);
        $this->assertStringContainsString('input=121500', $csv);
        $this->assertStringContainsString($operator->name, $csv);
        $this->assertStringNotContainsString('settings.round.close', $csv);
        $this->assertSame(2, count(array_filter(explode("\n", $csv))));
    }

    public function test_an_operator_cannot_open_the_activity_log(): void
    {
        $operator = User::factory()->create(['role' => 'operator', 'status' => true]);

        $this->actingAs($operator)->get(route('admin.activity-log.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('admin.activity-log.export'))->assertForbidden();
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
