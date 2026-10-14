<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentRoundSetting;
use App\Models\AgentSession;
use App\Models\Round;
use App\Models\SaleDetail;
use App\Models\ThreeDigitSaleDetail;
use App\Services\AmountLimitPolicy;
use App\Services\RoundSettlementCalculator;
use App\Services\SaleProcessor;
use App\Services\ThreeDigitSaleProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class SaleEntryController extends Controller
{
    public function __construct(private readonly AmountLimitPolicy $amountLimits)
    {
    }

    public function workspace(Request $request): RedirectResponse|View
    {
        $adminId = $this->authorizeWorkspace($request);

        $rounds = $this->openRounds($adminId);
        if ($rounds->isEmpty()) {
            return $this->unavailable(
                __('app.sale.no_round_title'),
                __('app.sale.no_round_message')
            );
        }

        $round = $this->pickRound($rounds, $request);
        $session = $this->readySession($round, $request->user()->id)
            ?? $this->firstReadySession($rounds, $request->user()->id);

        if ($session !== null) {
            return redirect()->route('operator.sales.show', [
                $session,
                'mode' => '2d',
                'view' => $request->query('view') === 'agent' ? 'agent' : null,
            ]);
        }

        return redirect()->route('operator.sales.setup', ['round_id' => $round->id]);
    }

    public function setup(Request $request): View
    {
        $adminId = $this->authorizeWorkspace($request);
        $rounds = $this->openRounds($adminId);
        if ($rounds->isEmpty()) {
            return $this->unavailable(
                __('app.sale.no_round_title'),
                __('app.sale.no_round_message')
            );
        }

        $round = $this->pickRound($rounds, $request);
        $options = $this->agentOptions($round, $request->user()->id, $adminId);

        return view('operator.sales.round-setup', [
            'rounds' => $rounds,
            'round' => $round,
            'agentOptions' => $options,
            'numberLimits' => $round->number_limits ?? [],
            'hotNumbers' => $round->hotNumbers()->orderBy('number')->get(),
            'activeSession' => $this->readySession($round, $request->user()->id),
            'selectableCount' => $options->where('selectable', true)->count(),
        ]);
    }

    public function entry(Request $request, RoundSettlementCalculator $calculator): RedirectResponse|View
    {
        $adminId = $this->authorizeWorkspace($request);
        $rounds = $this->openRounds($adminId);
        if ($rounds->isEmpty()) {
            return $this->unavailable(
                __('app.sale.no_round_title'),
                __('app.sale.no_round_message')
            );
        }

        $round = $this->pickRound($rounds, $request);
        if (($session = $this->readySession($round, $request->user()->id)) !== null) {
            return redirect()->route('operator.sales.show', [$session, 'mode' => '2d']);
        }

        return $this->saleView($request, null, $round, '2d', false, $calculator);
    }

    private function authorizeWorkspace(Request $request): int
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isOperator(), 403);
        $adminId = $user->isAdmin() ? $user->id : $user->admin_id;
        abort_if($adminId === null, 403);
        $request->session()->put('draw_mode', '2d');

        return (int) $adminId;
    }

    private function openRounds(int $adminId): Collection
    {
        return Round::query()
            ->where('admin_id', $adminId)
            ->where('status', 'open')
            ->orderBy('round_date')
            ->orderBy('round_no')
            ->get();
    }

    private function pickRound(Collection $rounds, Request $request): Round
    {
        return $rounds->firstWhere('id', (int) $request->query('round_id')) ?? $rounds->first();
    }

    private function firstReadySession(Collection $rounds, int $userId): ?AgentSession
    {
        return AgentSession::query()
            ->where('operator_id', $userId)
            ->whereIn('round_id', $rounds->pluck('id'))
            ->where('status', 'open')
            ->orderByDesc('id')
            ->first();
    }

    private function unavailable(string $title, string $message): View
    {
        return view('operator.sales.unavailable', ['title' => $title, 'message' => $message]);
    }

    public function select(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isOperator(), 403);
        $adminId = $user->isAdmin() ? $user->id : $user->admin_id;
        abort_if($adminId === null, 403);

        $validated = $request->validate([
            'round_id' => ['required', 'integer'],
            'agent_id' => ['required', 'integer'],
            'view' => ['nullable', 'in:agent'],
        ]);

        $round = Round::query()->where('admin_id', $adminId)->findOrFail((int) $validated['round_id']);
        $agent = Agent::query()
            ->where('admin_id', $adminId)
            ->where('status', 'active')
            ->findOrFail((int) $validated['agent_id']);

        $session = $this->readySession($round, $user->id, $agent->id);

        if ($session === null) {
            try {
                $session = AgentSession::openSession($agent, $round, $user->id);
            } catch (RuntimeException $exception) {
                return back()->withErrors(['agent' => $exception->getMessage()]);
            }
        }

        return redirect()->route('operator.sales.show', [
            $session,
            'mode' => '2d',
            'view' => ($validated['view'] ?? null) === 'agent' ? 'agent' : null,
        ])->with('status', "{$agent->agent_code} · Round {$round->round_no} is ready for sale input.");
    }

    private function readySession(Round $round, int $userId, ?int $agentId = null): ?AgentSession
    {
        return AgentSession::query()
            ->where('round_id', $round->id)
            ->where('operator_id', $userId)
            ->when($agentId !== null, fn ($query) => $query->where('agent_id', $agentId))
            ->where('status', 'open')
            ->orderByDesc('id')
            ->first();
    }

    private function agentOptions(Round $round, int $userId, int $adminId): Collection
    {
        $settings = AgentRoundSetting::query()
            ->where('round_id', $round->id)
            ->with('operator')
            ->get()
            ->keyBy('agent_id');
        $sessions = AgentSession::query()
            ->where('round_id', $round->id)
            ->with('operator')
            ->orderByDesc('id')
            ->get()
            ->groupBy('agent_id')
            ->map(fn ($group) => $group->firstWhere('status', 'open') ?? $group->first());

        return Agent::query()
            ->where('admin_id', $adminId)
            ->where('status', 'active')
            ->orderBy('agent_code')
            ->get()
            ->map(function (Agent $agent) use ($userId, $settings, $sessions): array {
                $setting = $settings->get($agent->id);
                $session = $sessions->get($agent->id);
                $ownerId = $setting?->operator_id ?? $session?->operator_id;
                $isMine = $ownerId !== null && (int) $ownerId === $userId;
                $isOpenForMe = $isMine && $session?->status === 'open';
                $isHeld = ! $isOpenForMe && $session?->status === 'open' && (int) $session->operator_id !== $userId;
                $isForeign = $ownerId !== null && ! $isMine;
                $holderName = $session?->operator?->name ?? $setting?->operator?->name;

                return [
                    'agent' => $agent,
                    'amount_limit' => $setting?->amount_limit,
                    'session' => $isOpenForMe ? $session : null,
                    'selectable' => ! $isForeign && ! $isHeld,
                    'state' => match (true) {
                        $isOpenForMe => 'Assigned to you · ready to sell',
                        $isForeign, $isHeld => 'In use by '.($holderName ?? 'another account'),
                        $isMine && $session?->status === 'closed' => 'Your session is closed · selecting it reopens',
                        $isMine => 'Assigned to you · selecting it opens',
                        default => 'Unclaimed · selecting it claims it for you',
                    },
                ];
            });
    }

    /**
     * Per-Agent, per-number accepted amounts that exceed that Agent's own Amount Limit.
     *
     * @return array<int, array{agent_code: string, number: string, limit: float, excess: float}>
     */
    private function aboveLimitRows(Round $round, ?int $agentId = null): array
    {
        $limits = AgentRoundSetting::query()
            ->where('round_id', $round->id)
            ->get(['agent_id', 'amount_limit'])
            ->keyBy('agent_id');
        $agents = Agent::query()
            ->where('admin_id', $round->admin_id)
            ->get(['id', 'agent_code'])
            ->keyBy('id');

        $totals = SaleDetail::query()
            ->join('sale_inputs', 'sale_inputs.id', '=', 'sale_details.sale_input_id')
            ->join('agent_sessions', 'agent_sessions.id', '=', 'sale_inputs.agent_session_id')
            ->where('agent_sessions.round_id', $round->id)
            ->where('sale_details.status', 'accepted')
            ->where('sale_details.is_excluded', false)
            ->select('agent_sessions.agent_id as agent_id', 'sale_details.number as number')
            ->selectRaw('SUM(sale_details.amount) as total_amount')
            ->groupBy('agent_sessions.agent_id', 'sale_details.number')
            ->get();

        return $totals
            ->filter(fn ($row): bool => $agentId === null || (int) $row->agent_id === $agentId)
            ->map(function ($row) use ($limits, $agents): ?array {
                $limit = $limits->get($row->agent_id)?->amount_limit;
                if ($limit === null || (float) $row->total_amount <= (float) $limit) {
                    return null;
                }

                return [
                    'agent_code' => $agents->get($row->agent_id)?->agent_code ?? '—',
                    'number' => (string) $row->number,
                    'limit' => (float) $limit,
                    'excess' => round((float) $row->total_amount - (float) $limit, 2),
                ];
            })
            ->filter()
            // stable chained sorts: excess high→low, then Agent code, then number
            ->sortBy('number')
            ->sortBy('agent_code')
            ->sortByDesc('excess')
            ->values()
            ->all();
    }

    public function show(Request $request, AgentSession $agentSession, RoundSettlementCalculator $calculator): View
    {
        $this->authorizeSession($request, $agentSession);

        $saleMode = $request->query('mode', $request->session()->get('draw_mode', '2d'));
        abort_unless(in_array($saleMode, ['2d', '3d'], true), 404);

        return $this->saleView(
            $request,
            $agentSession->load(['agent', 'round']),
            $agentSession->round,
            $saleMode,
            $request->query('view') === 'agent',
            $calculator
        );
    }

    private function saleView(
        Request $request,
        ?AgentSession $agentSession,
        Round $round,
        string $saleMode,
        bool $agentStatement,
        RoundSettlementCalculator $calculator
    ): View {
        $user = $request->user();
        $adminId = (int) ($user->isAdmin() ? $user->id : $user->admin_id);
        $awaitingAgent = $agentSession === null;
        $roundWide = $awaitingAgent || ($user->isAdmin() && ! $agentStatement);
        $saleInputs = $awaitingAgent ? collect() : $agentSession->saleInputs()
            ->with(['details', 'voidedBy'])
            ->latest()
            ->limit(50)
            ->get();
        $threeDigitSaleInputs = $awaitingAgent ? collect() : $agentSession->threeDigitSaleInputs()
            ->with(['details', 'voidedBy'])
            ->latest()
            ->limit(50)
            ->get();
        $agentOptions = $this->agentOptions($round, $user->id, $adminId);

        $totalsQuery = SaleDetail::query()
            ->whereHas('saleInput', function ($query) use ($agentSession, $round, $roundWide): void {
                if ($roundWide) {
                    $query->whereHas('agentSession', fn ($session) => $session->where('round_id', $round->id));
                } else {
                    $query->where('agent_session_id', $agentSession->id);
                }
            });
        $totals = $totalsQuery
            ->selectRaw(
                "SUM(CASE WHEN status = 'accepted' AND is_excluded = 0 THEN 1 ELSE 0 END) as accepted_count,
                 SUM(CASE WHEN status = 'accepted' AND is_excluded = 0 THEN amount ELSE 0 END) as accepted_amount,
                 SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count"
            )
            ->first();
        $threeDigitTotals = ThreeDigitSaleDetail::query()
            ->whereHas('saleInput', function ($query) use ($agentSession, $round, $roundWide): void {
                if ($roundWide) {
                    $query->whereHas('agentSession', fn ($session) => $session->where('round_id', $round->id));
                } else {
                    $query->where('agent_session_id', $agentSession->id);
                }
            })
            ->selectRaw(
                "SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_count,
                 SUM(CASE WHEN status = 'accepted' THEN amount ELSE 0 END) as accepted_amount,
                 SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count"
            )
            ->first();

        return view('operator.sales.show', [
            'agentSession' => $agentSession,
            'round' => $round,
            'saleInputs' => $saleInputs,
            'threeDigitSaleInputs' => $threeDigitSaleInputs,
            'roundWide' => $roundWide,
            'agentStatement' => $agentStatement,
            'acceptedCount' => (int) ($totals?->accepted_count ?? 0),
            'acceptedAmount' => (float) ($totals?->accepted_amount ?? 0),
            'rejectedCount' => (int) ($totals?->rejected_count ?? 0),
            'threeDigitAcceptedCount' => (int) ($threeDigitTotals?->accepted_count ?? 0),
            'threeDigitAcceptedAmount' => (float) ($threeDigitTotals?->accepted_amount ?? 0),
            'threeDigitRejectedCount' => (int) ($threeDigitTotals?->rejected_count ?? 0),
            'saleMode' => $saleMode,
            'availableSessions' => $awaitingAgent ? collect() : AgentSession::query()
                ->with(['agent', 'round'])
                ->where('operator_id', $user->id)
                ->where('round_id', $round->id)
                ->where('status', 'open')
                ->orderBy('agent_id')
                ->get(),
            'soldItems' => $roundWide
                ? $this->soldItemsForRound($round->id, $saleMode)
                : $this->soldItemsForSession($agentSession->id, $saleMode),
            'agentSoldItems' => $awaitingAgent ? [] : $this->soldItemsForSession($agentSession->id, $saleMode),
            'agentOptions' => $agentOptions,
            'selectableCount' => $agentOptions->where('selectable', true)->count(),
            'numberLimits' => $round->number_limits ?? [],
            'hotNumbers' => $round->hotNumbers()->orderBy('number')->get(),
            'overLimitRows' => $saleMode === '2d'
                ? $this->aboveLimitRows($round, $agentStatement ? $agentSession->agent_id : null)
                : [],
            'overLimitAllAgents' => $saleMode === '2d' && ! $agentStatement,
            'amountLimit' => $awaitingAgent ? null : AgentRoundSetting::query()
                ->where('agent_id', $agentSession->agent_id)
                ->where('round_id', $round->id)
                ->value($saleMode === '3d' ? 'amount_limit_3d' : 'amount_limit'),
            'amountLimitBreaches' => $awaitingAgent
                ? $this->amountLimits->breaches($round, null, null, $saleMode)
                : $this->amountLimits->breaches($round, $agentSession->agent_id, $agentSession->operator_id, $saleMode),
            'threeDigitAmountLimit' => $awaitingAgent ? 0.0 : (float) (AgentRoundSetting::query()
                ->where('agent_id', $agentSession->agent_id)
                ->where('round_id', $round->id)
                ->value('amount_limit_3d') ?? 0),
            'canEnterSales' => ! $awaitingAgent
                && (int) $agentSession->operator_id === (int) $user->id
                && $agentSession->status === 'open'
                && $round->status === 'open'
                && ! $round->isPastScheduledClose(),
            'roundPastCloseTime' => $round->isPastScheduledClose(),
            'agentBreakdown' => $user->isAdmin() ? $this->agentBreakdown($round, $saleMode) : null,
            'ownSettlement' => $awaitingAgent ? null : $calculator->calculate($round, (int) $user->id)['agents']
                ->first(fn (array $row): bool => $row['agent']->id === $agentSession->agent_id),
        ]);
    }

    /**
     * "Accepted but over the Amount Limit" sentence for the alert bar and the
     * save confirmation. Empty while every monitored tier is still inside its
     * limit, so a warning tier never adds noise.
     */
    private function amountLimitNotice(Round $round, ?int $agentId, ?int $operatorId, string $saleMode): string
    {
        $breaches = $this->amountLimits->breaches($round, $agentId, $operatorId, $saleMode);
        if ($breaches === []) {
            return '';
        }

        $parts = array_map(fn (array $tier): string => sprintf(
            '%s · limit %s · sold %s · over %s',
            $tier['label'],
            number_format($tier['limit'], 2),
            number_format($tier['used'], 2),
            number_format($tier['over'], 2)
        ), $breaches);

        return ' · ABOVE AMOUNT LIMIT: '.implode(' | ', $parts);
    }

    public function offlineShell(Request $request, AgentSession $agentSession): View
    {
        abort_unless($request->user()->isOperator(), 403);
        $this->authorizeSession($request, $agentSession, true);

        return view('operator.sales.offline', [
            'agentSession' => $agentSession->load(['agent', 'round']),
        ]);
    }

    public function store(
        Request $request,
        AgentSession $agentSession,
        SaleProcessor $processor,
        ThreeDigitSaleProcessor $threeDigitProcessor
    ): JsonResponse|RedirectResponse {
        $this->authorizeSession($request, $agentSession, true);
        abort_unless($agentSession->status === 'open' && $agentSession->round->status === 'open', 409, 'This Round is closed to sales.');

        $validated = $request->validate([
            'input' => ['required', 'string', 'max:255'],
            'sale_type' => ['nullable', 'in:2d,3d'],
            'client_uuid' => ['nullable', 'uuid'],
        ]);

        try {
            if (($validated['sale_type'] ?? '2d') === '3d') {
                $saleInput = $threeDigitProcessor->process(
                    $validated['input'],
                    $agentSession,
                    $validated['client_uuid'] ?? null
                );
                $acceptedNumbers = $saleInput->details()
                    ->where('status', 'accepted')
                    ->where('is_excluded', false)
                    ->count();
                $rejectedNumbers = $saleInput->details()->where('status', 'rejected')->count();
                $acceptedAmount = (float) $saleInput->details()->where('status', 'accepted')->sum('amount');

                $status = sprintf(
                    '3D %s · %d accepted number(s), %d rejected. Accepted amount: %s',
                    ucfirst($saleInput->status),
                    $acceptedNumbers,
                    $rejectedNumbers,
                    number_format($acceptedAmount, 2)
                ).$this->amountLimitNotice($agentSession->round, $agentSession->agent_id, $agentSession->operator_id, '3d');

                if ($request->expectsJson()) {
                    return response()->json([
                        'status' => $saleInput->status,
                        'message' => $status,
                        'details' => $saleInput->details()->get(['number', 'amount', 'status', 'reject_reason']),
                    ]);
                }

                return redirect()->route('operator.sales.show', [$agentSession, 'mode' => '3d'])->with('status', $status);
            }

            $saleInput = $processor->process($validated['input'], $agentSession, $validated['client_uuid'] ?? null);
        } catch (InvalidArgumentException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return back()
                ->withErrors(['input' => $exception->getMessage()])
                ->withInput();
        } catch (RuntimeException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 409);
            }

            return back()->withErrors(['input' => $exception->getMessage()]);
        }

        $acceptedNumbers = $saleInput->details()->where('status', 'accepted')->count();
        $rejectedNumbers = $saleInput->details()->where('status', 'rejected')->count();
        $notice = $this->amountLimitNotice($agentSession->round, $agentSession->agent_id, $agentSession->operator_id, '2d');

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $saleInput->status,
                'message' => sprintf(
                    '%s · %d accepted number(s), %d rejected.',
                    ucfirst($saleInput->status),
                    $acceptedNumbers,
                    $rejectedNumbers
                ).$notice,
                'details' => $saleInput->details()->get(['number', 'amount', 'status', 'reject_reason', 'is_excluded']),
            ]);
        }

        return redirect()->route('operator.sales.show', $agentSession)
            ->with('status', sprintf(
                '%s · %d accepted number(s), %d rejected. Accepted amount: %s',
                ucfirst($saleInput->status),
                $acceptedNumbers,
                $rejectedNumbers,
                number_format((float) $saleInput->details()->where('status', 'accepted')->where('is_excluded', false)->sum('amount'), 2)
            ).$notice);
    }

    public function storeBatch(
        Request $request,
        AgentSession $agentSession,
        SaleProcessor $processor,
        ThreeDigitSaleProcessor $threeDigitProcessor
    ): JsonResponse {
        $this->authorizeSession($request, $agentSession, true);
        abort_unless($agentSession->status === 'open' && $agentSession->round->status === 'open', 409, 'This Round is closed to sales.');

        $validated = $request->validate([
            'sale_type' => ['nullable', 'in:2d,3d'],
            'inputs' => ['required', 'array', 'min:1', 'max:200'],
            'inputs.*.input' => ['required', 'string', 'max:255'],
            'inputs.*.client_uuid' => ['nullable', 'uuid'],
        ]);

        $saleMode = $validated['sale_type'] ?? '2d';
        $results = [];

        foreach ($validated['inputs'] as $item) {
            $clientUuid = $item['client_uuid'] ?? null;

            try {
                if ($saleMode === '3d') {
                    $saleInput = $threeDigitProcessor->process($item['input'], $agentSession, $clientUuid);
                    $details = $saleInput->details()->get(['number', 'amount', 'status', 'reject_reason']);
                } else {
                    $saleInput = $processor->process($item['input'], $agentSession, $clientUuid);
                    $details = $saleInput->details()->get(['number', 'amount', 'status', 'reject_reason', 'is_excluded']);
                }
            } catch (InvalidArgumentException $exception) {
                $results[] = ['client_uuid' => $clientUuid, 'status' => 'invalid', 'message' => $exception->getMessage()];
                continue;
            } catch (RuntimeException $exception) {
                $results[] = ['client_uuid' => $clientUuid, 'status' => 'error', 'message' => $exception->getMessage()];
                continue;
            }

            $results[] = [
                'client_uuid' => $clientUuid,
                'status' => 'saved',
                'message' => sprintf(
                    '%s · %d accepted number(s), %d rejected.',
                    ucfirst($saleInput->status),
                    $details->where('status', 'accepted')->count(),
                    $details->where('status', 'rejected')->count()
                ),
                'details' => $details->values(),
            ];
        }

        return response()->json([
            'results' => $results,
            'amount_limit_breaches' => $this->amountLimits->breaches(
                $agentSession->round,
                $agentSession->agent_id,
                $agentSession->operator_id,
                $saleMode
            ),
        ]);
    }

    private function soldItemsForSession(int $agentSessionId, string $saleMode): array
    {
        if ($saleMode === '3d') {
            return ThreeDigitSaleDetail::query()
                ->whereHas('saleInput', fn ($query) => $query->where('agent_session_id', $agentSessionId))
                ->where('status', 'accepted')
                ->select('number')
                ->selectRaw('SUM(amount) as amount')
                ->groupBy('number')
                ->orderByDesc('amount')
                ->orderBy('number')
                ->get()
                ->map(fn ($row): array => ['number' => (string) $row->number, 'amount' => (float) $row->amount])
                ->all();
        }

        return SaleDetail::query()
            ->whereHas('saleInput', fn ($query) => $query->where('agent_session_id', $agentSessionId))
            ->where('status', 'accepted')
            ->where('is_excluded', false)
            ->select('number')
            ->selectRaw('SUM(amount) as amount')
            ->groupBy('number')
            ->orderByDesc('amount')
            ->orderBy('number')
            ->get()
            ->map(fn ($row): array => ['number' => (string) $row->number, 'amount' => (float) $row->amount])
            ->all();
    }

    private function soldItemsForRound(int $roundId, string $saleMode): array
    {
        $details = $saleMode === '3d' ? ThreeDigitSaleDetail::query() : SaleDetail::query();

        return $details
            ->whereHas('saleInput', fn ($query) => $query->whereHas(
                'agentSession',
                fn ($session) => $session->where('round_id', $roundId)
            ))
            ->where('status', 'accepted')
            ->when($saleMode === '2d', fn ($query) => $query->where('is_excluded', false))
            ->select('number')
            ->selectRaw('SUM(amount) as amount')
            ->groupBy('number')
            ->orderByDesc('amount')
            ->orderBy('number')
            ->get()
            ->map(fn ($row): array => ['number' => (string) $row->number, 'amount' => (float) $row->amount])
            ->all();
    }

    /**
     * A session is readable by its handler and, for an Admin, by anyone in the
     * same business — that is how Agent sales uploaded from the phone reach the
     * Admin's own Sale Entry. Writing stays with the handler only, so an Admin
     * can read another Operator's Agent but never enter sales into it.
     */
    /**
     * One row per Agent that has sold in this Round, so the Admin's Sale Entry
     * reads as a list of Agent headings — the way an Operator sees the Agents
     * they handle. Mobile/offline uploads land here because they are written
     * against the Agent's own session.
     *
     * @return array<int, array{agent_id: int, agent_code: string, agent_name: string, handler: string, numbers: int, amount: float, url: string|null}>
     */
    private function agentBreakdown(Round $round, string $saleMode): array
    {
        $threeDigit = $saleMode === '3d';
        $detailTable = $threeDigit ? 'three_digit_sale_details' : 'sale_details';
        $inputTable = $threeDigit ? 'three_digit_sale_inputs' : 'sale_inputs';
        $inputKey = $threeDigit ? 'three_digit_sale_input_id' : 'sale_input_id';

        $totals = DB::table($detailTable)
            ->join($inputTable, $inputTable.'.id', '=', $detailTable.'.'.$inputKey)
            ->join('agent_sessions', 'agent_sessions.id', '=', $inputTable.'.agent_session_id')
            ->where('agent_sessions.round_id', $round->id)
            ->where($detailTable.'.status', 'accepted')
            ->when(! $threeDigit, fn ($query) => $query->where($detailTable.'.is_excluded', false))
            ->addSelect('agent_sessions.agent_id as agent_id')
            ->selectRaw('COUNT(*) as accepted_count, SUM('.$detailTable.'.amount) as accepted_amount')
            ->groupBy('agent_sessions.agent_id')
            ->get()
            ->keyBy('agent_id');

        $sessions = AgentSession::query()
            ->with(['agent', 'operator'])
            ->where('round_id', $round->id)
            ->orderBy('status')
            ->orderByDesc('id')
            ->get()
            ->groupBy('agent_id')
            ->map(fn ($group) => $group->first());

        return Agent::query()
            ->where('admin_id', $round->admin_id)
            ->orderBy('agent_code')
            ->get()
            ->map(function (Agent $agent) use ($totals, $sessions, $saleMode): ?array {
                $total = $totals->get($agent->id);
                $session = $sessions->get($agent->id);
                if ($total === null && $session === null) {
                    return null;
                }

                return [
                    'agent_id' => (int) $agent->id,
                    'agent_code' => $agent->agent_code,
                    'agent_name' => $agent->agent_name,
                    'handler' => $session?->operator?->name ?? '—',
                    'numbers' => (int) ($total?->accepted_count ?? 0),
                    'amount' => (float) ($total?->accepted_amount ?? 0),
                    'url' => $session === null ? null : route('operator.sales.show', [$session, 'mode' => $saleMode, 'view' => 'agent']),
                ];
            })
            ->filter()
            // stable chained sorts: Agent code first, then amount high→low wins
            ->sortBy('agent_code')
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    private function authorizeSession(Request $request, AgentSession $agentSession, bool $canWrite = false): void
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isOperator(), 403);

        $agentSession->loadMissing(['round', 'agent']);
        $adminId = $user->isAdmin() ? $user->id : $user->admin_id;
        $isHandler = (int) $agentSession->operator_id === (int) $user->id;

        abort_unless(
            (int) $agentSession->round->admin_id === (int) $adminId
            && (int) $agentSession->agent->admin_id === (int) $adminId
            && ($isHandler || ($user->isAdmin() && ! $canWrite)),
            404
        );

        abort_unless(
            AgentRoundSetting::query()
                ->where('agent_id', $agentSession->agent_id)
                ->where('round_id', $agentSession->round_id)
                ->where('operator_id', $agentSession->operator_id)
                ->exists(),
            404
        );
    }
}
