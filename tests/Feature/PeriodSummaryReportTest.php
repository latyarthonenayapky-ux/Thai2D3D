<?php

namespace Tests\Feature;

use App\Models\AdminBusinessSetting;
use App\Models\Agent;
use App\Models\AgentRoundSetting;
use App\Models\AgentSession;
use App\Models\Round;
use App\Models\RoundResult;
use App\Models\ThreeDigitSaleDetail;
use App\Models\ThreeDigitSaleInput;
use App\Models\User;
use App\Services\InitializeAdminBusiness;
use App\Services\PeriodSummaryReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodSummaryReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_daily_weekly_monthly_yearly_and_agent_round_breakdowns(): void
    {
        [$admin, $firstOperator, $secondOperator, $agentOne, $agentTwo] = $this->createBusiness();
        AdminBusinessSetting::query()->where('admin_id', $admin->id)->update([
            'payout_2d_multiplier' => '5.00',
            'payout_3d_multiplier' => '1000.00',
        ]);
        $agentOne->update(['commission_3d_percent' => '10.00']);

        $mondayRound = $this->createClosedRound($admin, $agentOne, $firstOperator, '2026-10-05', 1, '12', 1);
        $this->enterSales($firstOperator, $mondayRound, ['121000']);
        RoundResult::query()->where('round_id', $mondayRound->id)->update(['result_3d' => '123']);
        $session = AgentSession::query()->where('round_id', $mondayRound->id)->firstOrFail();
        $threeDigitSale = ThreeDigitSaleInput::query()->create([
            'agent_session_id' => $session->id,
            'operator_id' => $firstOperator->id,
            'original_input' => '123500',
            'normalized_input' => '123500',
            'input_type' => 'NORMAL',
            'code' => 'NORMAL',
            'number_count' => 1,
            'amount' => '500.00',
            'total_amount' => '500.00',
            'status' => 'accepted',
        ]);
        ThreeDigitSaleDetail::query()->create([
            'three_digit_sale_input_id' => $threeDigitSale->id,
            'number' => '123',
            'amount' => '500.00',
            'status' => 'accepted',
        ]);
        $this->createClosedRound($admin, $agentTwo, $secondOperator, '2026-10-05', 2, '12', 2);
        $tuesdayRound = $this->createClosedRound($admin, $agentOne, $secondOperator, '2026-10-07', 2, '12', 3);
        $this->enterSales($secondOperator, $tuesdayRound, ['121000', '121000']);
        $this->createClosedRound($admin, $agentOne, $firstOperator, '2026-09-30', 3, '12', 4);
        $this->enterSales($firstOperator, Round::query()->where('admin_id', $admin->id)->whereDate('round_date', '2026-09-30')->firstOrFail(), ['121000']);

        $weekly = app(PeriodSummaryReport::class)->build($admin->id, 'weekly', '2026-10-07');
        $this->assertSame('2026-10-05', $weekly['start_date']->toDateString());
        $this->assertSame('2026-10-11', $weekly['end_date']->toDateString());
        $this->assertEquals(3000.0, $weekly['totals']['total_stake']);
        $this->assertEquals(3000.0, $weekly['totals']['winning_stake']);
        $this->assertEquals(15000.0, $weekly['totals']['winnings']);
        $this->assertEquals(300.0, $weekly['totals']['commission']);
        $this->assertEquals(-12300.0, $weekly['totals']['business_net']);
        $this->assertEquals(500.0, $weekly['totals']['three_digit_total_stake']);
        $this->assertEquals(500.0, $weekly['totals']['three_digit_winning_stake']);
        $this->assertEquals(500000.0, $weekly['totals']['three_digit_winnings']);
        $this->assertEquals(50.0, $weekly['totals']['three_digit_commission']);
        $this->assertEquals(-499550.0, $weekly['totals']['three_digit_business_net']);
        $this->assertEquals(500.0, $weekly['agent_totals']->firstWhere('agent_id', $agentOne->id)['three_digit_total_stake']);
        $this->assertCount(3, $weekly['agent_rows']);
        $this->assertCount(2, $weekly['daily_rows']);

        $dailyResponse = $this->actingAs($admin)
            ->get(route('reports.summary', ['period' => 'daily', 'date' => '2026-10-05']));
        $dailyResponse
            ->assertOk()
            ->assertSee('Showing Daily · 2026-10-05 to 2026-10-05')
            ->assertSee('A1 · Alpha')
            ->assertSee('A2 · Beta')
            ->assertSee('Agent · day · Round details')
            ->assertSee('3D Draw summaries')
            ->assertDontSee('3D agent totals')
            ->assertDontSee('500,000.00');

        foreach (['weekly', 'monthly', 'yearly'] as $period) {
            $response = $this->get(route('reports.summary', [
                'period' => $period,
                'date' => '2026-10-07',
            ]));
            $response->assertOk()
                ->assertSee('A1 · Alpha')
                ->assertSee('A2 · Beta')
                ->assertSee('Round 2');
        }

        $this->get(route('reports.summary', [
            'period' => 'weekly',
            'date' => '2026-10-07',
            'agent_id' => $agentOne->id,
            'round_no' => 2,
        ]))
            ->assertOk()
            ->assertSee('Showing Weekly · 2026-10-05 to 2026-10-11')
            ->assertSee('A1 · Alpha')
            ->assertSee('2026-10-07')
            ->assertSee('2,000.00')
            ->assertSee('10,000.00');

        $this->get(route('reports.summary', [
            'period' => 'monthly',
            'date' => '2026-10-07',
            'agent_id' => $agentOne->id,
        ]))
            ->assertOk()
            ->assertSee('2,000.00')
            ->assertDontSee('2026-09-30');
    }

    public function test_an_agent_total_figure_links_to_its_own_days_and_rounds(): void
    {
        [$admin, $firstOperator, $secondOperator, $agentOne, $agentTwo] = $this->createBusiness();
        AdminBusinessSetting::query()->where('admin_id', $admin->id)->update(['payout_2d_multiplier' => '5.00']);
        $monday = $this->createClosedRound($admin, $agentOne, $firstOperator, '2026-10-05', 1, '12', 1);
        $this->enterSales($firstOperator, $monday, ['121000']);
        $tuesday = $this->createClosedRound($admin, $agentOne, $secondOperator, '2026-10-07', 2, '12', 3);
        $this->enterSales($secondOperator, $tuesday, ['121000', '121000']);
        $betaRound = $this->createClosedRound($admin, $agentTwo, $secondOperator, '2026-10-07', 1, '12', 2);
        $this->enterSales($secondOperator, $betaRound, ['341000']);

        $this->actingAs($admin)
            ->get(route('reports.summary', ['period' => 'weekly', 'date' => '2026-10-07']))
            ->assertOk()
            ->assertViewHas('selectedFigure', null)
            ->assertSee('figure=business_net', false)
            ->assertSee('#report-detail', false);

        $response = $this->get(route('reports.summary', [
            'period' => 'weekly',
            'date' => '2026-10-07',
            'agent_id' => $agentOne->id,
            'figure' => 'business_net',
        ]))
            ->assertOk()
            ->assertViewHas('selectedFigure', 'business_net')
            ->assertSee('Agent · day · Round details · 2D · Business net')
            ->assertSee('class="figure-focus"', false);

        $this->assertCount(2, $response->viewData('detailRows'));
    }

    public function test_operator_reports_include_only_agents_and_sales_assigned_to_that_operator(): void
    {
        [$admin, $firstOperator, $secondOperator, $agentOne, $agentTwo] = $this->createBusiness();
        AdminBusinessSetting::query()->where('admin_id', $admin->id)->update([
            'payout_2d_multiplier' => '5.00',
        ]);
        $firstRound = $this->createClosedRound($admin, $agentOne, $firstOperator, '2026-10-05', 1, '12', 1);
        $this->enterSales($firstOperator, $firstRound, ['121000']);
        $secondRound = $this->createClosedRound($admin, $agentTwo, $secondOperator, '2026-10-05', 2, '12', 2);
        $this->enterSales($secondOperator, $secondRound, ['341000']);

        $this->actingAs($firstOperator)
            ->get(route('reports.summary', ['period' => 'weekly', 'date' => '2026-10-07']))
            ->assertOk()
            ->assertSee('A1 · Alpha')
            ->assertDontSee('A2 · Beta')
            ->assertSee('1,000.00');

        $this->get(route('reports.summary', [
            'period' => 'daily',
            'date' => '2026-10-05',
            'agent_id' => $agentTwo->id,
        ]))->assertNotFound();

        $this->actingAs($admin)
            ->get(route('reports.summary', ['period' => 'weekly', 'date' => '2026-10-07']))
            ->assertOk()
            ->assertSee('A1 · Alpha')
            ->assertSee('A2 · Beta');
    }

    public function test_only_admins_and_operators_can_view_period_reports_and_filters_are_validated(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'status' => true]);

        $this->actingAs($owner)
            ->get(route('reports.summary'))
            ->assertForbidden();

        $this->get(route('reports.summary.export'))->assertForbidden();

        [$admin] = $this->createBusiness();
        $this->actingAs($admin)
            ->from(route('reports.summary'))
            ->get(route('reports.summary', [
                'period' => 'quarterly',
                'date' => '2026-10-07',
            ]))
            ->assertSessionHasErrors('period');

        $this->get(route('reports.summary', [
            'period' => 'daily',
            'date' => '2026-10-07',
            'round_no' => 4,
        ]))->assertSessionHasErrors('round_no');
    }

    public function test_summary_csv_export_streams_detail_rows_totals_and_currency_headers(): void
    {
        [$admin, $firstOperator, , $agentOne] = $this->createBusiness();
        AdminBusinessSetting::query()->where('admin_id', $admin->id)->update([
            'payout_2d_multiplier' => '5.00',
        ]);
        $agentOne->update(['commission_2d_percent' => '10.00']);
        $round = $this->createClosedRound($admin, $agentOne, $firstOperator, '2026-10-05', 1, '12', 1);
        $this->enterSales($firstOperator, $round, ['121000']);

        $response = $this->actingAs($admin)->get(route('reports.summary.export', [
            'mode' => '2d',
            'period' => 'weekly',
            'date' => '2026-10-07',
        ]));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertSame(
            'attachment; filename=thai2d3d-2d-weekly-2026-10-05-2026-10-11.csv',
            $response->headers->get('Content-Disposition')
        );

        $csv = (string) $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        $lines = preg_split('/\r\n|\n/', trim(preg_replace('/^\xEF\xBB\xBF/', '', $csv)));
        $this->assertCount(3, $lines);
        $header = str_getcsv($lines[0]);
        $this->assertSame('Agent code', $header[0]);
        $this->assertSame('Date', $header[2]);
        $this->assertSame('Accepted stakes (MMK)', $header[6]);
        $row = str_getcsv($lines[1]);
        $this->assertSame(['A1', 'Alpha', '2026-10-05', '1'], array_slice($row, 0, 4));
        $this->assertSame('12', $row[5]);
        $this->assertSame(['1000', '1000', '5000', '100', '-4100'], array_slice($row, 6));
        $total = str_getcsv($lines[2]);
        $this->assertSame('TOTAL', $total[0]);
        $this->assertSame(['1000', '1000', '5000', '100', '-4100'], array_slice($total, 6));
    }

    public function test_summary_csv_export_neutralises_spreadsheet_formulas_in_agent_names(): void
    {
        [$admin, $firstOperator, , $agentOne] = $this->createBusiness();
        $agentOne->update(['agent_name' => '=2+2']);
        $round = $this->createClosedRound($admin, $agentOne, $firstOperator, '2026-10-05', 1, '12', 1);
        $this->enterSales($firstOperator, $round, ['121000']);

        $csv = (string) $this->actingAs($admin)->get(route('reports.summary.export', [
            'mode' => '2d',
            'period' => 'daily',
            'date' => '2026-10-05',
        ]))->streamedContent();

        $this->assertStringContainsString("A1,'=2+2,", $csv);
        $this->assertStringNotContainsString('A1,=2+2,', $csv);
    }

    public function test_summary_details_table_is_paginated_at_fifty_rows(): void
    {
        [$admin, $firstOperator, , $agentOne] = $this->createBusiness();
        AdminBusinessSetting::query()->where('admin_id', $admin->id)->update([
            'payout_2d_multiplier' => '5.00',
        ]);
        $round = $this->createClosedRound($admin, $agentOne, $firstOperator, '2026-10-05', 1, '12', 1);
        $this->enterSales($firstOperator, $round, ['121000']);

        for ($index = 1; $index <= 50; $index++) {
            $agent = Agent::query()->create([
                'admin_id' => $admin->id,
                'agent_code' => 'P'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'agent_name' => 'Page Agent '.$index,
            ]);
            AgentRoundSetting::query()->create([
                'agent_id' => $agent->id,
                'round_id' => $round->id,
                'operator_id' => $firstOperator->id,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('reports.summary', ['period' => 'daily', 'date' => '2026-10-05']))
            ->assertOk()
            ->assertSee('Showing 1–50 of 51 rows')
            ->assertSee('Page 1 of 2')
            ->assertSee('page=2');

        $this->get(route('reports.summary', ['period' => 'daily', 'date' => '2026-10-05', 'page' => 2]))
            ->assertOk()
            ->assertSee('Showing 51–51 of 51 rows')
            ->assertSee('Page 2 of 2');
    }

    private function createBusiness(): array
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
            'agent_name' => 'Alpha',
            'commission_2d_percent' => '10.00',
        ]);
        $agentTwo = Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'A2',
            'agent_name' => 'Beta',
            'commission_2d_percent' => '5.00',
        ]);

        return [$admin, $firstOperator, $secondOperator, $agentOne, $agentTwo];
    }

    private function createClosedRound(
        User $admin,
        Agent $agent,
        User $operator,
        string $date,
        int $roundNo,
        string $result,
        int $code
    ): Round {
        $round = Round::query()->create([
            'admin_id' => $admin->id,
            'round_date' => $date,
            'round_no' => $roundNo,
            'start_time' => '07:00:00',
            'close_time' => '09:30:00',
            'number_limit' => 500,
            'status' => 'open',
        ]);
        AgentSession::openSession($agent, $round, $operator->id);
        $round->update(['status' => 'closed']);
        RoundResult::query()->create([
            'admin_id' => $admin->id,
            'round_id' => $round->id,
            'result_2d' => $result,
            'updated_by' => $admin->id,
        ]);

        return $round;
    }

    private function enterSales(User $operator, Round $round, array $inputs): void
    {
        $session = AgentSession::query()
            ->where('round_id', $round->id)
            ->where('operator_id', $operator->id)
            ->firstOrFail();
        $session->update(['status' => 'open']);

        $round->update(['status' => 'open', 'manual_reopen' => true]);
        $this->actingAs($operator);
        foreach ($inputs as $input) {
            $this->post(route('operator.sales.store', $session), ['input' => $input])->assertRedirect();
        }

        $session->update(['status' => 'closed']);
        $round->update(['status' => 'closed']);
    }
}
