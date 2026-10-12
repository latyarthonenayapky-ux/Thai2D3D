<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentAccessToken;
use App\Models\AgentRoundSetting;
use App\Models\AgentSession;
use App\Models\OfflineSyncReview;
use App\Models\Round;
use App\Models\SaleInput;
use App\Models\User;
use App\Services\InitializeAdminBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AgentMobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_signs_in_with_mobile_credentials_and_receives_a_token(): void
    {
        [, , , , $agent] = $this->createBusiness();

        $response = $this->postJson(route('api.v1.auth.login'), [
            'login_code' => 'wya-01',
            'password' => 's3cret-pin',
            'device_name' => 'Agent phone',
        ])->assertCreated()
            ->assertJsonPath('agent.agent_code', 'WYA-01')
            ->assertJsonStructure(['token', 'expires_at', 'agent', 'rounds']);

        $this->assertNotSame('s3cret-pin', $agent->fresh()->mobile_password);
        $this->assertSame(1, AgentAccessToken::query()->where('agent_id', $agent->id)->count());

        $token = $response->json('token');
        $this->assertStringNotContainsString($token, AgentAccessToken::query()->first()->token_hash);

        $this->withToken($token)
            ->getJson(route('api.v1.me'))
            ->assertOk()
            ->assertJsonPath('agent.agent_code', 'WYA-01')
            ->assertJsonPath('sessions.0.id', fn ($id) => (int) $id > 0);
    }

    public function test_sign_in_rejects_a_wrong_password_or_a_disabled_agent(): void
    {
        [, , , , $agent] = $this->createBusiness();

        $this->postJson(route('api.v1.auth.login'), [
            'login_code' => 'WYA-01',
            'password' => 'wrong-password',
        ])->assertUnauthorized();

        $agent->update(['status' => 'inactive']);

        $this->postJson(route('api.v1.auth.login'), [
            'login_code' => 'WYA-01',
            'password' => 's3cret-pin',
        ])->assertUnauthorized();
    }

    public function test_protected_endpoints_require_a_usable_token(): void
    {
        $this->getJson(route('api.v1.me'))->assertUnauthorized();
        $this->withToken('not-a-real-token')->getJson(route('api.v1.me'))->assertUnauthorized();

        [, , , , $agent] = $this->createBusiness();
        $token = $this->signIn($agent);

        $expired = AgentAccessToken::query()->firstOrFail();
        $expired->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->withToken($token)->getJson(route('api.v1.me'))->assertUnauthorized();
    }

    public function test_signing_out_revokes_only_that_device(): void
    {
        [, , , , $agent] = $this->createBusiness();
        $phone = $this->signIn($agent);
        $tablet = $this->postJson(route('api.v1.auth.login'), [
            'login_code' => 'WYA-01',
            'password' => 's3cret-pin',
            'device_name' => 'Tablet',
        ])->json('token');

        $this->withToken($phone)->postJson(route('api.v1.auth.logout'))->assertOk();

        $this->withToken($phone)->getJson(route('api.v1.me'))->assertUnauthorized();
        $this->withToken($tablet)->getJson(route('api.v1.me'))->assertOk();
    }

    public function test_entries_upload_immediately_and_retries_stay_idempotent(): void
    {
        [, , $round, $session, $agent] = $this->createBusiness();
        $token = $this->signIn($agent);
        $record = $this->record($session, '2d', '121000');

        $this->withToken($token)
            ->postJson(route('api.v1.sales.upload'), ['records' => [$record]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'synced');

        $this->withToken($token)
            ->postJson(route('api.v1.sales.upload'), ['records' => [$record]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'synced')
            ->assertJsonPath('results.0.message', 'Already synchronized.');

        $this->assertSame(1, SaleInput::query()->count());
        $this->assertDatabaseHas('sale_inputs', [
            'agent_session_id' => $session->id,
            'client_uuid' => $record['client_uuid'],
        ]);

        $this->withToken($token)
            ->getJson(route('api.v1.sessions.state', $session))
            ->assertOk()
            ->assertJsonPath('totals.entries_2d', 1)
            ->assertJsonPath('entries.0.input', '121000');
    }

    public function test_uploads_are_rejected_for_another_agents_session(): void
    {
        [$admin, $operator, $round, , $agent] = $this->createBusiness();
        $otherAgent = Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'WYA-02',
            'agent_name' => 'Other agent',
        ]);
        $otherSession = AgentSession::openSession($otherAgent, $round, $operator->id);
        $token = $this->signIn($agent);

        $this->withToken($token)
            ->postJson(route('api.v1.sales.upload'), ['records' => [$this->record($otherSession, '2d', '121000')]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'error');

        $this->assertSame(0, SaleInput::query()->count());
    }

    public function test_entries_recorded_after_the_round_closed_wait_for_admin_review(): void
    {
        [, , $round, $session, $agent] = $this->createBusiness();
        $round->update(['status' => 'closed']);
        $token = $this->signIn($agent);
        $record = $this->record($session, '2d', '121000');

        $this->withToken($token)
            ->postJson(route('api.v1.sales.upload'), ['records' => [$record]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'pending_review');

        $this->assertSame(0, SaleInput::query()->count());
        $this->assertSame(1, OfflineSyncReview::query()->where('client_uuid', $record['client_uuid'])->count());

        $this->withToken($token)
            ->getJson(route('api.v1.me'))
            ->assertOk()
            ->assertJsonPath('awaiting_review', 1);
    }

    public function test_unparsable_input_is_reported_as_invalid_without_saving(): void
    {
        [, , , $session, $agent] = $this->createBusiness();
        $token = $this->signIn($agent);

        $this->withToken($token)
            ->postJson(route('api.v1.sales.upload'), ['records' => [$this->record($session, '3d', 'a1000')]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'invalid');

        $this->assertSame(0, SaleInput::query()->count());
        $this->assertSame(0, OfflineSyncReview::query()->count());
    }

    public function test_the_app_opens_the_agents_session_for_the_assigned_round(): void
    {
        [$admin, $operator, $round] = $this->createBusiness();
        $freshAgent = Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'WYA-09',
            'agent_name' => 'Not opened yet',
            'mobile_login_code' => 'WYA-09',
            'mobile_password' => 's3cret-pin',
        ]);
        AgentRoundSetting::query()->create([
            'agent_id' => $freshAgent->id,
            'round_id' => $round->id,
            'operator_id' => $operator->id,
        ]);

        $this->withToken($this->signIn($freshAgent))
            ->postJson(route('api.v1.sessions.open'), ['round_id' => $round->id])
            ->assertCreated()
            ->assertJsonPath('session.agent_id', $freshAgent->id)
            ->assertJsonPath('reused', false);

        $session = AgentSession::query()->where('agent_id', $freshAgent->id)->sole();
        $this->assertSame($operator->id, $session->operator_id);

        $this->withToken($this->signIn($freshAgent))
            ->postJson(route('api.v1.sessions.open'), ['round_id' => $round->id])
            ->assertOk()
            ->assertJsonPath('reused', true);
    }

    public function test_opening_a_session_without_an_operator_assignment_is_refused(): void
    {
        [$admin, , $round] = $this->createBusiness();
        $unassigned = Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => 'WYA-77',
            'agent_name' => 'Unassigned',
            'mobile_login_code' => 'WYA-77',
            'mobile_password' => 's3cret-pin',
        ]);

        $this->withToken($this->signIn($unassigned))
            ->postJson(route('api.v1.sessions.open'), ['round_id' => $round->id])
            ->assertStatus(422);

        $this->assertSame(0, AgentSession::query()->where('agent_id', $unassigned->id)->count());
    }

    public function test_a_batch_backlog_is_uploaded_in_one_call(): void
    {
        [, , , $session, $agent] = $this->createBusiness();
        $token = $this->signIn($agent);

        $records = collect(['121000', '131000', '141000'])
            ->map(fn (string $input): array => $this->record($session, '2d', $input))
            ->all();

        $this->withToken($token)
            ->postJson(route('api.v1.sales.upload'), ['records' => $records])
            ->assertOk()
            ->assertJsonCount(3, 'results');

        $this->assertSame(3, SaleInput::query()->count());
        $this->assertDatabaseHas('sale_details', ['number' => '12', 'status' => 'accepted']);
        $this->assertDatabaseHas('sale_details', ['number' => '14', 'status' => 'accepted']);
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
            'agent_code' => 'WYA-01',
            'agent_name' => 'Mobile agent',
            'mobile_login_code' => 'WYA-01',
            'mobile_password' => 's3cret-pin',
        ]);
        $round = Round::query()->create([
            'admin_id' => $admin->id,
            'round_date' => '2026-10-13',
            'round_no' => 1,
            'start_time' => '07:00:00',
            'close_time' => '09:30:00',
            'manual_reopen' => true,
            'number_limit' => 100,
            'number_limit_3d' => 100,
            'status' => 'open',
        ]);
        $session = AgentSession::openSession($agent, $round, $operator->id);

        return [$admin, $operator, $round, $session, $agent];
    }

    private function signIn(Agent $agent): string
    {
        return $this->postJson(route('api.v1.auth.login'), [
            'login_code' => $agent->mobile_login_code,
            'password' => 's3cret-pin',
        ])->json('token');
    }

    private function record(AgentSession $session, string $saleType, string $input): array
    {
        return [
            'client_uuid' => (string) Str::uuid(),
            'session_id' => $session->id,
            'sale_type' => $saleType,
            'input' => $input,
            'recorded_at' => now()->toIso8601String(),
        ];
    }
}
