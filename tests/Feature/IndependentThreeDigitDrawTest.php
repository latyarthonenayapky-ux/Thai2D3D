<?php

namespace Tests\Feature;

use App\Models\AdminBusinessSetting;
use App\Models\Agent;
use App\Models\ThreeDigitDraw;
use App\Models\ThreeDigitDrawResultChange;
use App\Models\ThreeDigitSaleInput;
use App\Models\User;
use App\Services\InitializeAdminBusiness;
use App\Services\SyncThreeDigitDraws;
use App\Services\ThreeDigitDrawPeriodReport;
use App\Services\ThreeDigitDrawSettlementCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IndependentThreeDigitDrawTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_independent_draw_sales_claims_results_and_reports_are_draw_scoped(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 10:00:00', 'Asia/Yangon'));
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        app(InitializeAdminBusiness::class)->initialize($admin);
        AdminBusinessSetting::query()->where('admin_id', $admin->id)->update([
            'payout_3d_multiplier' => '1000.00',
        ]);
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
            'agent_code' => 'D3-01',
            'agent_name' => 'Draw Agent',
            'commission_3d_percent' => '10.00',
        ]);

        $drawSync = app(SyncThreeDigitDraws::class);
        $drawSync->syncForAdmin($admin, Carbon::now('Asia/Yangon'));
        $drawSync->syncForAdmin($admin, Carbon::now('Asia/Yangon'));
        $this->assertSame(8, ThreeDigitDraw::query()->where('admin_id', $admin->id)->count());

        $draw = ThreeDigitDraw::query()
            ->where('admin_id', $admin->id)
            ->whereDate('draw_date', '2026-10-16')
            ->firstOrFail();
        $this->actingAs($admin)
            ->put(route('admin.three-digit.draws.settings', $draw), ['number_limit' => 5])
            ->assertRedirect();
        $this->post(route('admin.three-digit.draws.hot-numbers.store', $draw), ['numbers' => '123'])
            ->assertRedirect();

        Carbon::setTestNow(Carbon::parse('2026-10-16 10:00:00', 'Asia/Yangon'));
        $drawSync->syncForAdmin($admin, Carbon::now('Asia/Yangon'));
        $draw->refresh();
        $this->assertSame('open', $draw->status);

        $this->actingAs($operator)
            ->post(route('three-digit.sales.claim', [$draw, $agent]))
            ->assertRedirect(route('three-digit.sales.workspace', ['agent_id' => $agent->id]));
        $this->actingAs($otherOperator)
            ->from(route('three-digit.sales.workspace'))
            ->post(route('three-digit.sales.claim', [$draw, $agent]))
            ->assertSessionHasErrors('agent');

        $saleId = (string) Str::uuid();
        $this->actingAs($operator)
            ->postJson(route('three-digit.sales.store', [$draw, $agent]), [
                'input' => '124500',
                'client_uuid' => $saleId,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'accepted');
        $this->postJson(route('three-digit.sales.store', [$draw, $agent]), [
            'input' => '124500',
            'client_uuid' => $saleId,
        ])->assertOk();
        $this->postJson(route('three-digit.sales.store', [$draw, $agent]), [
            'input' => '123500',
            'client_uuid' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('status', 'rejected');
        $this->assertDatabaseCount('three_digit_sale_inputs', 2);
        $this->assertDatabaseHas('three_digit_sale_inputs', [
            'three_digit_draw_id' => $draw->id,
            'agent_id' => $agent->id,
            'agent_session_id' => null,
            'client_uuid' => $saleId,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-16 15:30:00', 'Asia/Yangon'));
        $this->actingAs($admin)
            ->patch(route('admin.three-digit.results.update', $draw), ['result' => '124'])
            ->assertRedirect(route('admin.three-digit.results.show', $draw));
        $this->assertSame(1, ThreeDigitDrawResultChange::query()->count());

        $settlement = app(ThreeDigitDrawSettlementCalculator::class)->calculate($draw->fresh());
        $this->assertEquals(500.0, $settlement['totals']['total_stake']);
        $this->assertEquals(500.0, $settlement['totals']['winning_stake']);
        $this->assertEquals(500000.0, $settlement['totals']['winnings']);
        $this->assertEquals(50.0, $settlement['totals']['commission']);
        $this->assertEquals(-499550.0, $settlement['totals']['business_net']);

        $report = app(ThreeDigitDrawPeriodReport::class)->build($admin->id, 'daily', '2026-10-16');
        $this->assertSame(1, $report['draw_count']);
        $this->assertEquals(500.0, $report['totals']['total_stake']);
        $this->assertEquals(500000.0, $report['totals']['winnings']);
        $this->actingAs($admin)
            ->get(route('reports.summary', ['mode' => '3d', 'period' => 'daily', 'date' => '2026-10-16']))
            ->assertOk()
            ->assertSee('3D Agent totals')
            ->assertSee('500,000.00');

        $this->postJson(route('three-digit.sales.store', [$draw, $agent]), [
            'input' => '125500',
            'client_uuid' => (string) Str::uuid(),
        ])->assertUnprocessable();

        $this->assertSame(2, ThreeDigitSaleInput::query()->count());
    }
}
