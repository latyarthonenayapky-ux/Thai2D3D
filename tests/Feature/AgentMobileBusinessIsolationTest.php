<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentSession;
use App\Models\Round;
use App\Models\SaleInput;
use App\Models\User;
use App\Services\InitializeAdminBusiness;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Two businesses share one VPS install, so every mobile endpoint has to answer
 * only for the business that owns the signed-in Agent.
 */
class AgentMobileBusinessIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_in_and_me_only_list_the_agents_own_business_rounds(): void
    {
        $mine = $this->business('THIPA', 'TP-01', 'THIPA-CODE', '2026-10-13');
        $theirs = $this->business('SUU', 'SU-01', 'SUU-CODE', '2026-10-14');

        $login = $this->postJson(route('api.v1.auth.login'), ['login_code' => 'THIPA-CODE',
            'password' => 's3cret-pin',
        ])->assertCreated()->json('rounds');

        $this->assertSame([$mine['round']->id], array_column($login, 'id'));

        $rounds = $this->withToken($this->signIn($mine['agent']))
            ->getJson(route('api.v1.me'))
            ->assertOk()
            ->json('rounds');

        $this->assertSame([$mine['round']->id], array_column($rounds, 'id'));

        // …and the other business sees only its own Round.
        $theirsRounds = $this->withToken($this->signIn($theirs['agent']))
            ->getJson(route('api.v1.me'))
            ->assertOk()
            ->json('rounds');

        $this->assertSame([$theirs['round']->id], array_column($theirsRounds, 'id'));
    }

    public function test_an_agent_cannot_open_a_session_on_another_business_round(): void
    {
        $mine = $this->business('THIPA', 'TP-01', 'THIPA-CODE', '2026-10-13');
        $theirs = $this->business('SUU', 'SU-01', 'SUU-CODE', '2026-10-14');

        $this->withToken($this->signIn($mine['agent']))
            ->postJson(route('api.v1.sessions.open'), ['round_id' => $theirs['round']->id])
            ->assertNotFound()
            ->assertJsonPath('message', 'This Round is not part of your business.');

        $this->assertSame(0, AgentSession::query()
            ->where('agent_id', $mine['agent']->id)
            ->where('round_id', $theirs['round']->id)
            ->count());
    }

    public function test_an_agent_cannot_upload_into_or_read_another_business_session(): void
    {
        $mine = $this->business('THIPA', 'TP-01', 'THIPA-CODE', '2026-10-13');
        $theirs = $this->business('SUU', 'SU-01', 'SUU-CODE', '2026-10-14');
        $token = $this->signIn($mine['agent']);

        $this->withToken($token)
            ->postJson(route('api.v1.sales.upload'), ['records' => [[
                'client_uuid' => (string) Str::uuid(),
                'session_id' => $theirs['session']->id,
                'sale_type' => '2d',
                'input' => '171000',
            ]]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'error')
            ->assertJsonPath('results.0.message', 'This Agent session is not available to your account.');

        $this->withToken($token)
            ->getJson(route('api.v1.sessions.state', $theirs['session']))
            ->assertNotFound();

        $this->assertSame(0, SaleInput::query()->count());
    }

    public function test_a_login_code_is_unique_so_two_businesses_cannot_share_one(): void
    {
        $this->business('THIPA', 'TP-01', 'SHARED-CODE', '2026-10-13');
        $other = User::factory()->create(['role' => 'admin', 'status' => true]);
        app(InitializeAdminBusiness::class)->initialize($other);

        $this->assertDatabaseCount('agents', 1);

        // The second business is refused the same code, so a phone can never be
        // handed a code that resolves into two accounts.
        $this->expectException(QueryException::class);
        Agent::query()->create([
            'admin_id' => $other->id,
            'agent_code' => 'OTHER-01',
            'agent_name' => 'Other agent',
            'mobile_login_code' => 'SHARED-CODE',
            'mobile_password' => 's3cret-pin',
        ]);
    }

    /**
     * @return array{admin: User, operator: User, agent: Agent, round: Round, session: AgentSession}
     */
    private function business(string $code, string $agentCode, string $loginCode, string $date): array
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
            'agent_code' => $agentCode,
            'agent_name' => $code.' agent',
            'mobile_login_code' => $loginCode,
            'mobile_password' => 's3cret-pin',
        ]);
        $round = Round::query()->create([
            'admin_id' => $admin->id,
            'round_date' => $date,
            'round_no' => 1,
            'start_time' => '07:00:00',
            'close_time' => '09:30:00',
            'manual_reopen' => true,
            'number_limit' => 100,
            'number_limit_3d' => 100,
            'status' => 'open',
        ]);

        return [
            'admin' => $admin,
            'operator' => $operator,
            'agent' => $agent,
            'round' => $round,
            'session' => AgentSession::openSession($agent, $round, $operator->id),
        ];
    }

    private function signIn(Agent $agent): string
    {
        return $this->postJson(route('api.v1.auth.login'), [
            'login_code' => $agent->mobile_login_code,
            'password' => 's3cret-pin',
        ])->json('token');
    }
}
