<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentSession;
use App\Models\Round;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class AgentSessionController extends Controller
{
    public function workspace(Request $request): View
    {
        $request->session()->put('draw_mode', '2d');
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isOperator(), 403);
        $adminId = $user->isAdmin() ? $user->id : $user->admin_id;
        abort_if($adminId === null, 403);
        $rounds = Round::query()
            ->where('admin_id', $adminId)
            ->where('status', 'open')
            ->orderBy('round_date')
            ->orderBy('round_no')
            ->get();
        $round = $rounds->firstWhere('id', (int) $request->query('round_id'))
            ?? $rounds->first();

        return view('operator.sales.workspace', [
            'rounds' => $rounds,
            'round' => $round,
            'agents' => Agent::query()
                ->where('admin_id', $adminId)
                ->where('status', 'active')
                ->orderBy('agent_code')
                ->get(),
            'sessions' => $round
                ? AgentSession::query()
                    ->with('operator')
                    ->where('round_id', $round->id)
                    ->get()
                    ->keyBy('agent_id')
                : collect(),
        ]);
    }

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
