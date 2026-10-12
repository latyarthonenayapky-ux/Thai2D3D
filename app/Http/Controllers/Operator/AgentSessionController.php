<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentSession;
use App\Models\Round;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AgentSessionController extends Controller
{
    public function claim(Request $request, Round $round, Agent $agent): RedirectResponse
    {
        $handler = $request->user();
        abort_unless($handler->isAdmin() || $handler->isOperator(), 403);
        $adminId = $handler->isAdmin() ? $handler->id : $handler->admin_id;
        abort_unless($round->admin_id === $adminId, 404);
        abort_unless($agent->admin_id === $adminId, 404);
        abort_unless($agent->status === 'active', 404);

        try {
            AgentSession::openSession($agent, $round, $handler->id);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['agent' => $exception->getMessage()]);
        }

        $session = AgentSession::query()
            ->where('round_id', $round->id)
            ->where('agent_id', $agent->id)
            ->where('operator_id', $handler->id)
            ->firstOrFail();

        return redirect()->route('operator.sales.show', [
            $session,
            'mode' => '2d',
            'view' => $request->input('view') === 'agent' ? 'agent' : null,
        ])
            ->with('status', "{$agent->agent_code} is now assigned to you for Round {$round->round_no}.");
    }
}
