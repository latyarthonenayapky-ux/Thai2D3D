<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Agent;
use App\Models\AgentSession;
use App\Models\OfflineSyncReview;
use App\Models\Round;
use App\Services\AgentAccessTokenGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentAuthController extends AgentApiController
{
    public function __construct(
        protected AgentAccessTokenGuard $tokens
    ) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login_code' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $agent = Agent::query()
            ->where('mobile_login_code', strtoupper($validated['login_code']))
            ->first();

        if (
            ! $agent
            || $agent->status !== 'active'
            || ! $agent->hasMobileAccess()
            || ! $this->tokens->verifyPassword($validated['password'], $agent)
        ) {
            return response()->json(['message' => 'Login code or password is incorrect.'], 401);
        }

        $issued = $this->tokens->issue($agent, $validated['device_name'] ?? null);

        return response()->json([
            'token' => $issued['token'],
            'expires_at' => $issued['record']->expires_at->toIso8601String(),
            'agent' => $this->agentPayload($agent),
            'rounds' => $this->openRounds($agent),
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->tokens->revoke($this->token($request));

        return response()->json(['message' => 'Signed out on this device.']);
    }

    public function me(Request $request): JsonResponse
    {
        $agent = $this->agent($request);

        return response()->json([
            'agent' => $this->agentPayload($agent),
            'token' => [
                'expires_at' => $this->token($request)->expires_at->toIso8601String(),
                'device_name' => $this->token($request)->device_name,
            ],
            'rounds' => $this->openRounds($agent),
            'sessions' => $this->openSessions($agent),
            'awaiting_review' => OfflineSyncReview::query()
                ->where('agent_id', $agent->id)
                ->where('status', 'pending')
                ->count(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function openRounds(Agent $agent): array
    {
        return Round::query()
            ->where('admin_id', $agent->admin_id)
            ->where('status', 'open')
            ->orderByDesc('round_date')
            ->orderByDesc('round_no')
            ->limit(5)
            ->get()
            ->map(fn (Round $round): array => $this->roundPayload($round) + [
                'has_session' => AgentSession::query()
                    ->where('round_id', $round->id)
                    ->where('agent_id', $agent->id)
                    ->where('status', 'open')
                    ->exists(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function openSessions(Agent $agent): array
    {
        return AgentSession::query()
            ->with('round')
            ->where('agent_id', $agent->id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->limit(10)
            ->get()
            ->map(fn (AgentSession $session): array => $this->sessionPayload($session))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function agentPayload(Agent $agent): array
    {
        return [
            'id' => $agent->id,
            'agent_code' => $agent->agent_code,
            'agent_name' => $agent->agent_name,
            'phone' => $agent->phone,
        ];
    }
}
