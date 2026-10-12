<?php

namespace App\Http\Middleware;

use App\Services\AgentAccessTokenGuard;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAgentAccessToken
{
    public function __construct(
        protected AgentAccessTokenGuard $tokens
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();

        if (! $plainToken) {
            return $this->unauthenticated('Sign in from the mobile app to use this endpoint.');
        }

        $token = $this->tokens->find($plainToken);

        if (! $token || ! $token->isUsable()) {
            return $this->unauthenticated('This mobile sign-in is no longer valid. Sign in again.');
        }

        $agent = $token->agent;

        if (! $agent || ! $agent->hasMobileAccess() || $agent->status !== 'active') {
            return response()->json(['message' => 'This Agent is not allowed to use the mobile app.'], 403);
        }

        if ($token->last_used_at === null || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now()])->save();
        }

        $request->attributes->set('apiAgent', $agent);
        $request->attributes->set('apiAgentToken', $token);

        return $next($request);
    }

    private function unauthenticated(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 401);
    }
}
