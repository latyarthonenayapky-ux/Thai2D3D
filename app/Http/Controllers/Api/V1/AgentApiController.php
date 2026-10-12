<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentAccessToken;
use Illuminate\Http\Request;

abstract class AgentApiController extends Controller
{
    protected function agent(Request $request): Agent
    {
        $agent = $request->attributes->get('apiAgent');
        abort_unless($agent instanceof Agent, 401);

        return $agent;
    }

    protected function token(Request $request): AgentAccessToken
    {
        $token = $request->attributes->get('apiAgentToken');
        abort_unless($token instanceof AgentAccessToken, 401);

        return $token;
    }

    /**
     * @return array<string, mixed>
     */
    protected function sessionPayload($session): array
    {
        return [
            'id' => $session->id,
            'session_code' => $session->session_code,
            'status' => $session->status,
            'agent_id' => $session->agent_id,
            'round_id' => $session->round_id,
            'round' => $this->roundPayload($session->round),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function roundPayload($round): array
    {
        return [
            'id' => $round->id,
            'round_no' => $round->round_no,
            'round_date' => optional($round->round_date)->format('Y-m-d'),
            'start_time' => $round->start_time,
            'close_time' => $round->close_time,
            'status' => $round->status,
        ];
    }
}
