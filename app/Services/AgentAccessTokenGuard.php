<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentAccessToken;
use Illuminate\Support\Facades\Hash;

class AgentAccessTokenGuard
{
    /**
     * Issue a bearer token for one mobile device. The plain token is returned
     * once and only its SHA-256 digest is stored.
     *
     * @return array{token: string, record: AgentAccessToken}
     */
    public function issue(Agent $agent, ?string $deviceName = null): array
    {
        $agent->accessTokens()
            ->where('expires_at', '<=', now())
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $plain = bin2hex(random_bytes(32));

        $record = $agent->accessTokens()->create([
            'token_hash' => $this->hash($plain),
            'device_name' => $deviceName,
            'expires_at' => now()->addDays((int) config('mobileapi.token_ttl_days', 180)),
        ]);

        return ['token' => $plain, 'record' => $record];
    }

    public function find(string $plainToken): ?AgentAccessToken
    {
        return AgentAccessToken::query()
            ->with('agent')
            ->where('token_hash', $this->hash($plainToken))
            ->first();
    }

    public function revoke(AgentAccessToken $token): void
    {
        $token->forceFill(['revoked_at' => now()])->save();
    }

    public function revokeAllForAgent(int $agentId): int
    {
        return AgentAccessToken::query()
            ->where('agent_id', $agentId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public function verifyPassword(string $plain, Agent $agent): bool
    {
        return $agent->mobile_password !== null && Hash::check($plain, $agent->mobile_password);
    }
}
