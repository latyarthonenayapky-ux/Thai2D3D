<?php

namespace Tests\Feature;

use App\Models\AdminBusinessSetting;
use App\Models\Agent;
use App\Models\AgentSession;
use App\Models\HotNumber;
use App\Models\Round;
use App\Models\RoundResultChange;
use App\Models\User;
use App\Services\InitializeAdminBusiness;
use App\Services\RoundSettlementCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoundSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_closed_round_results_are_audited_and_settlement_uses_only_accepted_stakes(): void
    {
        [$admin, $operator, $round, $agent, $session] = $this->createSalesRound();
        $this->actingAs($admin)
            ->put(route('admin.settings.payouts'), [
                'payout_2d_multiplier' => '5.00',
                'payout_3d_multiplier' => '500.00',
            ])
            ->assertRedirect();
        $this->assertSame(
            '5.00',
            AdminBusinessSetting::query()->where('admin_id', $admin->id)->value('payout_2d_multiplier')
        );

        $this->actingAs($operator)
            ->post(route('operator.sales.store', $session), ['input' => '121000'])
            ->assertRedirect();
        $this->post(route('operator.sales.store', $session), ['input' => '121000'])
            ->assertRedirect();

        HotNumber::query()->create(['round_id' => $round->id, 'number' => '56']);
        $this->post(route('operator.sales.store', $session), ['input' => '561000'])
            ->assertRedirect();

        $session->close();
        $round->update(['status' => 'closed']);

        $this->actingAs($admin)
            ->get(route('admin.rounds.settlement', $round))
            ->assertOk()
            ->assertSee('Official Round results')
            ->assertSee('Accepted 2D stakes');
        $this->get(route('admin.settlements.index'))
            ->assertOk()
            ->assertSee('Round results and settlements')
            ->assertSee('2026-10-07');

        $this->patch(route('admin.rounds.result.update', $round), [
            'result_2d' => '12',
        ])->assertRedirect(route('admin.rounds.settlement', $round));

        $this->assertDatabaseHas('round_results', [
            'admin_id' => $admin->id,
            'round_id' => $round->id,
            'result_2d' => '12',
            'result_3d' => null,
        ]);
        $this->assertSame(1, RoundResultChange::query()->count());

        $settlement = app(RoundSettlementCalculator::class)->calculate($round);
        $this->assertEquals(2000.0, $settlement['totals']['total_stake']);
        $this->assertEquals(2000.0, $settlement['totals']['winning_stake']);
        $this->assertEquals(10000.0, $settlement['totals']['winnings']);
        $this->assertEquals(100.0, $settlement['totals']['commission']);

        $this->get(route('admin.rounds.settlement', $round))
            ->assertOk()
            ->assertSee('2,000.00')
            ->assertSee('2,000.00')
            ->assertSee('10,000.00')
            ->assertSee('100.00')
            ->assertSee('Result change audit');

        $this->actingAs($operator)
            ->get(route('operator.sales.show', $session))
            ->assertOk()
            ->assertSee('Your 2D settlement for this Round')
            ->assertSee('10,000.00');

        $this->actingAs($admin)
            ->patch(route('admin.rounds.result.update', $round), [
                'result_2d' => '34',
            ])
            ->assertRedirect();
        $this->assertSame(2, RoundResultChange::query()->count());
        $this->get(route('admin.rounds.settlement', $round))
            ->assertSee('0.00')
            ->assertSee('Business net');
    }

    public function test_round_results_are_admin_scoped_closed_only_and_digit_validated(): void
    {
        [$admin, , $round] = $this->createSalesRound();
        $otherAdmin = User::factory()->create(['role' => 'admin', 'status' => true]);
        app(InitializeAdminBusiness::class)->initialize($otherAdmin);

        $this->actingAs($otherAdmin)
            ->get(route('admin.rounds.settlement', $round))
            ->assertNotFound();

        $this->actingAs($admin)
            ->patch(route('admin.rounds.result.update', $round), [
                'result_2d' => '9',
            ])
            ->assertStatus(409);

        $round->update(['status' => 'closed']);
        $this->from(route('admin.rounds.settlement', $round))
            ->patch(route('admin.rounds.result.update', $round), [
                'result_2d' => '9',
            ])
            ->assertSessionHasErrors(['result_2d']);
    }

    private function createSalesRound(): array
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
            'agent_code' => 'SETTLE-01',
            'agent_name' => 'Settlement Agent',
            'commission_2d_percent' => '5.00',
            'commission_3d_percent' => '10.00',
        ]);
        $round = Round::query()->create([
            'admin_id' => $admin->id,
            'round_date' => '2026-10-07',
            'round_no' => 1,
            'start_time' => '07:00:00',
            'close_time' => '09:30:00',
            'number_limit' => 500,
            'status' => 'open',
        ]);
        $session = AgentSession::openSession($agent, $round, $operator->id);

        return [$admin, $operator, $round, $agent, $session];
    }
}
