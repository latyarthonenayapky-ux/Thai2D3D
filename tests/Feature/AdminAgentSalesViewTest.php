<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentRoundSetting;
use App\Models\AgentSession;
use App\Models\Round;
use App\Models\User;
use App\Services\InitializeAdminBusiness;
use App\Services\SaleProcessor;
use Database\Seeders\CodeRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An Agent's own data — typed on the phone or in the office — has to reach the
 * Admin's Sale Entry already grouped under that Agent, the way an Operator sees
 * the Agents they handle. Reading is open to the business owner; writing stays
 * with the handler of the session.
 */
class AdminAgentSalesViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_sale_entry_lists_every_agent_with_its_own_totals(): void
    {
        [$admin, $operator, $round] = $this->business();
        $quiet = $this->claim($round, $operator, 'PH-01');
        $busy = $this->claim($round, $operator, 'PH-02');
        app(SaleProcessor::class)->process('121000', $quiet);
        app(SaleProcessor::class)->process('121000', $busy);
        app(SaleProcessor::class)->process('132500', $busy);

        $this->actingAs($admin)
            ->get(route('operator.sales.entry', ['round_id' => $round->id]))
            ->assertOk()
            ->assertSee('Sales by Agent · this Round')
            ->assertSeeInOrder(['PH-02', '3,500.00', 'PH-01', '1,000.00'])
            ->assertSee('view=agent');
    }

    public function test_the_admin_opens_an_operators_agent_statement_read_only(): void
    {
        [$admin, $operator, $round] = $this->business();
        $session = $this->claim($round, $operator, 'LOOK-ONLY');
        app(SaleProcessor::class)->process('121000', $session);

        $this->actingAs($admin)
            ->get(route('operator.sales.show', [$session, 'mode' => '2d', 'view' => 'agent']))
            ->assertOk()
            ->assertSee('121000')
            ->assertSee('disabled');

        $this->actingAs($admin)
            ->post(route('operator.sales.store', $session), ['input' => '141000'])
            ->assertNotFound();
        $this->assertSame(1, $session->saleInputs()->count());

        $this->actingAs($operator)
            ->post(route('operator.sales.store', $session), ['input' => '141000'])
            ->assertRedirect();
        $this->assertSame(2, $session->saleInputs()->count());
    }

    public function test_another_operator_still_cannot_read_a_foreign_agent_statement(): void
    {
        [$admin, $operator, $round] = $this->business();
        $session = $this->claim($round, $operator, 'PRIVATE-ONE');
        $stranger = User::factory()->create([
            'role' => 'operator',
            'admin_id' => $admin->id,
            'status' => true,
        ]);

        $this->actingAs($stranger)
            ->get(route('operator.sales.show', $session))
            ->assertNotFound();
    }

    public function test_a_different_business_cannot_open_the_agent_statement(): void
    {
        [, $operator, $round] = $this->business();
        $session = $this->claim($round, $operator, 'ISOLATED');
        $stranger = User::factory()->create(['role' => 'admin', 'status' => true]);
        app(InitializeAdminBusiness::class)->initialize($stranger);

        $this->actingAs($stranger)
            ->get(route('operator.sales.show', $session))
            ->assertNotFound();
    }

    /**
     * @return array{0: User, 1: User, 2: Round}
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
        $round = Round::query()->create([
            'admin_id' => $admin->id,
            'round_date' => now('Asia/Yangon')->toDateString(),
            'round_no' => 1,
            'start_time' => '07:00:00',
            'close_time' => '23:59:59',
            'number_limit' => 100,
            'status' => 'open',
        ]);

        return [$admin, $operator, $round];
    }

    private function claim(Round $round, User $operator, string $code): AgentSession
    {
        $agent = Agent::query()->create([
            'admin_id' => $round->admin_id,
            'agent_code' => $code,
            'agent_name' => $code,
        ]);
        AgentRoundSetting::query()->create([
            'agent_id' => $agent->id,
            'round_id' => $round->id,
            'operator_id' => $operator->id,
        ]);

        return AgentSession::openSession($agent, $round, $operator->id);
    }
}
