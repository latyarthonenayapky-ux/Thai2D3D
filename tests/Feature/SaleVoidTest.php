<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Agent;
use App\Models\AgentRoundSetting;
use App\Models\AgentSession;
use App\Models\HotNumber;
use App\Models\Round;
use App\Models\ThreeDigitSaleInput;
use App\Models\User;
use App\Services\InitializeAdminBusiness;
use App\Services\RoundSettlementCalculator;
use App\Services\SaleProcessor;
use Database\Seeders\CodeRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleVoidTest extends TestCase
{
    use RefreshDatabase;

    public function test_voiding_a_saved_sale_removes_it_from_totals_the_ledger_and_settlement(): void
    {
        [, $operator, $round, $agent] = $this->business();
        $session = AgentSession::openSession($agent, $round, $operator->id);
        $sale = app(SaleProcessor::class)->process('121500', $session);

        $this->actingAs($operator)
            ->post(route('operator.sales.void', [$session, $sale]), ['reason' => 'typed the wrong amount'])
            ->assertRedirect(route('operator.sales.show', [$session, 'mode' => '2d']))
            ->assertSessionHas('status');

        $this->assertSame('voided', $sale->fresh()->status);
        $this->assertSame(0, $sale->details()->where('status', 'accepted')->count());
        $this->assertSame(1, $sale->details()->where('status', 'voided')->count());
        $this->assertDatabaseHas('sale_inputs', [
            'id' => $sale->id,
            'voided_by' => $operator->id,
            'void_reason' => 'typed the wrong amount',
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'sale.void', 'subject_id' => $sale->id]);

        $this->actingAs($operator)
            ->get(route('operator.sales.show', [$session, 'mode' => '2d']))
            ->assertOk()
            ->assertSee('typed the wrong amount')
            ->assertViewHas('acceptedAmount', 0.0)
            ->assertViewHas('acceptedCount', 0)
            ->assertViewHas('soldItems', [])
            ->assertViewHas('ownSettlement', fn (array $row): bool => (float) $row['total_stake'] === 0.0);

        $this->assertSame(0.0, app(RoundSettlementCalculator::class)->calculate($round)['totals']['total_stake']);
    }

    public function test_a_void_needs_a_reason_and_leaves_the_sale_untouched_without_one(): void
    {
        [, $operator, $round, $agent] = $this->business();
        $session = AgentSession::openSession($agent, $round, $operator->id);
        $sale = app(SaleProcessor::class)->process('121500', $session);

        $this->actingAs($operator)
            ->from(route('operator.sales.show', [$session, 'mode' => '2d']))
            ->post(route('operator.sales.void', [$session, $sale]), ['reason' => 'ab'])
            ->assertRedirect(route('operator.sales.show', [$session, 'mode' => '2d']))
            ->assertSessionHasErrors('reason');

        $this->assertSame('accepted', $sale->fresh()->status);
        $this->assertSame(1, $sale->details()->where('status', 'accepted')->count());
    }

    public function test_restoring_puts_every_number_back_to_the_status_it_had_before_the_void(): void
    {
        [, $operator, $round, $agent] = $this->business();
        $session = AgentSession::openSession($agent, $round, $operator->id);
        $accepted = app(SaleProcessor::class)->process('121500', $session);
        $rejected = app(SaleProcessor::class)->process('071000', $session);
        $before = [
            $accepted->id => [$accepted->status, $accepted->details()->pluck('status', 'id')->all()],
            $rejected->id => [$rejected->status, $rejected->details()->pluck('status', 'id')->all()],
        ];

        foreach ([$accepted, $rejected] as $sale) {
            $this->actingAs($operator)
                ->post(route('operator.sales.void', [$session, $sale]), ['reason' => 'checking the void flow'])
                ->assertRedirect();
        }
        $this->assertSame(0, $accepted->details()->where('status', 'accepted')->count());

        foreach ([$accepted, $rejected] as $sale) {
            $this->post(route('operator.sales.restore', [$session, $sale]))
                ->assertRedirect(route('operator.sales.show', [$session, 'mode' => '2d']));
        }

        $this->assertSame($before[$accepted->id][0], $accepted->fresh()->status);
        $this->assertSame($before[$rejected->id][0], $rejected->fresh()->status);
        $this->assertSame($before[$accepted->id][1], $accepted->details()->pluck('status', 'id')->all());
        $this->assertSame($before[$rejected->id][1], $rejected->details()->pluck('status', 'id')->all());
        $this->assertDatabaseHas('sale_inputs', [
            'id' => $accepted->id,
            'voided_by' => null,
            'void_reason' => null,
            'pre_void_status' => null,
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'sale.restore', 'subject_id' => $accepted->id]);
    }

    public function test_another_operator_of_the_same_business_cannot_void_or_restore(): void
    {
        [$admin, $operator, $round, $agent] = $this->business();
        $intruder = User::factory()->create([
            'role' => 'operator',
            'admin_id' => $admin->id,
            'status' => true,
        ]);
        $intruderSession = AgentSession::openSession($agent, $round, $operator->id);
        $sale = app(SaleProcessor::class)->process('121500', $intruderSession);

        $this->actingAs($intruder)
            ->post(route('operator.sales.void', [$intruderSession, $sale]), ['reason' => 'not mine to touch'])
            ->assertForbidden();
        $this->assertSame('accepted', $sale->fresh()->status);

        $this->voidAs($operator, $intruderSession, $sale);
        $this->actingAs($intruder)
            ->post(route('operator.sales.restore', [$intruderSession, $sale]))
            ->assertForbidden();
        $this->assertSame('voided', $sale->fresh()->status);

        $this->actingAs($admin)
            ->post(route('operator.sales.restore', [$intruderSession, $sale]))
            ->assertRedirect();
        $this->assertSame('accepted', $sale->fresh()->status);
    }

    public function test_a_closed_round_stops_operators_from_correcting_sales_but_not_the_admin(): void
    {
        [$admin, $operator, $round, $agent] = $this->business();
        $session = AgentSession::openSession($agent, $round, $operator->id);
        $first = app(SaleProcessor::class)->process('121500', $session);
        $second = app(SaleProcessor::class)->process('131500', $session);
        $round->forceFill(['status' => 'closed'])->save();

        $this->actingAs($operator)
            ->post(route('operator.sales.void', [$session, $first]), ['reason' => 'round already closed'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('operator.sales.void', [$session, $second]), ['reason' => 'admin correction'])
            ->assertRedirect();
        $this->assertSame('voided', $second->fresh()->status);
        $this->assertSame('accepted', $first->fresh()->status);
    }

    public function test_a_voided_three_digit_sale_leaves_the_three_digit_totals(): void
    {
        [, $operator, $round, $agent] = $this->business();
        $round->update(['number_limit_3d' => 9]);
        AgentRoundSetting::query()
            ->where('agent_id', $agent->id)
            ->where('round_id', $round->id)
            ->update(['amount_limit_3d' => '1000.00']);
        $session = AgentSession::openSession($agent, $round, $operator->id);
        $this->actingAs($operator)
            ->post(route('operator.sales.store', $session), ['sale_type' => '3d', 'input' => '123500'])
            ->assertRedirect(route('operator.sales.show', [$session, 'mode' => '3d']));

        $sale = ThreeDigitSaleInput::query()->where('agent_session_id', $session->id)->firstOrFail();
        $this->assertSame(500.0, (float) $sale->details()->where('status', 'accepted')->sum('amount'));

        $this->actingAs($operator)
            ->post(route('operator.three-digit-sales.void', [$session, $sale]), ['reason' => 'customer cancelled'])
            ->assertRedirect(route('operator.sales.show', [$session, 'mode' => '3d']));

        $this->assertSame('voided', $sale->fresh()->status);
        $this->assertSame(0, $sale->details()->where('status', 'accepted')->count());
        $this->assertSame(0.0, app(RoundSettlementCalculator::class)->calculate($round)['totals']['three_digit_total_stake']);

        $this->actingAs($operator)
            ->post(route('operator.three-digit-sales.restore', [$session, $sale]))
            ->assertRedirect();
        $this->assertSame(500.0, (float) $sale->fresh()->details()->where('status', 'accepted')->sum('amount'));
    }

    private function voidAs(User $actor, AgentSession $session, mixed $sale): void
    {
        $this->actingAs($actor)
            ->post(route('operator.sales.void', [$session, $sale]), ['reason' => 'mistake on my side'])
            ->assertRedirect();
    }

    /**
     * @return array{User, User, Round, Agent}
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
        $agent = Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'A1',
            'agent_name' => 'First Agent',
        ]);
        $round = Round::query()->create([
            'admin_id' => $admin->id,
            'round_date' => now('Asia/Yangon')->toDateString(),
            'round_no' => 1,
            'start_time' => '07:00:00',
            'close_time' => '23:59:59',
            'status' => 'open',
        ]);
        AgentRoundSetting::query()->create([
            'agent_id' => $agent->id,
            'round_id' => $round->id,
            'operator_id' => $operator->id,
            'amount_limit' => '1000.00',
        ]);
        HotNumber::query()->create(['round_id' => $round->id, 'number' => '07']);

        return [$admin, $operator, $round, $agent];
    }
}
