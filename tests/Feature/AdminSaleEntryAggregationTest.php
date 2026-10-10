<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentSession;
use App\Models\Round;
use App\Models\User;
use App\Services\InitializeAdminBusiness;
use App\Services\SaleProcessor;
use Database\Seeders\CodeRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSaleEntryAggregationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_live_ledger_aggregates_the_round_while_agent_statement_stays_scoped(): void
    {
        $this->seed(CodeRuleSeeder::class);
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        $operator = User::factory()->create(['role' => 'operator', 'admin_id' => $admin->id, 'status' => true]);
        app(InitializeAdminBusiness::class)->initialize($admin);
        $round = Round::query()->create([
            'admin_id' => $admin->id,
            'round_date' => now('Asia/Yangon')->toDateString(),
            'round_no' => 1,
            'start_time' => '00:00:00',
            'close_time' => '23:59:59',
            'status' => 'open',
        ]);
        $adminAgent = Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'LIVE-01',
            'agent_name' => 'Admin live',
        ]);
        $operatorAgent = Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'LIVE-02',
            'agent_name' => 'Operator live',
        ]);
        $adminSession = AgentSession::openSession($adminAgent, $round, $admin->id);
        $operatorSession = AgentSession::openSession($operatorAgent, $round, $operator->id);

        app(SaleProcessor::class)->process('121000', $adminSession);
        app(SaleProcessor::class)->process('341000', $operatorSession);

        $this->actingAs($admin)
            ->get(route('operator.sales.show', [$adminSession, 'mode' => '2d']))
            ->assertOk()
            ->assertViewHas('roundWide', true)
            ->assertViewHas('acceptedCount', 2)
            ->assertViewHas('acceptedAmount', 2000.0)
            ->assertViewHas('soldItems', fn (array $items): bool => count($items) === 2);

        $this->get(route('operator.sales.show', [$adminSession, 'mode' => '2d', 'view' => 'agent']))
            ->assertOk()
            ->assertSee('Agent Statement')
            ->assertViewHas('roundWide', false)
            ->assertViewHas('acceptedCount', 1)
            ->assertViewHas('acceptedAmount', 1000.0)
            ->assertViewHas('soldItems', fn (array $items): bool => count($items) === 1);

        $this->actingAs($operator)
            ->get(route('operator.sales.show', [$operatorSession, 'mode' => '2d']))
            ->assertOk()
            ->assertViewHas('roundWide', false)
            ->assertViewHas('acceptedCount', 1)
            ->assertViewHas('acceptedAmount', 1000.0)
            ->assertDontSee('Round controls', false);
    }
}
