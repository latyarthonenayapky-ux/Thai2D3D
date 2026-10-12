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

    private function businessAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => true]);
        app(InitializeAdminBusiness::class)->initialize($admin);

        return $admin;
    }

    private function agent(User $admin, string $code = 'WYA-01', ?string $loginCode = null, ?string $password = null): Agent
    {
        return Agent::query()->create([
            'admin_id' => $admin->id,
            'agent_code' => $code,
            'agent_name' => "Agent {$code}",
            'mobile_login_code' => $loginCode,
            'mobile_password' => $password,
        ]);
    }
}
