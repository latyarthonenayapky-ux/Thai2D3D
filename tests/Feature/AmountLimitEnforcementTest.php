<?php

namespace Tests\Feature;

use App\Exceptions\AmountLimitExceededException;
use App\Models\Agent;
use App\Models\AgentRoundSetting;
use App\Models\AgentSession;
use App\Models\OfflineSyncReview;
use App\Models\OperatorRoundSetting;
use App\Models\Round;
use App\Models\SaleDetail;
use App\Models\SaleInput;
use App\Models\User;
use App\Services\AmountLimitPolicy;
use App\Services\InitializeAdminBusiness;
use App\Services\SaleProcessor;
use Database\Seeders\CodeRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AmountLimitEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_amount_limit_blocks_the_second_agent_of_the_same_operator(): void
    {
        [, $operator, $round] = $this->business();
        $first = $this->openAgentSession($round, $operator, 'OP-A');
        $second = $this->openAgentSession($round, $operator, 'OP-B');

        OperatorRoundSetting::query()->create([
            'round_id' => $round->id,
            'operator_id' => $operator->id,
            'amount_limit' => '2000.00',
            'amount_limit_mode' => 'block',
        ]);

        $this->assertSame('accepted', app(SaleProcessor::class)->process('121000', $first)->status);

        try {
            app(SaleProcessor::class)->process('131500', $second);
            $this->fail('Expected the Operator Amount Limit to block the second Agent.');
        } catch (AmountLimitExceededException $exception) {
            $this->assertSame('operator', $exception->tier);
            $this->assertSame(1000.0, $exception->used);
            $this->assertSame(1000.0, $exception->headroom());
        }

        $this->assertSame(1, SaleInput::query()->count());
        $this->assertSame(1, SaleDetail::query()->count());
    }

    public function test_warn_mode_keeps_accepting_past_the_limit_and_flags_the_round(): void
    {
        [, $operator, $round] = $this->business();
        $session = $this->openAgentSession($round, $operator, 'WARN-A');
        AgentRoundSetting::query()
            ->where('agent_id', $session->agent_id)
            ->where('round_id', $round->id)
            ->update(['amount_limit' => '1000.00']);

        $this->assertSame('accepted', app(SaleProcessor::class)->process('121000', $session)->status);
        $this->assertSame('accepted', app(SaleProcessor::class)->process('131000', $session)->status);
        $this->assertSame(2, SaleDetail::query()->count());

        $breaches = app(AmountLimitPolicy::class)->breaches($round, $session->agent_id, $session->operator_id, '2d');
        $this->assertCount(1, $breaches);
        $this->assertSame('agent', $breaches[0]['key']);
        $this->assertSame(1000.0, $breaches[0]['over']);

        $this->actingAs($operator)
            ->get(route('operator.sales.show', $session))
            ->assertOk()
            ->assertSee('ABOVE AMOUNT LIMIT')
            ->assertSee('data-limit-banner')
            ->assertSee('over-limit');
    }

    public function test_round_amount_limit_counts_every_agent_and_operator_together(): void
    {
        [$admin, $operator, $round] = $this->business();
        $secondOperator = User::factory()->create([
            'role' => 'operator',
            'admin_id' => $admin->id,
            'status' => true,
        ]);
        $first = $this->openAgentSession($round, $operator, 'ALL-A');
        $second = $this->openAgentSession($round, $secondOperator, 'ALL-B');

        $round->update(['amount_limit_all' => '3000.00', 'amount_limit_all_mode' => 'block']);

        $this->assertSame('accepted', app(SaleProcessor::class)->process('122000', $first)->status);

        try {
            app(SaleProcessor::class)->process('131500', $second);
            $this->fail('Expected the whole-Round Amount Limit to block the second Operator.');
        } catch (AmountLimitExceededException $exception) {
            $this->assertSame('round', $exception->tier);
            $this->assertSame(2000.0, $exception->used);
            $this->assertSame(1000.0, $exception->headroom());
        }

        $this->assertSame(1, SaleInput::query()->count());
    }

    public function test_a_voided_sale_gives_the_amount_limit_back(): void
    {
        [, $operator, $round] = $this->business();
        $session = $this->openAgentSession($round, $operator, 'VOID-CAP');
        AgentRoundSetting::query()
            ->where('agent_id', $session->agent_id)
            ->where('round_id', $round->id)
            ->update(['amount_limit' => '1000.00', 'amount_limit_mode' => 'block']);

        app(SaleProcessor::class)->process('121000', $session);

        $this->expectException(AmountLimitExceededException::class);
        app(SaleProcessor::class)->process('131000', $session);
    }

    public function test_voiding_a_sale_frees_the_headroom_for_the_next_one(): void
    {
        [, $operator, $round] = $this->business();
        $session = $this->openAgentSession($round, $operator, 'VOID-FREE');
        AgentRoundSetting::query()
            ->where('agent_id', $session->agent_id)
            ->where('round_id', $round->id)
            ->update(['amount_limit' => '1000.00', 'amount_limit_mode' => 'block']);

        $sale = app(SaleProcessor::class)->process('121000', $session);
        $this->actingAs($operator)
            ->post(route('operator.sales.void', [$session, $sale]), ['reason' => 'customer cancelled'])
            ->assertRedirect();

        $this->assertSame('accepted', app(SaleProcessor::class)->process('131000', $session)->status);
        $this->assertSame(1000.0, (float) SaleDetail::query()->where('status', 'accepted')->sum('amount'));
    }

    public function test_admin_saves_and_clears_operator_and_round_wide_limits(): void
    {
        [$admin, $operator, $round] = $this->business();

        $this->actingAs($admin)
            ->patch(route('admin.settings.rounds.operators.amount-limits', [$round, $operator]), [
                'amount_limit' => '15000',
                'amount_limit_3d' => '20000',
                'amount_limit_mode' => 'block',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('operator_round_settings', [
            'round_id' => $round->id,
            'operator_id' => $operator->id,
            'amount_limit' => '15000.00',
            'amount_limit_3d' => '20000.00',
            'amount_limit_mode' => 'block',
        ]);

        $this->patch(route('admin.settings.rounds.amount-limits', $round), [
            'amount_limit_all' => '90000',
            'amount_limit_all_mode' => 'block',
        ])->assertRedirect();
        $this->assertSame(90000.0, (float) $round->fresh()->amount_limit_all);
        $this->assertSame('block', $round->fresh()->amount_limit_all_mode);

        // Saving without a mode falls back to Warn, the default for every tier.
        $this->patch(route('admin.settings.rounds.amount-limits', $round), ['amount_limit_all' => '90000'])
            ->assertRedirect();
        $this->assertSame('warn', $round->fresh()->amount_limit_all_mode);

        $this->patch(route('admin.settings.rounds.operators.amount-limits', [$round, $operator]), [
            'amount_limit' => '',
            'amount_limit_3d' => '',
        ])->assertRedirect();
        $this->assertDatabaseMissing('operator_round_settings', [
            'round_id' => $round->id,
            'operator_id' => $operator->id,
        ]);

        $this->patch(route('admin.settings.rounds.amount-limits', $round), [
            'amount_limit_all' => '',
            'amount_limit_all_3d' => '',
        ])->assertRedirect();
        $this->assertNull($round->fresh()->amount_limit_all);
    }

    public function test_another_business_cannot_set_amount_limits(): void
    {
        [, $operator, $round] = $this->business();
        $stranger = User::factory()->create(['role' => 'admin', 'status' => true]);
        app(InitializeAdminBusiness::class)->initialize($stranger);

        $this->actingAs($stranger)
            ->patch(route('admin.settings.rounds.operators.amount-limits', [$round, $operator]), ['amount_limit' => '1'])
            ->assertNotFound();

        $this->patch(route('admin.settings.rounds.amount-limits', $round), ['amount_limit_all' => '1'])
            ->assertNotFound();

        $this->assertSame(0, OperatorRoundSetting::query()->count());
    }

    public function test_a_blocked_offline_sale_above_the_limit_waits_for_admin_review(): void
    {
        [, $operator, $round] = $this->business();
        $session = $this->openAgentSession($round, $operator, 'OFFLINE-CAP');
        AgentRoundSetting::query()
            ->where('agent_id', $session->agent_id)
            ->where('round_id', $round->id)
            ->update(['amount_limit' => '500.00', 'amount_limit_mode' => 'block']);

        $record = [
            'client_uuid' => (string) Str::uuid(),
            'session_id' => $session->id,
            'sale_type' => '2d',
            'input' => '121000',
            'recorded_at' => now()->toIso8601String(),
        ];

        $this->actingAs($operator)
            ->postJson(route('operator.offline-sync.store'), ['records' => [$record]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'pending_review');

        $review = OfflineSyncReview::query()->firstOrFail();
        $this->assertStringContainsString('Amount Limit', $review->conflict_reason);
        $this->assertSame(0, SaleInput::query()->count());
    }

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

    private function openAgentSession(Round $round, User $operator, string $code): AgentSession
    {
        $agent = Agent::query()->create([
            'admin_id' => $round->admin_id,
            'agent_code' => $code,
            'agent_name' => $code,
        ]);

        return AgentSession::openSession($agent, $round, $operator->id);
    }
}
