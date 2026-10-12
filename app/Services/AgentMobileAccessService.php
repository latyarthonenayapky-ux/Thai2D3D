<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Facades\DB;

/**
 * Creates the phone sign-in credentials an Agent needs for the mobile app.
 *
 * The PIN is only ever returned in clear text once, so the admin hands it over
 * on the Settings page and Laravel stores just its bcrypt hash.
 */
class AgentMobileAccessService
{
    public function issue(Agent $agent, AgentAccessTokenGuard $tokens, ?string $loginCode = null): array
    {
        // An Agent already knows the code on their phone, so re-issuing replaces
        // the PIN but keeps the code.
        $agent->mobile_login_code = $loginCode !== null && trim($loginCode) !== ''
            ? strtoupper(trim($loginCode))
            : ($agent->mobile_login_code ?? $this->uniqueLoginCode($agent));
        $pin = (string) random_int(100000, 999999);
        $agent->mobile_password = $pin;

        // A fresh PIN invalidates every phone that signed in with the old one.
        $tokens->revokeAllForAgent($agent->id);
        $agent->save();

        return [
            'agent_id' => $agent->id,
            'agent_code' => $agent->agent_code,
            'agent_name' => $agent->agent_name,
            'login_code' => $agent->mobile_login_code,
            'pin' => $pin,
            'sign_in_url' => rtrim((string) config('app.url'), '/'),
        ];
    }

    /**
     * @return array<int, array{agent_id: int, agent_code: string, agent_name: string, login_code: string, pin: string, sign_in_url: string}>
     */
    public function issueForAgentsWithoutAccess(int $adminId, AgentAccessTokenGuard $tokens): array
    {
        $agents = Agent::query()
            ->where('admin_id', $adminId)
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('mobile_login_code')->orWhereNull('mobile_password');
            })
            ->orderBy('agent_code')
            ->get();

        return $agents->map(fn (Agent $agent): array => $this->issue($agent, $tokens))->all();
    }

    private function uniqueLoginCode(Agent $agent): string
    {
        $base = 'AGT-'.str_pad((string) $agent->id, 4, '0', STR_PAD_LEFT);

        if (! DB::table('agents')->where('mobile_login_code', $base)->exists()) {
            return $base;
        }

        do {
            $candidate = $base.'-'.random_int(10, 99);
        } while (DB::table('agents')->where('mobile_login_code', $candidate)->exists());

        return $candidate;
    }
}
