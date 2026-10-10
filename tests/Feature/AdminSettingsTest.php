<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentRoundSetting;
use App\Models\AgentSession;
use App\Models\CodeRule;
use App\Models\HotNumber;
use App\Models\Round;
use App\Models\RoundSchedule;
use App\Models\User;
use App\Services\InitializeAdminBusiness;
use App\Services\SaleInputParser;
use Carbon\Carbon;
use Database\Seeders\CodeRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    private ?User $admin = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CodeRuleSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_only_admins_and_owners_can_access_settings(): void
    {
        $operator = User::factory()->create(['role' => 'operator', 'status' => true]);

        $this->actingAs($operator)
            ->get(route('admin.settings.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_the_settings_page(): void
    {
        $admin = $this->businessAdmin();

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Daily Round schedule')
            ->assertSee('Hot Numbers')
            ->assertSee('Configured number rules')
            ->assertSee('Blocked 2D values')
            ->assertDontSee('name="schedules[1][number_limit]"', false);

        $this->assertSame(0, Round::query()->count());
        $this->assertNull(RoundSchedule::query()->where('round_no', 1)->value('start_time'));
    }

    public function test_admin_can_create_multiple_agents_for_operator_coverage(): void
    {
        $admin = $this->businessAdmin();
        $this->actingAs($admin);

        $this->post(route('admin.settings.agents.store'), [
            'agent_code' => 'AG-001',
            'agent_name' => 'Agent One',
            'commission_2d_percent' => '2.50',
            'commission_3d_percent' => '3.75',
        ])->assertRedirect();
        $agent = Agent::query()->where('agent_code', 'AG-001')->firstOrFail();
        $this->assertSame('2.50', $agent->commission_2d_percent);
        $this->assertSame('3.75', $agent->commission_3d_percent);

        $this->patch(route('admin.settings.agents.update', $agent), [
            'agent_code' => $agent->agent_code,
            'agent_name' => $agent->agent_name,
            'phone' => null,
            'commission_2d_percent' => '4.25',
            'commission_3d_percent' => '5.50',
            'status' => 'active',
        ])->assertRedirect();
        $this->assertSame('4.25', $agent->fresh()->commission_2d_percent);
        $this->assertSame('5.50', $agent->fresh()->commission_3d_percent);

        $this->post(route('admin.settings.agents.store'), [
            'agent_code' => 'AG-002',
            'agent_name' => 'Agent Two',
            'commission_2d_percent' => '0',
            'commission_3d_percent' => '0',
        ])->assertRedirect();
        $this->assertSame(2, Agent::query()->where('admin_id', $admin->id)->count());
    }

    public function test_first_operator_claims_an_agent_per_round_and_can_claim_multiple_agents(): void
    {
        $admin = $this->businessAdmin();
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
            'agent_name' => 'Agent A1',
        ]);
        $agentFour = Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'A4',
            'agent_name' => 'Agent A4',
        ]);
        $roundOne = $this->createRound(1, 'open');
        $roundTwo = $this->createRound(2, 'open');

        $firstSession = AgentSession::openSession($agentOne, $roundOne, $firstOperator->id);
        AgentSession::openSession($agentFour, $roundOne, $firstOperator->id);

        foreach ([$agentOne, $agentFour] as $agent) {
            try {
                AgentSession::openSession($agent, $roundOne, $secondOperator->id);
                $this->fail('A second Operator must not take an Agent already claimed in this Round.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('already been claimed', $exception->getMessage());
            }
        }

        AgentSession::openSession($agentOne, $roundTwo, $secondOperator->id);
        AgentSession::openSession($agentFour, $roundTwo, $secondOperator->id);

        $firstSession->close();
        $reopenedSession = AgentSession::openSession($agentOne, $roundOne, $firstOperator->id);
        $this->assertSame($firstSession->id, $reopenedSession->id);

        $this->assertSame(
            $firstOperator->id,
            AgentRoundSetting::query()->where('agent_id', $agentOne->id)->where('round_id', $roundOne->id)->value('operator_id')
        );
        $this->assertSame(
            $secondOperator->id,
            AgentRoundSetting::query()->where('agent_id', $agentOne->id)->where('round_id', $roundTwo->id)->value('operator_id')
        );
    }

    public function test_admin_can_set_per_agent_round_amount_limits_but_not_for_another_business(): void
    {
        $admin = $this->businessAdmin();
        $operator = User::factory()->create([
            'role' => 'operator',
            'admin_id' => $admin->id,
            'status' => true,
        ]);
        $agent = Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'LIMIT-01',
            'agent_name' => 'Limit Agent',
        ]);
        $round = $this->createRound(1);

        $otherAdmin = User::factory()->create(['role' => 'admin', 'status' => true]);
        app(InitializeAdminBusiness::class)->initialize($otherAdmin);
        $otherRound = Round::query()->create([
            'admin_id' => $otherAdmin->id,
            'round_date' => '2026-10-08',
            'round_no' => 1,
            'start_time' => '07:00:00',
            'close_time' => '09:30:00',
            'number_limit' => 500,
            'status' => 'closed',
        ]);
        $otherOperator = User::factory()->create([
            'role' => 'operator',
            'admin_id' => $otherAdmin->id,
            'status' => true,
        ]);
        $otherAgent = Agent::query()->create([
            'admin_id' => $otherAdmin->id,
            'agent_code' => 'LIMIT-02',
            'agent_name' => 'Other Business Agent',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.settings.index', ['round_id' => $round->id]))
            ->patch(route('admin.settings.rounds.agents.amount-limit', [$round, $agent]), [
                'amount_limit' => '2500.50',
            ])
            ->assertRedirect(route('admin.settings.index', ['round_id' => $round->id]));

        $this->assertSame(
            '2500.50',
            AgentRoundSetting::query()->where('agent_id', $agent->id)->where('round_id', $round->id)->value('amount_limit')
        );

        $this->patch(route('admin.settings.rounds.agents.amount-limit', [$otherRound, $agent]), [
            'amount_limit' => '100',
        ])->assertNotFound();
        $this->patch(route('admin.settings.rounds.agents.amount-limit', [$round, $otherAgent]), [
            'amount_limit' => '100',
        ])->assertNotFound();

        $this->patch(route('admin.settings.rounds.agents.amount-limit', [$round, $agent]), [
            'amount_limit' => '',
        ])->assertRedirect();
        $this->assertDatabaseMissing('agent_round_settings', [
            'agent_id' => $agent->id,
            'round_id' => $round->id,
        ]);
    }

    public function test_agent_session_cannot_open_for_a_closed_round(): void
    {
        $operator = User::factory()->create(['role' => 'operator', 'status' => true]);
        $agent = Agent::query()->create([
            'admin_id' => $this->businessAdmin()->id,
            'agent_code' => 'CLOSED-01',
            'agent_name' => 'Closed Round Agent',
        ]);
        $round = $this->createRound(1, 'closed');

        try {
            AgentSession::openSession($agent, $round, $operator->id);
            $this->fail('Expected closed rounds to prevent session creation.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Round 1 is not open.', $exception->getMessage());
        }

        $this->assertSame(0, AgentSession::query()->count());
    }

    public function test_schedule_updates_unopened_rounds_but_preserves_opened_rounds(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-08 08:00:00', 'Asia/Yangon'));
        $admin = $this->businessAdmin();
        $opened = $this->createRound(1, 'open', Carbon::now('Asia/Yangon'));
        $opened->update(['start_time' => '06:00:00', 'close_time' => '08:30:00']);
        $unopened = $this->createRound(2, 'closed');
        $unopened->update(['number_limits' => ['00', '77']]);

        $this->actingAs($admin)
            ->put(route('admin.settings.schedule'), $this->schedulePayload())
            ->assertRedirect(route('admin.settings.index'));

        $this->assertSame('06:00:00', $opened->fresh()->start_time);
        $this->assertSame('08:30:00', $opened->fresh()->close_time);
        $this->assertSame(500, $opened->fresh()->number_limit);
        $this->assertSame('11:00:00', $unopened->fresh()->start_time);
        $this->assertSame(500, $unopened->fresh()->number_limit);
        $this->assertSame(['00', '77'], $unopened->fresh()->number_limits);
        $this->assertSame(
            '18:00:00',
            Round::query()->whereDate('round_date', '2026-10-09')->where('round_no', 3)->value('close_time')
        );
    }

    public function test_round_schedule_rejects_overlapping_times(): void
    {
        $admin = $this->businessAdmin();
        $payload = $this->schedulePayload();
        $payload['schedules'][2]['start_time'] = '10:59:59';

        $this->actingAs($admin)
            ->from(route('admin.settings.index'))
            ->put(route('admin.settings.schedule'), $payload)
            ->assertSessionHasErrors('schedules.2.start_time');
    }

    public function test_admin_can_save_a_round_specific_2d_number_blocklist(): void
    {
        $admin = $this->businessAdmin();
        $round = $this->createRound(1);

        $this->actingAs($admin)
            ->patch(route('admin.settings.rounds.number-limits', $round), [
                'numbers' => "00, 17\n28 17",
            ])
            ->assertRedirect();
        $this->assertSame(['00', '17', '28'], $round->fresh()->number_limits);

        $this->patch(route('admin.settings.rounds.number-limits', $round), [
            'numbers' => '00, 5, 100',
        ])->assertSessionHasErrors('numbers');
        $this->assertSame(['00', '17', '28'], $round->fresh()->number_limits);
    }

    public function test_hot_numbers_are_bulk_added_deduplicated_and_scoped_to_a_round(): void
    {
        $admin = $this->businessAdmin();
        $round = $this->createRound(1);
        $otherRound = $this->createRound(2);
        HotNumber::query()->create(['round_id' => $round->id, 'number' => '01']);

        $this->actingAs($admin)
            ->from(route('admin.settings.index', ['round_id' => $round->id]))
            ->post(route('admin.settings.hot-numbers.store', $round), [
                'numbers' => "01, 02\n02 03",
            ])
            ->assertRedirect(route('admin.settings.index', ['round_id' => $round->id]));

        $this->assertSame(3, HotNumber::query()->where('round_id', $round->id)->count());
        $hotNumber = HotNumber::query()->where('round_id', $round->id)->where('number', '02')->firstOrFail();

        $this->delete(route('admin.settings.hot-numbers.destroy', [$otherRound, $hotNumber]))
            ->assertNotFound();
        $this->assertDatabaseHas('hot_numbers', ['id' => $hotNumber->id]);
    }

    public function test_admin_can_edit_only_configured_w_n_x_lists_and_active_state(): void
    {
        $admin = $this->businessAdmin();
        $rule = CodeRule::query()->where('admin_id', $admin->id)->where('code', 'W')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.settings.rules.update', $rule), [
                'numbers' => "00, 11\n22 22",
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertSame(['00', '11', '22'], $rule->fresh()->rule_config['numbers']);

        $fixedRule = CodeRule::query()->where('code', 'A')->firstOrFail();
        $this->patch(route('admin.settings.rules.update', $fixedRule), [
            'numbers' => '00',
            'is_active' => '1',
        ])->assertNotFound();
    }

    public function test_admin_can_create_custom_parser_code_and_use_it_in_sale_inputs_and_exclusions(): void
    {
        $admin = $this->businessAdmin();

        $this->actingAs($admin)
            ->post(route('admin.settings.rules.custom.store'), [
                'code' => 'Q',
                'name' => 'Quick list',
                'numbers' => "11, 22\n22 33",
            ])
            ->assertRedirect();

        $rule = CodeRule::query()->where('code', 'Q')->firstOrFail();
        $this->assertSame(['11', '22', '33'], $rule->rule_config['numbers']);

        $parsed = app(SaleInputParser::class)->parse('Q1000', $admin->id);
        $this->assertSame(['11', '22', '33'], $parsed['numbers']);
        $this->assertSame(['11', '22', '33'], $parsed['generated_numbers']);

        $excluded = app(SaleInputParser::class)->parse('A[Q]1000', $admin->id);
        $this->assertSame(['11', '22', '33'], $excluded['excluded_numbers']);
        $this->assertSame(['00', '44', '55', '66', '77', '88', '99'], $excluded['numbers']);
    }

    public function test_reserved_and_invalid_custom_parser_codes_are_rejected(): void
    {
        $admin = $this->businessAdmin();
        $this->actingAs($admin);

        $this->from(route('admin.settings.index'))
            ->post(route('admin.settings.rules.custom.store'), [
                'code' => 'A',
                'name' => 'Reserved',
                'numbers' => '11,22',
            ])
            ->assertSessionHasErrors('code');

        $this->post(route('admin.settings.rules.custom.store'), [
            'code' => 'QQ',
            'name' => 'Too long',
            'numbers' => '11,22',
        ])->assertSessionHasErrors('code');
    }

    public function test_custom_parser_codes_are_isolated_between_admin_businesses(): void
    {
        $firstAdmin = $this->businessAdmin();
        $secondAdmin = User::factory()->create(['role' => 'admin', 'status' => true]);
        app(InitializeAdminBusiness::class)->initialize($secondAdmin);

        foreach ([$firstAdmin, $secondAdmin] as $admin) {
            $this->actingAs($admin)
                ->post(route('admin.settings.rules.custom.store'), [
                    'code' => 'Q',
                    'name' => 'Business '.$admin->id,
                    'numbers' => $admin->id === $firstAdmin->id ? '11,22' : '33,44',
                ])
                ->assertRedirect();
        }

        $parser = app(SaleInputParser::class);
        $this->assertSame(['11', '22'], $parser->parse('Q1000', $firstAdmin->id)['numbers']);
        $this->assertSame(['33', '44'], $parser->parse('Q1000', $secondAdmin->id)['numbers']);
    }

    public function test_scheduler_opens_and_closes_rounds_and_their_sessions(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-08 07:00:00', 'Asia/Yangon'));
        $this->configureRoundSchedule();
        $operator = User::factory()->create(['role' => 'operator', 'status' => true]);
        $agent = Agent::query()->create([
            'admin_id' => $this->businessAdmin()->id,
            'agent_code' => 'AUTO-01',
            'agent_name' => 'Auto',
        ]);

        $this->artisan('rounds:sync-schedule')->assertSuccessful();
        $round = Round::query()->whereDate('round_date', '2026-10-08')->where('round_no', 1)->firstOrFail();
        $this->assertSame('open', $round->status);
        $session = AgentSession::query()->create([
            'round_id' => $round->id,
            'agent_id' => $agent->id,
            'operator_id' => $operator->id,
            'session_code' => 'AUTO-SESSION',
            'status' => 'open',
            'opened_at' => now('Asia/Yangon'),
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-08 09:30:00', 'Asia/Yangon'));
        $this->artisan('rounds:sync-schedule')->assertSuccessful();

        $this->assertSame('closed', $round->fresh()->status);
        $this->assertSame('closed', $session->fresh()->status);
    }

    public function test_manual_reopen_persists_after_close_time_until_a_later_round_opens(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-08 15:00:00', 'Asia/Yangon'));
        $this->configureRoundSchedule();
        $admin = $this->businessAdmin();
        $round = $this->createRound(1, 'closed', Carbon::parse('2026-10-08 07:00:00', 'Asia/Yangon'));
        $nextRound = $this->createRound(3, 'open', Carbon::parse('2026-10-08 12:30:01', 'Asia/Yangon'));
        $nextRound->update([
            'start_time' => '12:30:01',
            'close_time' => '16:30:00',
        ]);
        $operator = User::factory()->create(['role' => 'operator', 'status' => true]);
        $agent = Agent::query()->create([
            'admin_id' => $this->businessAdmin()->id,
            'agent_code' => 'MANUAL-01',
            'agent_name' => 'Manual',
        ]);
        $session = AgentSession::query()->create([
            'round_id' => $round->id,
            'agent_id' => $agent->id,
            'operator_id' => $operator->id,
            'session_code' => 'MANUAL-SESSION',
            'status' => 'closed',
            'opened_at' => Carbon::parse('2026-10-08 07:00:00', 'Asia/Yangon'),
            'closed_at' => Carbon::parse('2026-10-08 09:30:00', 'Asia/Yangon'),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.settings.rounds.reopen', $round))
            ->assertRedirect();
        $this->assertSame('open', $session->fresh()->status);
        $this->assertTrue($round->fresh()->manual_reopen);

        $this->artisan('rounds:sync-schedule')->assertSuccessful();
        $this->assertSame('open', $round->fresh()->status);

        Carbon::setTestNow(Carbon::parse('2026-10-09 07:00:00', 'Asia/Yangon'));
        $this->artisan('rounds:sync-schedule')->assertSuccessful();
        $this->assertSame('closed', $round->fresh()->status);
        $this->assertSame('closed', $session->fresh()->status);
    }

    public function test_admin_can_close_a_reopened_round_and_scheduler_keeps_it_closed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-08 08:00:00', 'Asia/Yangon'));
        $this->configureRoundSchedule();
        $admin = $this->businessAdmin();
        $round = $this->createRound(1, 'open', Carbon::now('Asia/Yangon'));
        $operator = User::factory()->create(['role' => 'operator', 'admin_id' => $admin->id, 'status' => true]);
        $agent = Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'CLOSE-01',
            'agent_name' => 'Manual close',
        ]);
        $session = AgentSession::query()->create([
            'round_id' => $round->id,
            'agent_id' => $agent->id,
            'operator_id' => $operator->id,
            'session_code' => 'CLOSE-SESSION',
            'status' => 'open',
            'opened_at' => Carbon::now('Asia/Yangon'),
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.settings.rounds.close', $round))
            ->assertRedirect();
        $this->assertSame('closed', $round->fresh()->status);
        $this->assertTrue($round->fresh()->manually_closed);
        $this->assertSame('closed', $session->fresh()->status);

        Carbon::setTestNow(Carbon::parse('2026-10-08 08:05:00', 'Asia/Yangon'));
        $this->artisan('rounds:sync-schedule')->assertSuccessful();
        $this->assertSame('closed', $round->fresh()->status);
    }

    public function test_old_manually_reopened_round_stays_listed_and_can_be_reclosed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-17 15:00:00', 'Asia/Yangon'));
        $admin = $this->businessAdmin();
        $round = $this->createRound(1, 'open', Carbon::parse('2026-10-08 07:00:00', 'Asia/Yangon'));
        $round->update([
            'manual_reopen' => true,
            'manually_closed' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('2026-10-08')
            ->assertSee('Reopened Round · close it here when finished')
            ->assertSee('Reclose reopened Round');

        $this->patch(route('admin.settings.rounds.close', $round))
            ->assertRedirect();

        $this->assertSame('closed', $round->fresh()->status);
        $this->assertFalse($round->fresh()->manual_reopen);
        $this->assertTrue($round->fresh()->manually_closed);
    }

    public function test_schedule_accepts_blank_start_times(): void
    {
        $admin = $this->businessAdmin();
        $payload = $this->schedulePayload();
        $payload['schedules'][1]['start_time'] = null;
        $payload['schedules'][2]['start_time'] = null;
        $payload['schedules'][3]['start_time'] = null;

        $this->actingAs($admin)
            ->put(route('admin.settings.schedule'), $payload)
            ->assertRedirect(route('admin.settings.index'));

        $this->assertDatabaseHas('round_schedules', [
            'admin_id' => $admin->id,
            'round_no' => 2,
            'start_time' => null,
            'close_time' => '14:00:00',
        ]);
    }

    public function test_rounds_with_blank_start_open_sequentially_after_previous_closes(): void
    {
        $admin = $this->businessAdmin();
        foreach ([
            1 => [null, '09:30:00'],
            2 => [null, '12:30:00'],
            3 => [null, '16:30:00'],
        ] as $roundNo => [$startTime, $closeTime]) {
            RoundSchedule::query()
                ->where('admin_id', $admin->id)
                ->where('round_no', $roundNo)
                ->update([
                    'start_time' => $startTime,
                    'close_time' => $closeTime,
                    'number_limit' => 500,
                ]);
        }

        Carbon::setTestNow(Carbon::parse('2026-10-08 00:05:00', 'Asia/Yangon'));
        $this->artisan('rounds:sync-schedule')->assertSuccessful();
        $first = Round::query()->whereDate('round_date', '2026-10-08')->where('round_no', 1)->firstOrFail();
        $second = Round::query()->whereDate('round_date', '2026-10-08')->where('round_no', 2)->firstOrFail();
        $this->assertSame('open', $first->status);
        $this->assertSame('closed', $second->status);

        Carbon::setTestNow(Carbon::parse('2026-10-08 09:30:00', 'Asia/Yangon'));
        $this->artisan('rounds:sync-schedule')->assertSuccessful();
        $this->assertSame('closed', $first->fresh()->status);
        $this->assertSame('open', $second->fresh()->status);

        Carbon::setTestNow(Carbon::parse('2026-10-08 12:30:00', 'Asia/Yangon'));
        $this->artisan('rounds:sync-schedule')->assertSuccessful();
        $third = Round::query()->whereDate('round_date', '2026-10-08')->where('round_no', 3)->firstOrFail();
        $this->assertSame('closed', $second->fresh()->status);
        $this->assertSame('open', $third->fresh()->status);
    }

    private function createRound(int $roundNo, string $status = 'closed', ?Carbon $openedAt = null): Round
    {
        return Round::query()->create([
            'admin_id' => $this->businessAdmin()->id,
            'round_date' => '2026-10-08',
            'round_no' => $roundNo,
            'start_time' => '07:00:00',
            'close_time' => '09:30:00',
            'number_limit' => 500,
            'status' => $status,
            'opened_at' => $openedAt,
        ]);
    }

    private function schedulePayload(): array
    {
        return [
            'schedules' => [
                1 => ['start_time' => '07:00:00', 'close_time' => '11:00:00'],
                2 => ['start_time' => '11:00:00', 'close_time' => '14:00:00'],
                3 => ['start_time' => '14:00:00', 'close_time' => '18:00:00'],
            ],
        ];
    }

    private function configureRoundSchedule(): void
    {
        foreach ([
            1 => ['07:00:00', '09:30:00'],
            2 => ['09:30:01', '12:30:00'],
            3 => ['12:30:01', '16:30:00'],
        ] as $roundNo => [$startTime, $closeTime]) {
            RoundSchedule::query()
                ->where('admin_id', $this->businessAdmin()->id)
                ->where('round_no', $roundNo)
                ->update([
                    'start_time' => $startTime,
                    'close_time' => $closeTime,
                    'number_limit' => 500,
                ]);
        }
    }

    private function businessAdmin(): User
    {
        if (! $this->admin) {
            $this->admin = User::factory()->create(['role' => 'admin', 'status' => true]);
            app(InitializeAdminBusiness::class)->initialize($this->admin);
        }

        return $this->admin;
    }
}
