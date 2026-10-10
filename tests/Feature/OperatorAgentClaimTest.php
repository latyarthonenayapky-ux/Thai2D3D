<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentRoundSetting;
use App\Models\AgentSession;
use App\Models\HotNumber;
use App\Models\Round;
use App\Models\SaleDetail;
use App\Models\SaleInput;
use App\Models\User;
use App\Services\InitializeAdminBusiness;
use App\Services\ThreeDigitSaleProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatorAgentClaimTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_live_dashboard_shows_only_own_open_round_accepted_sales_in_amount_order(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        $otherAdmin = User::factory()->create(['role' => 'admin', 'status' => true]);
        app(InitializeAdminBusiness::class)->initialize($admin);
        app(InitializeAdminBusiness::class)->initialize($otherAdmin);

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
        $lowerAgent = Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'A1',
            'agent_name' => 'Lower Sales',
        ]);
        $higherAgent = Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'A2',
            'agent_name' => 'Higher Sales',
        ]);
        $otherAgent = Agent::query()->create([
            'admin_id' => $otherAdmin->id,
            'agent_code' => 'SECRET',
            'agent_name' => 'Other Business',
        ]);
        $round = $this->openRound($admin, 1);
        $otherRound = $this->openRound($otherAdmin, 1);
        $lowerSession = AgentSession::openSession($lowerAgent, $round, $operator->id);
        $higherSession = AgentSession::openSession($higherAgent, $round, $operator->id);
        $otherSession = AgentSession::openSession($otherAgent, $otherRound, $otherOperator->id);
        $round->update(['number_limit_3d' => 10]);
        AgentRoundSetting::query()
            ->where('agent_id', $lowerAgent->id)
            ->where('round_id', $round->id)
            ->update(['amount_limit' => '1000.00', 'amount_limit_3d' => '500.00']);

        $this->recordSale($lowerSession, $operator, [
            ['12', '1000.00', 'accepted', false],
            ['34', '9000.00', 'rejected', false],
            ['56', '8000.00', 'excluded', true],
        ]);
        $this->recordSale($higherSession, $operator, [
            ['78', '5000.00', 'accepted', false],
        ]);
        $this->recordSale($otherSession, $otherOperator, [
            ['90', '99000.00', 'accepted', false],
        ]);
        app(ThreeDigitSaleProcessor::class)->process('1231000', $lowerSession);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Live sales dashboard')
            ->assertSee('refreshes every 15 seconds')
            ->assertSee('A1 · Lower Sales')
            ->assertSee('A2 · Higher Sales')
            ->assertSee('6,000.00')
            ->assertDontSee('3D accepted numbers')
            ->assertDontSee('3D sales remain accepted')
            ->assertDontSee('SECRET')
            ->assertDontSee('99,000.00')
            ->assertSeeInOrder(['A2 · Higher Sales', '5,000.00', 'A1 · Lower Sales', '1,000.00']);
    }

    public function test_admin_cannot_enter_sales_for_an_agent_claimed_by_an_operator(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        app(InitializeAdminBusiness::class)->initialize($admin);
        $operator = User::factory()->create([
            'role' => 'operator',
            'admin_id' => $admin->id,
            'status' => true,
        ]);
        $agent = Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'ADMIN-SALE',
            'agent_name' => 'Admin entry test',
        ]);
        $round = $this->openRound($admin, 1);
        $round->update(['number_limit_3d' => 100]);
        $session = AgentSession::openSession($agent, $round, $operator->id);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($operator->name)
            ->assertDontSee('Enter sales')
            ->assertDontSee('Claim Agent');

        $this->actingAs($operator)
            ->get(route('operator.sales.show', $session))
            ->assertOk()
            ->assertSee('Sale input');

        $this->actingAs($admin)
            ->get(route('operator.sales.show', $session))
            ->assertNotFound();
        $this->post(route('operator.sales.store', $session), ['input' => '121000'])
            ->assertNotFound();

        $this->assertDatabaseMissing('sale_inputs', [
            'agent_session_id' => $session->id,
            'original_input' => '121000',
        ]);
    }

    public function test_admin_can_claim_an_unassigned_agent_and_other_users_cannot_take_it_over(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        $otherAdmin = User::factory()->create(['role' => 'admin', 'status' => true]);
        app(InitializeAdminBusiness::class)->initialize($admin);
        app(InitializeAdminBusiness::class)->initialize($otherAdmin);
        $operator = User::factory()->create([
            'role' => 'operator',
            'admin_id' => $admin->id,
            'status' => true,
        ]);
        $agent = Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'ADMIN-CLAIM',
            'agent_name' => 'Admin claim test',
        ]);
        $round = $this->openRound($admin, 1);
        $round->update(['number_limit_3d' => 100]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Claim Agent');
        $this->post(route('operator.agent-sessions.claim', [$round, $agent]))
            ->assertRedirect();

        $session = AgentSession::query()->where('agent_id', $agent->id)->firstOrFail();
        $this->assertSame($admin->id, $session->operator_id);
        $this->assertDatabaseHas('agent_round_settings', [
            'agent_id' => $agent->id,
            'round_id' => $round->id,
            'operator_id' => $admin->id,
        ]);
        $this->get(route('operator.sales.show', $session))
            ->assertOk()
            ->assertSee('Sale Entry · 2D')
            ->assertDontSee('data-offline-sale-form')
            ->assertDontSee('Open offline sales mode');
        $this->post(route('operator.sales.store', $session), ['input' => '121000'])
            ->assertRedirect(route('operator.sales.show', $session));
        $this->post(route('operator.sales.store', $session), ['sale_type' => '3d', 'input' => '123500'])
            ->assertRedirect(route('operator.sales.show', [$session, 'mode' => '3d']));

        $this->assertDatabaseHas('sale_inputs', [
            'agent_session_id' => $session->id,
            'operator_id' => $admin->id,
            'original_input' => '121000',
            'status' => 'accepted',
        ]);
        $this->assertDatabaseHas('three_digit_sale_inputs', [
            'agent_session_id' => $session->id,
            'operator_id' => $admin->id,
            'original_input' => '123500',
            'status' => 'accepted',
        ]);

        $this->actingAs($operator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Assigned to Admin')
            ->assertDontSee('Enter sales');
        $this->post(route('operator.agent-sessions.claim', [$round, $agent]))
            ->assertSessionHasErrors('agent');
        $this->get(route('operator.sales.show', $session))->assertNotFound();

        $this->actingAs($otherAdmin)
            ->post(route('operator.agent-sessions.claim', [$round, $agent]))
            ->assertNotFound();
        $this->get(route('operator.sales.show', $session))->assertNotFound();

    }

    public function test_admin_sale_entry_lists_all_round_agents_and_can_claim_available_agents_in_place(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        app(InitializeAdminBusiness::class)->initialize($admin);
        $operator = User::factory()->create([
            'role' => 'operator',
            'admin_id' => $admin->id,
            'status' => true,
        ]);
        $agentOne = Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'A1',
            'agent_name' => 'Admin Agent One',
        ]);
        $agentTwo = Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'A2',
            'agent_name' => 'Available Agent Two',
        ]);
        $agentFour = Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'A4',
            'agent_name' => 'Operator Agent Four',
        ]);
        $round = $this->openRound($admin, 1);
        $adminSession = AgentSession::openSession($agentOne, $round, $admin->id);
        AgentSession::openSession($agentFour, $round, $operator->id);

        $this->actingAs($admin)
            ->get(route('operator.sales.show', $adminSession))
            ->assertOk()
            ->assertSee('A1 · Admin Agent One')
            ->assertSee('A2 · Available Agent Two')
            ->assertSee('A4 · Operator Agent Four')
            ->assertSee('In use by '.$operator->name)
            ->assertSee('data-agent-claim-form', false)
            ->assertSee('data-claim-url=', false);

        $this->post(route('operator.agent-sessions.claim', [$round, $agentTwo]))
            ->assertRedirect();
        $agentTwoSession = AgentSession::query()
            ->where('round_id', $round->id)
            ->where('agent_id', $agentTwo->id)
            ->firstOrFail();
        $this->assertSame($admin->id, $agentTwoSession->operator_id);

        $this->get(route('operator.sales.show', $agentTwoSession))
            ->assertOk()
            ->assertSee('Available Agent Two')
            ->assertSee('A1 · Admin Agent One')
            ->assertSee('A4 · Operator Agent Four');

        $this->post(route('operator.agent-sessions.claim', [$round, $agentFour]))
            ->assertSessionHasErrors('agent');
        $this->assertSame($operator->id, $agentFour->fresh()->roundSettings()->where('round_id', $round->id)->value('operator_id'));
    }

    public function test_operators_can_claim_multiple_agents_per_round_but_only_first_operator_can_claim_each_agent(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        app(InitializeAdminBusiness::class)->initialize($admin);

        $firstOperator = User::factory()->create([
            'role' => 'operator',
            'admin_id' => $admin->id,
            'status' => true,
        ]);
        $secondOperator = User::factory()->create([
            'role' => 'operator',
            'admin_id' => $admin->id,
            'status' => true,
        ]);
        $agentOne = Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'A1',
            'agent_name' => 'Agent A1',
        ]);
        $agentFour = Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'A4',
            'agent_name' => 'Agent A4',
        ]);
        $roundOne = $this->openRound($admin, 1);
        $roundTwo = $this->openRound($admin, 2);

        $this->actingAs($firstOperator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('A1 · Agent A1')
            ->assertSee('Claim Agent');

        $this->post(route('operator.agent-sessions.claim', [$roundOne, $agentOne]))
            ->assertRedirect();
        $this->post(route('operator.agent-sessions.claim', [$roundOne, $agentFour]))
            ->assertRedirect();

        $this->assertSame(2, AgentSession::query()
            ->where('round_id', $roundOne->id)
            ->where('operator_id', $firstOperator->id)
            ->count());

        $this->actingAs($firstOperator)
            ->get(route('dashboard'))
            ->assertSee('Claimed by you · Active');

        $this->actingAs($admin)
            ->patch(route('admin.settings.rounds.agents.amount-limit', [$roundOne, $agentOne]), [
                'amount_limit' => '100',
            ])
            ->assertRedirect();
        $this->patch(route('admin.settings.rounds.agents.amount-limit', [$roundOne, $agentOne]), [
            'amount_limit' => '',
        ])->assertRedirect();
        $this->assertSame(
            $firstOperator->id,
            AgentRoundSetting::query()
                ->where('agent_id', $agentOne->id)
                ->where('round_id', $roundOne->id)
                ->value('operator_id')
        );

        $this->actingAs($secondOperator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Assigned to another Operator');
        $this->post(route('operator.agent-sessions.claim', [$roundOne, $agentOne]))
            ->assertSessionHasErrors('agent');

        $this->post(route('operator.agent-sessions.claim', [$roundTwo, $agentOne]))
            ->assertRedirect();

        $this->assertSame(
            $secondOperator->id,
            AgentRoundSetting::query()
                ->where('agent_id', $agentOne->id)
                ->where('round_id', $roundTwo->id)
                ->value('operator_id')
        );
        $this->assertSame(
            $firstOperator->id,
            AgentRoundSetting::query()
                ->where('agent_id', $agentOne->id)
                ->where('round_id', $roundOne->id)
                ->value('operator_id')
        );
    }

    public function test_operator_can_enter_sales_view_history_and_only_access_their_own_session(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        app(InitializeAdminBusiness::class)->initialize($admin);
        $operator = User::factory()->create([
            'role' => 'operator',
            'admin_id' => $admin->id,
            'status' => true,
        ]);
        $otherOperator = User::factory()->create([
            'role' => 'operator',
            'admin_id' => $admin->id,
            'status' => true,
        ]);
        $agent = Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'SALE-01',
            'agent_name' => 'Sales Agent',
        ]);
        $round = $this->openRound($admin, 1);

        $this->actingAs($operator)
            ->post(route('operator.agent-sessions.claim', [$round, $agent]))
            ->assertRedirect();

        $session = AgentSession::query()->where('agent_id', $agent->id)->firstOrFail();
        $this->get(route('operator.sales.show', $session))
            ->assertOk()
            ->assertSee('Sale input');

        $this->post(route('operator.sales.store', $session), ['input' => '121000'])
            ->assertRedirect(route('operator.sales.show', $session))
            ->assertSessionHas('status');
        $this->get(route('operator.sales.show', $session))
            ->assertOk()
            ->assertSee('Accepted amount')
            ->assertSee('1,000.00')
            ->assertSee('121000')
            ->assertSee('12');
        $this->assertDatabaseHas('sale_inputs', [
            'agent_session_id' => $session->id,
            'original_input' => '121000',
            'status' => 'accepted',
        ]);

        HotNumber::query()->create(['round_id' => $round->id, 'number' => '12']);
        $this->post(route('operator.sales.store', $session), ['input' => '121000'])
            ->assertRedirect(route('operator.sales.show', $session))
            ->assertSessionHas('status');
        $this->assertSame(1, SaleDetail::query()->where('status', 'accepted')->count());
        $this->assertSame(1, SaleDetail::query()->where('reject_reason', 'HOT_NUMBER')->count());

        $this->from(route('operator.sales.show', $session))
            ->post(route('operator.sales.store', $session), ['input' => 'a1000'])
            ->assertSessionHasErrors('input');
        $this->assertSame(2, SaleInput::query()->count());

        $this->actingAs($otherOperator)
            ->get(route('operator.sales.show', $session))
            ->assertNotFound();

        $session->close();
        $round->update(['status' => 'closed']);
        $this->actingAs($operator)
            ->get(route('dashboard'))
            ->assertSee('Your recent Agent/Round sessions')
            ->assertSee('View sales');
        $this->get(route('operator.sales.show', $session))
            ->assertOk()
            ->assertSee('read-only');
        $this->post(route('operator.sales.store', $session), ['input' => '341000'])
            ->assertStatus(409);
    }

    private function openRound(User $admin, int $roundNo): Round
    {
        return Round::query()->create([
            'admin_id' => $admin->id,
            'round_date' => '2026-10-07',
            'round_no' => $roundNo,
            'start_time' => '07:00:00',
            'close_time' => '09:30:00',
            'number_limit' => 500,
            'status' => 'open',
        ]);
    }

    private function recordSale(AgentSession $session, User $operator, array $details): void
    {
        $saleInput = SaleInput::query()->create([
            'agent_session_id' => $session->id,
            'operator_id' => $operator->id,
            'original_input' => 'dashboard-test',
            'normalized_input' => 'DASHBOARD-TEST',
            'input_type' => 'TEST',
            'number_count' => count($details),
            'amount' => 1000,
            'total_amount' => 1000,
            'status' => 'accepted',
        ]);

        foreach ($details as [$number, $amount, $status, $excluded]) {
            SaleDetail::query()->create([
                'sale_input_id' => $saleInput->id,
                'number' => $number,
                'amount' => $amount,
                'status' => $status,
                'is_excluded' => $excluded,
                'reject_reason' => $status === 'rejected' ? 'HOT_NUMBER' : null,
            ]);
        }
    }
}
