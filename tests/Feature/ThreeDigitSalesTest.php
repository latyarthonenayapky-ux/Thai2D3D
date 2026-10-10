<?php

namespace Tests\Feature;

use App\Models\AdminBusinessSetting;
use App\Models\Agent;
use App\Models\AgentSession;
use App\Models\Round;
use App\Models\RoundResult;
use App\Models\SaleDetail;
use App\Models\ThreeDigitHotNumber;
use App\Models\ThreeDigitSaleDetail;
use App\Models\ThreeDigitSaleInput;
use App\Models\User;
use App\Services\InitializeAdminBusiness;
use App\Services\RoundSettlementCalculator;
use App\Services\ThreeDigitInputParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThreeDigitSalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_digit_parser_supports_literal_inputs_and_triples_only_a_rule(): void
    {
        $parser = app(ThreeDigitInputParser::class);
        $normal = $parser->parse('123500');
        $this->assertSame(['123'], $normal['numbers']);
        $this->assertSame(500, $normal['amount']);

        $triples = $parser->parse('A1000');
        $this->assertSame(['000', '111', '222', '333', '444', '555', '666', '777', '888', '999'], $triples['numbers']);
        $this->assertSame(10, $triples['number_count']);
        $this->assertSame(10000, $triples['total_amount']);

        foreach (['a1000', 'A0', '123 500', '12R500', 'A[123]500'] as $invalid) {
            try {
                $parser->parse($invalid);
                $this->fail("Expected {$invalid} to be rejected by the 3D parser.");
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_3d_sales_use_separate_round_limits_and_hot_numbers_and_never_use_amount_limit_for_rejection(): void
    {
        [$admin, $operator, $round, $agent, $session] = $this->createBusiness();
        $round->update(['number_limit_3d' => 9]);
        $agentSetting = $round->agentRoundSettings()->where('agent_id', $agent->id)->firstOrFail();
        $agentSetting->update(['amount_limit_3d' => '1.00']);

        ThreeDigitHotNumber::query()->create(['round_id' => $round->id, 'number' => '111']);
        $this->actingAs($operator)
            ->post(route('operator.sales.store', $session), ['sale_type' => '3d', 'input' => 'A1000'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $sale = ThreeDigitSaleInput::query()->where('agent_session_id', $session->id)->firstOrFail();
        $this->assertSame('accepted', $sale->status);
        $this->assertSame(9, $sale->details()->where('status', 'accepted')->count());
        $this->assertSame(1, $sale->details()->where('reject_reason', 'HOT_NUMBER_3D')->count());
        $this->assertSame(9000.0, (float) $sale->details()->where('status', 'accepted')->sum('amount'));
        $this->assertSame(10, ThreeDigitSaleDetail::query()->count());

        $secondAgent = Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'D3-02',
            'agent_name' => 'Second three digit agent',
        ]);
        $secondSession = AgentSession::openSession($secondAgent, $round, $operator->id);
        $this->post(route('operator.sales.store', $secondSession), ['sale_type' => '3d', 'input' => '123500'])
            ->assertRedirect();
        $overLimit = ThreeDigitSaleInput::query()->where('original_input', '123500')->firstOrFail();
        $this->assertSame('rejected', $overLimit->status);
        $this->assertSame('NUMBER_LIMIT_3D', $overLimit->reject_reason);
        $this->assertSame(1, ThreeDigitSaleDetail::query()->where('three_digit_sale_input_id', $overLimit->id)->where('reject_reason', 'NUMBER_LIMIT_3D')->count());
        $this->assertSame(0, SaleDetail::query()->count());
    }

    public function test_3d_hot_numbers_are_separate_and_are_rejected_before_acceptance(): void
    {
        [$admin, $operator, $round, , $session] = $this->createBusiness();
        $round->update(['number_limit_3d' => 1]);
        ThreeDigitHotNumber::query()->create(['round_id' => $round->id, 'number' => '123']);

        $this->actingAs($admin)
            ->get(route('admin.settings.index', ['round_id' => $round->id]))
            ->assertOk()
            ->assertSee('Daily Round schedule')
            ->assertDontSee('3D Hot Number list')
            ->assertDontSee('123');

        $this->get(route('admin.three-digit.index'))
            ->assertOk()
            ->assertSee('3D Settings');

        $this->actingAs($operator)
            ->post(route('operator.sales.store', $session), ['sale_type' => '3d', 'input' => '123500'])
            ->assertRedirect();
        $this->assertDatabaseHas('three_digit_sale_details', [
            'number' => '123',
            'status' => 'rejected',
            'reject_reason' => 'HOT_NUMBER_3D',
        ]);

        $this->post(route('operator.sales.store', $session), ['sale_type' => '3d', 'input' => '456500'])
            ->assertRedirect();
        $this->assertDatabaseHas('three_digit_sale_details', [
            'number' => '456',
            'status' => 'accepted',
        ]);
    }

    public function test_admin_can_configure_3d_hot_numbers_and_agent_round_amount_monitoring(): void
    {
        [$admin, , $round, $agent] = $this->createBusiness();

        $this->actingAs($admin)
            ->post(route('admin.settings.hot-numbers-3d.store', $round), [
                'numbers' => "001, 123\n123 999",
            ])
            ->assertRedirect(route('admin.settings.index', ['round_id' => $round->id]));
        $this->assertSame(3, ThreeDigitHotNumber::query()->where('round_id', $round->id)->count());

        $this->from(route('admin.settings.index', ['round_id' => $round->id]))
            ->post(route('admin.settings.hot-numbers-3d.store', $round), ['numbers' => '12, 1234'])
            ->assertSessionHasErrors('numbers_3d');

        $this->patch(route('admin.settings.rounds.agents.amount-limit-3d', [$round, $agent]), [
            'amount_limit_3d' => '2500.00',
        ])->assertRedirect(route('admin.settings.index', ['round_id' => $round->id]));
        $this->assertSame(
            '2500.00',
            $round->agentRoundSettings()->where('agent_id', $agent->id)->value('amount_limit_3d')
        );
    }

    public function test_3d_sales_are_included_in_separate_round_settlement_and_results(): void
    {
        [$admin, $operator, $round, $agent, $session] = $this->createBusiness();
        $round->update(['number_limit_3d' => 100]);
        AdminBusinessSetting::query()->where('admin_id', $admin->id)->update([
            'payout_3d_multiplier' => '1000.00',
        ]);

        $this->actingAs($operator)
            ->post(route('operator.sales.store', $session), ['sale_type' => '3d', 'input' => '123500'])
            ->assertRedirect();
        $session->close();
        $round->update(['status' => 'closed']);
        RoundResult::query()->create([
            'admin_id' => $admin->id,
            'round_id' => $round->id,
            'result_3d' => '123',
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.rounds.settlement', $round))
            ->assertOk()
            ->assertSee('Agent settlement · 2D')
            ->assertSee('Open separate 3D Draw settlements')
            ->assertDontSee('500,000.00');
        $settlement = app(RoundSettlementCalculator::class)->calculate($round);
        $this->assertEquals(500.0, $settlement['totals']['three_digit_total_stake']);
        $this->assertEquals(500.0, $settlement['totals']['three_digit_winning_stake']);
        $this->assertEquals(500000.0, $settlement['totals']['three_digit_winnings']);
        $this->assertEquals(50.0, $settlement['totals']['three_digit_commission']);
        $this->assertEquals(-499550.0, $settlement['totals']['three_digit_business_net']);
    }

    private function createBusiness(): array
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
            'agent_code' => 'D3-01',
            'agent_name' => 'Three digit agent',
            'commission_2d_percent' => '0.00',
            'commission_3d_percent' => '10.00',
        ]);
        $round = Round::query()->create([
            'admin_id' => $admin->id,
            'round_date' => '2026-10-07',
            'round_no' => 1,
            'start_time' => '07:00:00',
            'close_time' => '09:30:00',
            'number_limit' => 500,
            'number_limit_3d' => 100,
            'status' => 'open',
        ]);
        $session = AgentSession::openSession($agent, $round, $operator->id);

        return [$admin, $operator, $round, $agent, $session];
    }
}
