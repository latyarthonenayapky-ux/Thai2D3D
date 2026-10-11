<?php

namespace Tests\Feature;

use App\Models\AdminBusinessSetting;
use App\Models\Agent;
use App\Models\RoundSchedule;
use App\Models\User;
use App\Services\InitializeAdminBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFirstRunChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_shows_first_run_checklist_with_cron_note_until_setup_is_complete(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        app(InitializeAdminBusiness::class)->initialize($admin);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Get started checklist')
            ->assertSee('Save the daily Round schedule')
            ->assertSee('Set the 2D payout multiplier')
            ->assertSee('Create and activate Agents')
            ->assertSee('Create Operator accounts')
            ->assertSee('schedule:run');

        RoundSchedule::query()->where('admin_id', $admin->id)->update([
            'start_time' => '07:00:00',
            'close_time' => '09:30:00',
        ]);
        AdminBusinessSetting::query()->where('admin_id', $admin->id)->update([
            'payout_2d_multiplier' => '5.00',
        ]);
        Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'C1',
            'agent_name' => 'Checklist Agent',
        ]);
        User::factory()->create([
            'role' => 'operator',
            'admin_id' => $admin->id,
            'status' => true,
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Get started checklist');
    }

    public function test_empty_round_lists_explain_the_scheduler_requirement(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        app(InitializeAdminBusiness::class)->initialize($admin);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('No open Rounds')
            ->assertSee('schedule:run');

        $this->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('the Laravel scheduler is not running')
            ->assertSee('schedule:run');
    }
}
