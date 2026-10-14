<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\AdminBusinessSetting;
use App\Models\Agent;
use App\Models\AgentSession;
use App\Models\OfflineSyncReview;
use App\Models\Round;
use App\Models\RoundSchedule;
use App\Models\SaleDetail;
use App\Models\User;
use App\Services\AmountLimitPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly AmountLimitPolicy $amountLimits)
    {
    }
    public function index(Request $request): View|RedirectResponse
    {
        $operator = $request->user();

        if ($operator->isAdmin()) {
            $rounds = $this->adminLiveRounds($operator);

            $pendingReviewQuery = OfflineSyncReview::query()
                ->where('status', 'pending')
                ->whereHas('round', fn ($query) => $query->where('admin_id', $operator->id));

            $setupChecklist = [
                [
                    'label' => __('app.dashboard.checklist_schedule'),
                    'done' => RoundSchedule::query()
                        ->where('admin_id', $operator->id)
                        ->whereNotNull('close_time')
                        ->exists(),
                    'href' => route('admin.settings.index').'#round-schedule',
                    'action' => __('app.dashboard.checklist_schedule_action'),
                ],
                [
                    'label' => __('app.dashboard.checklist_payout'),
                    'done' => AdminBusinessSetting::query()
                        ->where('admin_id', $operator->id)
                        ->whereNotNull('payout_2d_multiplier')
                        ->exists(),
                    'href' => route('admin.settings.index').'#payout-settings',
                    'action' => __('app.dashboard.checklist_payout_action'),
                ],
                [
                    'label' => __('app.dashboard.checklist_agents'),
                    'done' => Agent::query()
                        ->where('admin_id', $operator->id)
                        ->where('status', 'active')
                        ->exists(),
                    'href' => route('admin.settings.index').'#agents',
                    'action' => __('app.dashboard.checklist_agents_action'),
                ],
                [
                    'label' => __('app.dashboard.checklist_operators'),
                    'done' => User::query()
                        ->where('admin_id', $operator->id)
                        ->where('role', 'operator')
                        ->where('status', true)
                        ->exists(),
                    'href' => route('admin.users.index'),
                    'action' => __('app.dashboard.checklist_operators_action'),
                ],
            ];

            return view('dashboard', [
                'adminRounds' => $rounds,
                'todaySummary' => $this->today($operator),
                'pendingReviewCount' => (clone $pendingReviewQuery)->count(),
                'latestPendingReviewId' => (clone $pendingReviewQuery)->latest()->value('id'),
                'setupChecklist' => $setupChecklist,
                'setupPending' => collect($setupChecklist)->contains(fn (array $item): bool => ! $item['done']),
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

    public function live(Request $request): JsonResponse
    {
        $operator = $request->user();
        abort_unless($operator->isAdmin(), 403);

        return response()->json([
            'rounds' => $this->adminLiveRounds($operator)
                ->map(fn (Round $round): array => ['id' => $round->id] + $round->figures)
                ->values()
                ->all(),
            'today' => $this->today($operator),
        ]);
    }

    private function adminLiveRounds(User $admin): Collection
    {
        $rounds = Round::query()
            ->where('admin_id', $admin->id)
            ->where('status', 'open')
            ->orderBy('round_date')
            ->orderBy('round_no')
            ->get();

        foreach ($rounds as $round) {
            $round->setAttribute('figures', $this->figures($round));
        }

        return $rounds;
    }

    /**
     * What the office watches while a Round is live: the round-wide accepted
     * figures, the best-selling numbers, and the numbers that passed the single
     * 2D Amount Limit. Every Agent and Operator of the Round counts, phone
     * uploads included.
     *
     * @return array{accepted_count: int, accepted_amount: float, amount_limit: float|null, top: array<int, array{number: string, amount: float}>, breaches: array<int, array{number: string, limit: float, sold: float, excess: float}>}
     */
    private function figures(Round $round): array
    {
        $sold = SaleDetail::query()
            ->join('sale_inputs', 'sale_inputs.id', '=', 'sale_details.sale_input_id')
            ->join('agent_sessions', 'agent_sessions.id', '=', 'sale_inputs.agent_session_id')
            ->where('agent_sessions.round_id', $round->id)
            ->where('sale_details.status', 'accepted')
            ->where('sale_details.is_excluded', false)
            ->select('sale_details.number as number')
            ->selectRaw('COUNT(*) as entries, SUM(sale_details.amount) as total_amount')
            ->groupBy('sale_details.number')
            ->get();

        $limit = $this->amountLimits->twoDigitCap($round);

        $top = $sold
            ->map(fn ($row): array => [
                'number' => str_pad((string) $row->number, 2, '0', STR_PAD_LEFT),
                'amount' => round((float) $row->total_amount, 2),
            ])
            ->sortBy([['amount', 'desc'], ['number', 'asc']])
            ->take(10)
            ->values()
            ->all();

        $breaches = $limit === null ? [] : $sold
            ->filter(fn ($row): bool => round((float) $row->total_amount, 2) > $limit)
            ->map(fn ($row): array => [
                'number' => str_pad((string) $row->number, 2, '0', STR_PAD_LEFT),
                'limit' => $limit,
                'sold' => round((float) $row->total_amount, 2),
                'excess' => round((float) $row->total_amount - $limit, 2),
            ])
            ->sortBy([['excess', 'desc'], ['number', 'asc']])
            ->values()
            ->all();

        return [
            'accepted_count' => (int) $sold->sum('entries'),
            'accepted_amount' => round((float) $sold->sum('total_amount'), 2),
            'amount_limit' => $limit,
            'top' => $top,
            'breaches' => $breaches,
        ];
    }

    /**
     * Today's book, round by round — what remains after the last Round closes.
     *
     * @return array{rounds: array<int, array<string, mixed>>, accepted_count: int, accepted_amount: float}
     */
    private function today(User $admin): array
    {
        $rounds = Round::query()
            ->with('result')
            ->where('admin_id', $admin->id)
            ->whereDate('round_date', today('Asia/Yangon'))
            ->orderBy('round_no')
            ->get();

        $rows = $rounds->map(fn (Round $round): array => [
            'id' => $round->id,
            'round_no' => $round->round_no,
            'start_time' => $round->start_time,
            'close_time' => $round->close_time,
            'status' => $round->status,
            'result_2d' => $round->result?->result_2d,
        ] + $this->figures($round))->values()->all();

        return [
            'rounds' => $rows,
            'accepted_count' => (int) array_sum(array_column($rows, 'accepted_count')),
            'accepted_amount' => round((float) array_sum(array_column($rows, 'accepted_amount')), 2),
        ];
    }
}
