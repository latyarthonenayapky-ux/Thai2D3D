<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentAccessToken;
use App\Models\User;
use App\Services\InitializeAdminBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AgentMobileAccessAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_issues_mobile_credentials_that_the_api_accepts(): void
    {
        $admin = $this->businessAdmin();
        $agent = $this->agent($admin);

        $this->actingAs($admin)
            ->patch(route('admin.settings.agents.mobile-access', $agent), [
                'mobile_login_code' => 'wya-mob-01',
                'mobile_password' => 'nay-2026-pin',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $agent->refresh();
        $this->assertSame('WYA-MOB-01', $agent->mobile_login_code);
        $this->assertTrue(Hash::check('nay-2026-pin', $agent->mobile_password));

        $this->postJson(route('api.v1.auth.login'), [
            'login_code' => 'wya-mob-01',
            'password' => 'nay-2026-pin',
        ])->assertCreated();

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('WYA-MOB-01')
            ->assertSee('Mobile app access');
    }

    public function test_a_password_is_required_the_first_time_mobile_access_is_enabled(): void
    {
        $admin = $this->businessAdmin();
        $agent = $this->agent($admin);

        $this->actingAs($admin)
            ->patch(route('admin.settings.agents.mobile-access', $agent), [
                'mobile_login_code' => 'WYA-MOB-02',
            ])
            ->assertSessionHasErrors('mobile_password');

        $this->assertNull($agent->fresh()->mobile_login_code);
    }

    public function test_login_codes_are_unique_across_the_whole_system(): void
    {
        $admin = $this->businessAdmin();
        $otherAdmin = User::factory()->create(['role' => 'admin', 'status' => true]);
        app(InitializeAdminBusiness::class)->initialize($otherAdmin);

        $this->actingAs($admin)->patch(route('admin.settings.agents.mobile-access', $this->agent($admin)), [
            'mobile_login_code' => 'SHARED-01',
            'mobile_password' => 'first-password',
        ])->assertSessionHasNoErrors();

        $this->actingAs($otherAdmin)
            ->patch(route('admin.settings.agents.mobile-access', $this->agent($otherAdmin, 'AG-2')), [
                'mobile_login_code' => 'shared-01',
                'mobile_password' => 'second-password',
            ])
            ->assertSessionHasErrors('mobile_login_code');
    }

    public function test_rotating_the_password_signs_out_every_phone(): void
    {
        $admin = $this->businessAdmin();
        $agent = $this->agent($admin, 'WYA-01', 'MOB-01', 'old-password');
        $token = $this->postJson(route('api.v1.auth.login'), [
            'login_code' => 'MOB-01',
            'password' => 'old-password',
        ])->json('token');

        $this->actingAs($admin)
            ->patch(route('admin.settings.agents.mobile-access', $agent), [
                'mobile_login_code' => 'MOB-01',
                'mobile_password' => 'brand-new-password',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, AgentAccessToken::query()->whereNull('revoked_at')->count());
        $this->withToken($token)->getJson(route('api.v1.me'))->assertUnauthorized();
    }

    public function test_disabling_mobile_access_blocks_sign_in_and_active_tokens(): void
    {
        $admin = $this->businessAdmin();
        $agent = $this->agent($admin, 'WYA-01', 'MOB-01', 'long-enough');
        $token = $this->postJson(route('api.v1.auth.login'), [
            'login_code' => 'MOB-01',
            'password' => 'long-enough',
        ])->json('token');

        $this->actingAs($admin)
            ->delete(route('admin.settings.agents.mobile-access.disable', $agent))
            ->assertRedirect();

        $agent->refresh();
        $this->assertNull($agent->mobile_login_code);
        $this->assertNull($agent->mobile_password);
        $this->withToken($token)->getJson(route('api.v1.me'))->assertUnauthorized();
        $this->postJson(route('api.v1.auth.login'), [
            'login_code' => 'MOB-01',
            'password' => 'long-enough',
        ])->assertUnauthorized();
    }

    public function test_signing_out_phones_keeps_the_credentials_available(): void
    {
        $admin = $this->businessAdmin();
        $agent = $this->agent($admin, 'WYA-01', 'MOB-01', 'long-enough');
        $token = $this->postJson(route('api.v1.auth.login'), [
            'login_code' => 'MOB-01',
            'password' => 'long-enough',
        ])->json('token');

        $this->actingAs($admin)
            ->delete(route('admin.settings.agents.mobile-access.devices', $agent))
            ->assertRedirect();

        $this->withToken($token)->getJson(route('api.v1.me'))->assertUnauthorized();
        $this->postJson(route('api.v1.auth.login'), [
            'login_code' => 'MOB-01',
            'password' => 'long-enough',
        ])->assertCreated();
    }

    public function test_operators_cannot_issue_mobile_credentials(): void
    {
        $admin = $this->businessAdmin();
        $agent = $this->agent($admin);
        $operator = User::factory()->create([
            'role' => 'operator',
            'admin_id' => $admin->id,
            'status' => true,
        ]);

        $this->actingAs($operator)
            ->patch(route('admin.settings.agents.mobile-access', $agent), [
                'mobile_login_code' => 'SNEAK-01',
                'mobile_password' => 'long-enough',
            ])
            ->assertForbidden();

        $this->assertNull($agent->fresh()->mobile_login_code);
    }

    public function test_issuing_credentials_returns_a_pin_that_signs_in_once_and_is_never_shown_again(): void
    {
        $admin = $this->businessAdmin();
        $agent = $this->agent($admin);

        $this->actingAs($admin)
            ->post(route('admin.settings.agents.mobile-access.issue', $agent))
            ->assertRedirect()
            ->assertSessionHas('mobile_credentials');

        $credential = session()->get('mobile_credentials')[0] ?? null;
        $this->assertNotNull($credential);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $credential['pin']);
        $this->assertSame('AGT-'.str_pad((string) $agent->id, 4, '0', STR_PAD_LEFT), $credential['login_code']);

        $agent->refresh();
        $this->assertSame($credential['login_code'], $agent->mobile_login_code);
        $this->assertTrue(Hash::check($credential['pin'], $agent->mobile_password));

        $this->postJson(route('api.v1.auth.login'), [
            'login_code' => $credential['login_code'],
            'password' => $credential['pin'],
        ])->assertCreated();

        // The handover sheet shows the PIN on the next page load, then the flash
        // bag ages out. Checking the card marker rather than the raw PIN matters:
        // a six-digit PIN is a substring of the random CSRF token often enough to
        // make assertDontSee($pin) flaky.
        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee($credential['pin'])
            ->assertSee('Sign in at')
            ->assertSee($credential['login_code']);

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertDontSee('Sign in at')
            ->assertSessionMissing('mobile_credentials');
    }

    public function test_bulk_issue_only_touches_active_agents_without_credentials(): void
    {
        $admin = $this->businessAdmin();
        $ready = $this->agent($admin, 'AG-READY', 'MOB-READY', 'long-enough');
        $this->agent($admin, 'AG-TWO');
        $this->agent($admin, 'AG-THREE');
        $this->agent($admin, 'AG-OFF', null, null, 'inactive');
        $token = $this->postJson(route('api.v1.auth.login'), [
            'login_code' => 'MOB-READY',
            'password' => 'long-enough',
        ])->json('token');

        $this->actingAs($admin)
            ->post(route('admin.settings.agents.mobile-access.issue-all'))
            ->assertRedirect();

        $issued = session()->get('mobile_credentials');
        $this->assertCount(2, $issued);
        $this->assertSame(['AG-THREE', 'AG-TWO'], array_column($issued, 'agent_code'));

        $this->assertSame('MOB-READY', $ready->fresh()->mobile_login_code);
        $this->assertNull(Agent::query()->where('agent_code', 'AG-OFF')->firstOrFail()->mobile_login_code);
        $this->withToken($token)->getJson(route('api.v1.me'))->assertOk();
    }

    public function test_reissuing_credentials_signs_out_every_phone(): void
    {
        $admin = $this->businessAdmin();
        $agent = $this->agent($admin, 'AG-RE', 'MOB-RE', 'long-enough');
        $token = $this->postJson(route('api.v1.auth.login'), [
            'login_code' => 'MOB-RE',
            'password' => 'long-enough',
        ])->json('token');

        $this->actingAs($admin)
            ->post(route('admin.settings.agents.mobile-access.issue', $agent))
            ->assertSessionHas('mobile_credentials');

        $new = session()->get('mobile_credentials')[0];
        $this->assertSame('MOB-RE', $new['login_code']);
        $this->withToken($token)->getJson(route('api.v1.me'))->assertUnauthorized();
        $this->postJson(route('api.v1.auth.login'), [
            'login_code' => 'MOB-RE',
            'password' => 'long-enough',
        ])->assertUnauthorized();
        $this->postJson(route('api.v1.auth.login'), [
            'login_code' => 'MOB-RE',
            'password' => $new['pin'],
        ])->assertCreated();
    }

    public function test_a_custom_login_code_is_normalised_and_must_be_unique(): void
    {
        $admin = $this->businessAdmin();
        $first = $this->agent($admin, 'AG-A');
        $second = $this->agent($admin, 'AG-B');

        $this->actingAs($admin)
            ->post(route('admin.settings.agents.mobile-access.issue', $first), [
                'mobile_login_code' => 'agt-custom',
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame('AGT-CUSTOM', $first->fresh()->mobile_login_code);

        $this->actingAs($admin)
            ->post(route('admin.settings.agents.mobile-access.issue', $second), [
                'mobile_login_code' => 'AGT-CUSTOM',
            ])
            ->assertSessionHasErrors('mobile_login_code');
        $this->assertNull($second->fresh()->mobile_login_code);
    }

    public function test_operators_cannot_reach_the_issue_endpoints(): void
    {
        $admin = $this->businessAdmin();
        $agent = $this->agent($admin);
        $operator = User::factory()->create([
            'role' => 'operator',
            'admin_id' => $admin->id,
            'status' => true,
        ]);

        $this->actingAs($operator)
            ->post(route('admin.settings.agents.mobile-access.issue', $agent))
            ->assertForbidden();
        $this->actingAs($operator)
            ->post(route('admin.settings.agents.mobile-access.issue-all'))
            ->assertForbidden();

        $this->assertNull($agent->fresh()->mobile_login_code);
    }

    private function businessAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        app(InitializeAdminBusiness::class)->initialize($admin);

        return $admin;
    }

    private function agent(User $admin, string $code = 'WYA-01', ?string $loginCode = null, ?string $password = null, string $status = 'active'): Agent
    {
        return Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => $code,
            'agent_name' => "Agent {$code}",
            'status' => $status,
            'mobile_login_code' => $loginCode,
            'mobile_password' => $password,
        ]);
    }
}
