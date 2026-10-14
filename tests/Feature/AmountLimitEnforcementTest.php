<?php

namespace Tests\Feature;

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

    public function test_a_number_cap_counts_every_agent_and_operator_of_the_round_together(): void
    {
        [$admin, $operator, $round] = $this->business();
        $secondOperator = User::factory()->create([
            'role' => 'operator',
            'admin_id' => $admin->id,
            'status' => true,
        ]);
        $first = $this->openAgentSession($round, $operator, 'ALL-A');
        $second = $this->openAgentSession($round, $secondOperator, 'ALL-B');
        $this->cap($round, '1500.00');

        $this->assertSame('accepted', app(SaleProcessor::class)->process('121000', $first)->status);
        $this->assertSame('accepted', app(SaleProcessor::class)->process('121000', $second)->status);
        $this->assertSame(2, SaleInput::query()->count());

        $breaches = app(AmountLimitPolicy::class)->numberBreaches($round);
        $this->assertCount(1, $breaches);
        $this->assertSame('12', $breaches[0]['number']);
        $this->assertSame(1500.0, $breaches[0]['limit']);
        $this->assertSame(2000.0, $breaches[0]['sold']);
        $this->assertSame(500.0, $breaches[0]['excess']);
    }

    public function test_sale_input_flags_the_over_cap_number_and_keeps_selling(): void
    {
        [, $operator, $round] = $this->business();
        $session = $this->openAgentSession($round, $operator, 'WARN-A');
        $this->cap($round, '1000.00');

        $this->assertSame('accepted', app(SaleProcessor::class)->process('121000', $session)->status);
        $this->assertSame('accepted', app(SaleProcessor::class)->process('121500', $session)->status);
        $this->assertSame('accepted', app(SaleProcessor::class)->process('131000', $session)->status);
        $this->assertSame(3, SaleDetail::query()->count());

        $this->actingAs($operator)
            ->get(route('operator.sales.show', [$session, 'mode' => '2d']))
            ->assertOk()
            ->assertSee('ABOVE AMOUNT LIMIT')
            ->assertSee('data-limit-banner')
            ->assertSee('over-limit')
            ->assertViewHas('overLimitRows', fn (array $rows): bool => count($rows) === 1
                && $rows[0]['number'] === '12'
                && (float) $rows[0]['limit'] === 1000.0
                && (float) $rows[0]['sold'] === 2500.0
                && (float) $rows[0]['excess'] === 1500.0)
            ->assertViewHas('numberAmountLimit', 1000.0);
    }

    public function test_a_voided_sale_stops_flagging_the_number(): void
    {
        [, $operator, $round] = $this->business();
        $session = $this->openAgentSession($round, $operator, 'VOID-CAP');
        $this->cap($round, '1000.00');

        $sale = app(SaleProcessor::class)->process('121500', $session);
        $this->assertCount(1, app(AmountLimitPolicy::class)->numberBreaches($round));

        $this->actingAs($operator)
            ->post(route('operator.sales.void', [$session, $sale]), ['reason' => 'customer cancelled'])
            ->assertRedirect();

        $this->assertSame([], app(AmountLimitPolicy::class)->numberBreaches($round));
        $this->assertSame('accepted', app(SaleProcessor::class)->process('121000', $session)->status);
    }

    public function test_the_retired_2d_scalar_tiers_never_block_or_flag_a_sale(): void
    {
        [, $operator, $round] = $this->business();
        $session = $this->openAgentSession($round, $operator, 'LEGACY');
        AgentRoundSetting::query()->updateOrCreate(
            ['agent_id' => $session->agent_id, 'round_id' => $round->id],
            ['amount_limit' => '100.00', 'amount_limit_mode' => 'block']
        );
        OperatorRoundSetting::query()->updateOrCreate(
            ['operator_id' => $operator->id, 'round_id' => $round->id],
            ['amount_limit' => '100.00', 'amount_limit_mode' => 'block']
        );
        $round->update(['amount_limit_all' => '100.00', 'amount_limit_all_mode' => 'block']);

        $this->assertSame('accepted', app(SaleProcessor::class)->process('121000', $session)->status);
        $this->assertSame([], app(AmountLimitPolicy::class)->breaches($round, $session->agent_id, $session->operator_id, '2d'));
    }

    public function test_one_round_cap_applies_to_every_number_and_cleared_it_stops_flagging(): void
    {
        [$admin, $operator, $round] = $this->business();
        $session = $this->openAgentSession($round, $operator, 'ONE-CAP');

        $this->actingAs($admin)
            ->patch(route('admin.settings.rounds.amount-limits', $round), ['amount_limit_all' => '1500'])
            ->assertRedirect();
        $this->assertSame(1500.0, app(AmountLimitPolicy::class)->twoDigitCap($round->fresh()));

        app(SaleProcessor::class)->process('123000', $session);
        app(SaleProcessor::class)->process('713000', $session);

        // One figure, so a second number is capped exactly like the first.
        $breaches = app(AmountLimitPolicy::class)->numberBreaches($round->fresh());
        $this->assertSame(['12', '71'], array_column($breaches, 'number'));
        $this->assertSame([1500.0, 1500.0], array_column($breaches, 'limit'));

        $this->patch(route('admin.settings.rounds.amount-limits', $round), ['amount_limit_all' => ''])
            ->assertRedirect();
        $this->assertNull($round->fresh()->amount_limit_all);
        $this->assertSame([], app(AmountLimitPolicy::class)->numberBreaches($round->fresh()));

        $stranger = User::factory()->create(['role' => 'admin', 'status' => true]);
        app(InitializeAdminBusiness::class)->initialize($stranger);
        $this->actingAs($stranger)
            ->patch(route('admin.settings.rounds.amount-limits', $round), ['amount_limit_all' => '1'])
            ->assertNotFound();
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

    public function test_an_offline_sale_above_a_number_cap_syncs_and_is_flagged(): void
    {
        [, $operator, $round] = $this->business();
        $session = $this->openAgentSession($round, $operator, 'OFFLINE-CAP');
        $this->cap($round, '500.00');

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
            ->assertJsonPath('results.0.status', 'synced');

        $this->assertSame(0, OfflineSyncReview::query()->count());
        $this->assertSame('web', SaleInput::query()->value('source'));
        $breaches = app(AmountLimitPolicy::class)->numberBreaches($round);
        $this->assertCount(1, $breaches);
        $this->assertSame(500.0, $breaches[0]['excess']);
    }

    private function cap(Round $round, string $amount): void
    {
        // One Round-wide figure caps every number 00–99.
        $round->update(['amount_limit_all' => $amount]);
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
