<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentRoundSetting;
use App\Models\AgentSession;
use App\Models\HotNumber;
use App\Models\Round;
use App\Models\RoundNumberAmountLimit;
use App\Models\User;
use App\Services\InitializeAdminBusiness;
use App\Services\SaleProcessor;
use Database\Seeders\CodeRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleEntryWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_2d_entry_point_stops_at_round_setup_before_any_agent_is_claimed(): void
    {
        [, $operator, $round] = $this->business();

        $this->actingAs($operator)
            ->get(route('operator.sales.workspace'))
            ->assertRedirect(route('operator.sales.setup', ['round_id' => $round->id]));

        $this->assertSame(0, AgentSession::query()->count());

        $this->get(route('operator.sales.setup', ['round_id' => $round->id]))
            ->assertOk()
            ->assertSee('Round limits · all Agents')
            ->assertSee('blocked values (00–99)')
            ->assertSee('Hot Numbers')
            ->assertSee('Amount Limit · each number 00–99')
            ->assertSee('1,000.00')
            ->assertSee('Continue to Sale Input')
            ->assertSee('choose the Agent first')
            ->assertDontSee('Save Number Limit')
            ->assertDontSee('Add Hot Numbers');
    }

    public function test_the_admin_round_setup_page_edits_limits_for_every_agent(): void
    {
        [$admin, , $round] = $this->business();

        $this->actingAs($admin)
            ->get(route('operator.sales.setup', ['round_id' => $round->id]))
            ->assertOk()
            ->assertSee('Save Number Limit')
            ->assertSee('Add Hot Numbers')
            ->assertSee('07');
    }

    public function test_sale_input_stays_locked_until_an_agent_is_chosen(): void
    {
        [, $operator, $round] = $this->business();

        $response = $this->actingAs($operator)
            ->get(route('operator.sales.entry', ['round_id' => $round->id]))
            ->assertOk()
            ->assertViewHas('agentSession', null)
            ->assertViewHas('canEnterSales', false)
            ->assertSee('Choose an Agent below first')
            ->assertSee('— Choose an Agent —')
            ->assertSee('Round setup · limits')
            ->assertSee('Copy · No=Excess')
            ->assertSee('Above Amount Limit · all Agents')
            ->assertDontSee('Round limits · all Agents');

        $this->assertStringContainsString('disabled', $this->saleInputTag($response));
        $this->assertSame(0, AgentSession::query()->count());
    }

    public function test_choosing_an_agent_from_the_sale_input_dropdown_claims_it_and_unlocks_the_input(): void
    {
        [, $operator, $round, $agentOne] = $this->business();

        $this->actingAs($operator)
            ->post(route('operator.sales.select'), ['round_id' => $round->id, 'agent_id' => $agentOne->id])
            ->assertRedirect(route('operator.sales.show', [$this->sessionFor($round, $agentOne), 'mode' => '2d']));
        $this->assertSame($operator->id, $this->sessionFor($round, $agentOne)->operator_id);

        $response = $this->get(route('operator.sales.show', [$this->sessionFor($round, $agentOne), 'mode' => '2d']))
            ->assertOk()
            ->assertViewHas('canEnterSales', true)
            ->assertSee('A1 · First Agent')
            ->assertSee('Agent · choose before any input')
            ->assertDontSee('Choose an Agent below first');

        $this->assertStringNotContainsString('disabled', $this->saleInputTag($response));
    }

    public function test_the_live_ledger_shows_all_hundred_numbers_without_an_inner_scroll(): void
    {
        [, $operator, $round, $agentOne] = $this->business();
        $session = AgentSession::openSession($agentOne, $round, $operator->id);

        $content = $this->actingAs($operator)
            ->get(route('operator.sales.show', [$session, 'mode' => '2d']))
            ->assertOk()
            ->assertSee('75–99')
            ->getContent();

        preg_match_all('/data-number="(\d{2})"/', $content, $matches);
        $numbers = $matches[1];
        sort($numbers);

        $this->assertSame(array_map(fn (int $n): string => str_pad((string) $n, 2, '0', STR_PAD_LEFT), range(0, 99)), $numbers);
        $this->assertStringContainsString('ledger-wrap ledger-wrap-full', $content);
        $this->assertStringContainsString('.ledger-wrap-full{max-height:none', $content);
        $this->assertSame(25, preg_match_all('/<tr>\s*<td class="ledger-number"/', $content));
        $this->assertSame(100, substr_count($content, 'data-amount-for='));
    }

    public function test_an_already_open_session_short_circuits_the_round_setup(): void
    {
        [, $operator, $round, $agentOne] = $this->business();
        $session = AgentSession::openSession($agentOne, $round, $operator->id);

        $this->actingAs($operator)
            ->get(route('operator.sales.workspace'))
            ->assertRedirect(route('operator.sales.show', [$session, 'mode' => '2d']));

        $this->get(route('operator.sales.entry', ['round_id' => $round->id]))
            ->assertRedirect(route('operator.sales.show', [$session, 'mode' => '2d']));

        $this->get(route('operator.sales.setup', ['round_id' => $round->id]))
            ->assertOk()
            ->assertSee('open in this Round');
    }

    public function test_the_dropdown_claims_an_unclaimed_agent_and_hides_agents_used_by_others(): void
    {
        [$admin, $operator, $round, $agentOne, $agentTwo] = $this->business();
        $otherOperator = User::factory()->create([
            'role' => 'operator',
            'admin_id' => $admin->id,
            'status' => true,
            'name' => 'Other Operator',
        ]);
        $session = AgentSession::openSession($agentOne, $round, $operator->id);
        AgentSession::openSession($agentTwo, $round, $otherOperator->id);

        $this->actingAs($operator)
            ->get(route('operator.sales.show', [$session, 'mode' => '2d']))
            ->assertOk()
            ->assertViewHas('agentOptions', fn ($options): bool => $options->firstWhere('agent.id', $agentOne->id)['selectable']
                && ! $options->firstWhere('agent.id', $agentTwo->id)['selectable'])
            ->assertSee('In use by Other Operator');

        $thirdAgent = Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'A3',
            'agent_name' => 'Third Agent',
        ]);
        $this->post(route('operator.sales.select'), ['round_id' => $round->id, 'agent_id' => $thirdAgent->id])
            ->assertRedirect(route('operator.sales.show', [$this->sessionFor($round, $thirdAgent), 'mode' => '2d']));
        $this->assertSame($operator->id, $this->sessionFor($round, $thirdAgent)->operator_id);

        $this->post(route('operator.sales.select'), ['round_id' => $round->id, 'agent_id' => $agentTwo->id])
            ->assertSessionHasErrors('agent');
    }

    public function test_the_above_amount_limit_list_is_round_wide_in_every_view(): void
    {
        [, $operator, $round, $agentOne, $agentTwo] = $this->business();
        $oneSession = AgentSession::openSession($agentOne, $round, $operator->id);
        $twoSession = AgentSession::openSession($agentTwo, $round, $operator->id);
        // The setup helper caps 12 at 1,000.00 and 34 at 2,500.00 for the whole Round.

        app(SaleProcessor::class)->process('121500', $oneSession);
        app(SaleProcessor::class)->process('343600', $twoSession);

        $this->actingAs($operator)
            ->get(route('operator.sales.show', [$oneSession, 'mode' => '2d']))
            ->assertOk()
            ->assertSee('Above Amount Limit · all Agents')
            ->assertViewHas('overLimitRows', fn (array $rows): bool => count($rows) === 2
                && $rows[0]['number'] === '34'
                && (float) $rows[0]['excess'] === 1100.0
                && $rows[1]['number'] === '12'
                && (float) $rows[1]['excess'] === 500.0);

        $this->get(route('operator.sales.show', [$oneSession, 'mode' => '2d', 'view' => 'agent']))
            ->assertOk()
            ->assertSee('Above Amount Limit · all Agents')
            ->assertViewHas('overLimitRows', fn (array $rows): bool => count($rows) === 2);
    }

    public function test_the_agent_statement_entry_point_survives_the_sale_entry_redirect(): void
    {
        [$admin, , $round, , $agentTwo] = $this->business();
        $session = AgentSession::openSession($agentTwo, $round, $admin->id);

        $this->actingAs($admin)
            ->get(route('operator.sales.workspace', ['view' => 'agent']))
            ->assertRedirect(route('operator.sales.show', [$session, 'mode' => '2d', 'view' => 'agent']));
    }

    public function test_sale_entry_reports_when_the_round_has_no_agent_left_to_choose(): void
    {
        [$admin, $operator, $round, $agentOne, $agentTwo] = $this->business();
        AgentRoundSetting::query()
            ->where('agent_id', $agentOne->id)
            ->where('round_id', $round->id)
            ->update(['operator_id' => $admin->id]);
        AgentSession::openSession($agentOne, $round, $admin->id);
        AgentSession::openSession($agentTwo, $round, $admin->id);

        $this->actingAs($operator)
            ->get(route('operator.sales.setup', ['round_id' => $round->id]))
            ->assertOk()
            ->assertSee('Every Agent of this Round is held by another account')
            ->assertSee('disabled');

        $this->get(route('operator.sales.entry', ['round_id' => $round->id]))
            ->assertOk()
            ->assertViewHas('selectableCount', 0)
            ->assertSee('Every Agent of this Round is in use by another account');
    }

    public function test_the_sale_page_locks_and_warns_once_the_close_time_has_passed(): void
    {
        [, $operator, $round, $agentOne] = $this->business();
        $round->update(['close_time' => '00:00:00']);
        $session = AgentSession::openSession($agentOne, $round, $operator->id);

        $this->actingAs($operator)
            ->get(route('operator.sales.show', [$session, 'mode' => '2d']))
            ->assertOk()
            ->assertViewHas('canEnterSales', false)
            ->assertSee('This Round closed at 00:00:00.')
            ->assertSee('disabled', false);
    }

    public function test_sale_entry_stays_closed_when_no_round_is_open(): void
    {
        [, $operator, $round] = $this->business();
        $round->forceFill(['status' => 'closed'])->save();

        $this->actingAs($operator)
            ->get(route('operator.sales.workspace'))
            ->assertOk()
            ->assertSee('No 2D Round is open right now.');
    }

    public function test_the_sale_workspace_translates_into_the_operators_chosen_language(): void
    {
        [, $operator, $round, $agentOne] = $this->business();
        $operator->rememberPreference('locale', 'my');
        $session = AgentSession::openSession($agentOne, $round, $operator->id);

        $this->actingAs($operator)
            ->get(route('operator.sales.show', [$session, 'mode' => '2d']))
            ->assertOk()
            ->assertSee('ထည့်မည်')
            ->assertSee('ပွင့်နေသည်')
            ->assertSee('Amount Limit ကျော်')
            ->assertSee('Recent Sales')
            ->assertDontSee('Add</button>')
            ->assertDontSee('app.sale.');
    }

    private function saleInputTag($response): string
    {
        preg_match('/<input id="sale-input"[^>]*>/', $response->getContent(), $matches);
        $this->assertNotEmpty($matches, 'The sale input field is missing from the response.');

        return $matches[0];
    }

    private function sessionFor(Round $round, Agent $agent): AgentSession
    {
        return AgentSession::query()
            ->where('round_id', $round->id)
            ->where('agent_id', $agent->id)
            ->firstOrFail();
    }

    /**
     * @return array{User, User, Round, Agent, Agent}
     */
    private function business(): array
    {
        $this->seed(CodeRuleSeeder::class);
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
            'agent_name' => 'First Agent',
        ]);
        $agentTwo = Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'A2',
            'agent_name' => 'Second Agent',
        ]);
        $round = Round::query()->create([
            'admin_id' => $admin->id,
            'round_date' => now('Asia/Yangon')->toDateString(),
            'round_no' => 1,
            'start_time' => '07:00:00',
            'close_time' => '23:59:59',
            'number_limits' => ['00', '17', '99'],
            'status' => 'open',
        ]);
        AgentRoundSetting::query()->create([
            'agent_id' => $agentOne->id,
            'round_id' => $round->id,
            'operator_id' => $operator->id,
        ]);
        AgentRoundSetting::query()->create([
            'agent_id' => $agentTwo->id,
            'round_id' => $round->id,
        ]);
        RoundNumberAmountLimit::query()->create([
            'round_id' => $round->id,
            'number' => '12',
            'amount_limit' => '1000.00',
        ]);
        RoundNumberAmountLimit::query()->create([
            'round_id' => $round->id,
            'number' => '34',
            'amount_limit' => '2500.00',
        ]);
        HotNumber::query()->create(['round_id' => $round->id, 'number' => '07']);

        return [$admin, $operator, $round, $agentOne, $agentTwo];
    }
}
