<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentSession;
use App\Models\OfflineSyncReview;
use App\Models\Round;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $operator = $request->user();
        if (($operator->isAdmin() || $operator->isOperator()) && $request->session()->get('draw_mode', '2d') === '3d') {
            return redirect()->route('three-digit.sales.workspace');
        }

        if ($operator->isAdmin()) {
            $rounds = Round::query()
                ->where('admin_id', $operator->id)
                ->where('status', 'open')
                ->orderBy('round_date')
                ->orderBy('round_no')
                ->get();

            foreach ($rounds as $round) {
                $round->setAttribute('agent_sales', DB::table('agents as a')
                    ->leftJoin('agent_round_settings as ars', function ($join) use ($round): void {
                        $join->on('ars.agent_id', '=', 'a.id')
                            ->where('ars.round_id', '=', $round->id);
                    })
                    ->leftJoin('users as handlers', 'handlers.id', '=', 'ars.operator_id')
                    ->leftJoin('agent_sessions as ases', function ($join) use ($round): void {
                        $join->on('ases.agent_id', '=', 'a.id')
                            ->on('ases.round_id', '=', 'ars.round_id')
                            ->on('ases.operator_id', '=', 'ars.operator_id')
                            ->where('ases.round_id', '=', $round->id)
                            ->where('ases.status', '=', 'open');
                    })
                    ->leftJoin('sale_inputs as si', 'si.agent_session_id', '=', 'ases.id')
                    ->leftJoin('sale_details as sd', function ($join): void {
                        $join->on('sd.sale_input_id', '=', 'si.id')
                            ->where('sd.status', '=', 'accepted')
                            ->where('sd.is_excluded', '=', false);
                    })
                    ->where('a.admin_id', $operator->id)
                    ->where('a.status', 'active')
                    ->select([
                        'a.id as agent_id',
                        'a.agent_code',
                        'a.agent_name',
                        'ases.id as agent_session_id',
                        'ars.operator_id as assigned_user_id',
                        'handlers.name as handler_name',
                        'handlers.role as handler_role',
                        'ars.amount_limit',
                    ])
                    ->selectRaw('COUNT(sd.id) as accepted_count')
                    ->selectRaw('COALESCE(SUM(sd.amount), 0) as accepted_amount')
                    ->groupBy(
                        'a.id',
                        'a.agent_code',
                        'a.agent_name',
                        'ases.id',
                        'ars.operator_id',
                        'handlers.name',
                        'handlers.role',
                        'ars.amount_limit'
                    )
                    ->get()
                    ->map(fn ($agent): object => (object) [
                        'agent_id' => (int) $agent->agent_id,
                        'agent_code' => $agent->agent_code,
                        'agent_name' => $agent->agent_name,
                        'agent_session_id' => $agent->agent_session_id === null ? null : (int) $agent->agent_session_id,
                        'assigned_user_id' => $agent->assigned_user_id === null ? null : (int) $agent->assigned_user_id,
                        'handler_name' => $agent->handler_name,
                        'handler_role' => $agent->handler_role,
                        'amount_limit' => $agent->amount_limit === null ? null : (float) $agent->amount_limit,
                        'accepted_count' => (int) $agent->accepted_count,
                        'accepted_amount' => (float) $agent->accepted_amount,
                    ])
                    ->sortBy([['accepted_amount', 'desc'], ['agent_code', 'asc']])
                    ->values());

                $round->setAttribute('accepted_count', $round->agent_sales->sum('accepted_count'));
                $round->setAttribute('accepted_amount', $round->agent_sales->sum('accepted_amount'));
            }

            $pendingReviewQuery = OfflineSyncReview::query()
                ->where('status', 'pending')
                ->whereHas('round', fn ($query) => $query->where('admin_id', $operator->id));

            return view('dashboard', [
                'adminRounds' => $rounds,
                'pendingReviewCount' => (clone $pendingReviewQuery)->count(),
                'latestPendingReviewId' => (clone $pendingReviewQuery)->latest()->value('id'),
            ]);
        }

        if (! $operator->isOperator()) {
            return view('dashboard');
        }

        return view('dashboard', [
            'openRounds' => Round::query()
                ->with([
                    'agentRoundSettings.operator',
                    'sessions' => fn ($query) => $query
                        ->where('operator_id', $operator->id)
                        ->where('status', 'open'),
                ])
                ->where('admin_id', $operator->admin_id)
                ->where('status', 'open')
                ->orderBy('round_date')
                ->orderBy('round_no')
                ->get(),
            'agents' => Agent::query()
                ->where('admin_id', $operator->admin_id)
                ->where('status', 'active')
                ->orderBy('agent_code')
                ->get(),
            'recentSessions' => AgentSession::query()
                ->with(['round', 'agent'])
                ->where('operator_id', $operator->id)
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }
}
