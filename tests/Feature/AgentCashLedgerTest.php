<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Agent;
use App\Models\AgentLedgerEntry;
use App\Models\User;
use App\Services\AgentLedger;
use App\Services\InitializeAdminBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentCashLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_ledger_is_admin_only(): void
    {
        $admin = $this->admin();
        $operator = User::factory()->create(['role' => 'operator', 'admin_id' => $admin->id, 'status' => true]);

        $this->get(route('admin.ledger.index'))->assertRedirect(route('login'));
        $this->actingAs($operator)->get(route('admin.ledger.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.ledger.index'))->assertOk();
    }

    public function test_a_deposit_is_recorded_and_shows_in_the_balance(): void
    {
        $admin = $this->admin();
        $agent = $this->agent($admin);

        $this->actingAs($admin)->post(route('admin.ledger.store', $agent), [
            'type' => 'deposit',
            'amount' => '50000.00',
            'occurs_on' => '2026-10-14',
            'note' => 'Cash at the office',
        ])->assertRedirect(route('admin.ledger.show', $agent));

        $entry = AgentLedgerEntry::query()->sole();
        $this->assertSame($admin->id, (int) $entry->admin_id);
        $this->assertSame($agent->id, (int) $entry->agent_id);
        $this->assertSame($admin->id, (int) $entry->recorded_by);
        $this->assertTrue($entry->isMoneyIn());
        $this->assertSame(50000.0, app(AgentLedger::class)->balance($agent));

        $this->actingAs($admin)->get(route('admin.ledger.show', $agent))
            ->assertOk()
            ->assertSee('50,000.00', false)
            ->assertSee(__('app.ledger.type_deposit'), false);
    }

    public function test_credit_given_out_makes_the_balance_negative(): void
    {
        $admin = $this->admin();
        $agent = $this->agent($admin);

        $this->record($admin, $agent, 'deposit', '30000');
        $this->record($admin, $agent, 'credit', '40000');

        $this->assertSame(-10000.0, app(AgentLedger::class)->balance($agent));

        $this->actingAs($admin)->get(route('admin.ledger.show', $agent))
            ->assertSee(__('app.ledger.owed', ['amount' => number_format(10000, 2)]), false);
    }

    public function test_the_running_balance_accumulates_in_book_order(): void
    {
        $admin = $this->admin();
        $agent = $this->agent($admin);

        $this->record($admin, $agent, 'deposit', '10000', '2026-10-03');
        $this->record($admin, $agent, 'deposit', '20000', '2026-10-05');
        $this->record($admin, $agent, 'withdrawal', '25000', '2026-10-06');

        $statement = app(AgentLedger::class)->statement($agent, null, null);

        $this->assertSame([10000.0, 30000.0, 5000.0], $statement['rows']->pluck('running_balance')->all());
        $this->assertSame(0.0, $statement['opening']);
        $this->assertSame(30000.0, $statement['totals']['in']);
        $this->assertSame(25000.0, $statement['totals']['out']);
        $this->assertSame(5000.0, $statement['totals']['closing']);
    }

    public function test_a_date_filter_opens_with_the_balance_from_before_the_window(): void
    {
        $admin = $this->admin();
        $agent = $this->agent($admin);

        $this->record($admin, $agent, 'deposit', '10000', '2026-10-01');
        $this->record($admin, $agent, 'credit', '4000', '2026-10-08');

        $statement = app(AgentLedger::class)->statement($agent, '2026-10-05', '2026-10-10');

        $this->assertSame(1, $statement['rows']->count());
        $this->assertSame(10000.0, $statement['opening']);
        $this->assertSame(4000.0, $statement['totals']['out']);
        $this->assertSame(6000.0, $statement['totals']['closing']);
    }

    public function test_deleting_an_entry_recalculates_the_balance(): void
    {
        $admin = $this->admin();
        $agent = $this->agent($admin);

        $this->record($admin, $agent, 'deposit', '10000');
        $entry = $this->record($admin, $agent, 'repayment', '5000');

        $this->actingAs($admin)->delete(route('admin.ledger.destroy', [$agent, $entry]))
            ->assertRedirect();

        $this->assertSame(10000.0, app(AgentLedger::class)->balance($agent));
        $this->assertDatabaseMissing('agent_ledger_entries', ['id' => $entry->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'agent_ledger.deleted']);
    }

    public function test_another_business_cannot_reach_this_agents_ledger(): void
    {
        $admin = $this->admin();
        $agent = $this->agent($admin);
        $other = $this->admin('other@example.test');

        $this->actingAs($other)->get(route('admin.ledger.show', $agent))->assertNotFound();
        $this->actingAs($other)->post(route('admin.ledger.store', $agent), [
            'type' => 'deposit',
            'amount' => '100',
            'occurs_on' => '2026-10-14',
        ])->assertNotFound();
    }

    public function test_only_a_positive_amount_and_a_known_kind_are_accepted(): void
    {
        $admin = $this->admin();
        $agent = $this->agent($admin);

        $this->actingAs($admin)->post(route('admin.ledger.store', $agent), [
            'type' => 'loan',
            'amount' => '-5',
            'occurs_on' => '2026-10-14',
        ])->assertSessionHasErrors(['type', 'amount']);

        $this->assertSame(0, AgentLedgerEntry::query()->count());
    }

    public function test_the_amount_and_kind_are_written_to_the_activity_log(): void
    {
        $admin = $this->admin();
        $agent = $this->agent($admin);

        $this->record($admin, $agent, 'credit', '12500');

        $entry = ActivityLog::query()->where('action', 'agent_ledger.recorded')->sole();

        $this->assertSame('credit', $entry->meta['type']);
        $this->assertSame(12500.0, (float) $entry->meta['amount']);
    }

    public function test_the_overview_lists_every_agent_with_its_balance(): void
    {
        $admin = $this->admin();
        $first = $this->agent($admin, 'AGT-001', 'Ma Thiri');
        $second = $this->agent($admin, 'AGT-002', 'Ko Zaw');

        $this->record($admin, $first, 'deposit', '8000');

        $this->actingAs($admin)->get(route('admin.ledger.index'))
            ->assertOk()
            ->assertSee('Ma Thiri')
            ->assertSee('Ko Zaw')
            ->assertSee('8,000.00', false);

        $this->actingAs($admin)->get(route('admin.ledger.index', ['q' => 'Zaw']))
            ->assertSee('Ko Zaw')
            ->assertDontSee('Ma Thiri');
    }

    private function record(User $admin, Agent $agent, string $type, string $amount, string $on = '2026-10-14'): AgentLedgerEntry
    {
        $this->actingAs($admin)->post(route('admin.ledger.store', $agent), [
            'type' => $type,
            'amount' => $amount,
            'occurs_on' => $on,
        ])->assertRedirect(route('admin.ledger.show', $agent));

        return AgentLedgerEntry::query()->latest('id')->firstOrFail();
    }

    private function admin(string $email = 'ledger-admin@example.test'): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true, 'email' => $email]);
        app(InitializeAdminBusiness::class)->initialize($admin);

        return $admin;
    }

    private function agent(User $admin, string $code = 'AGT-001', string $name = 'Thiri'): Agent
    {
        return Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => $code,
            'agent_name' => $name,
        ]);
    }
}
