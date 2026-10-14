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
use Illuminate\Support\Facades\Lang;
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
            'numberAmountLimit' => $this->amountLimits->twoDigitCap($round),
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
     * Round-wide per-number Amount Limit breaches, in the shape the warning
     * banner and the coloured amounts already read.
     *
     * @return array<int, array{key: string, label: string, limit: float, used: float, over: float, mode: string}>
     */
    private function bannerBreaches(Round $round, ?AgentSession $agentSession, bool $awaitingAgent, string $saleMode): array
    {
        if ($saleMode === '2d') {
            return array_map(fn (array $row): array => [
                'key' => 'number',
                'label' => 'No. '.$row['number'],
                'limit' => $row['limit'],
                'used' => $row['sold'],
                'over' => $row['excess'],
                'mode' => AmountLimitPolicy::MODE_WARN,
            ], $this->amountLimits->numberBreaches($round));
        }

        return $awaitingAgent
            ? $this->amountLimits->breaches($round, null, null, '3d')
            : $this->amountLimits->breaches($round, $agentSession->agent_id, $agentSession->operator_id, '3d');
    }

    /**
     * What each sale channel has recorded in this view, and the detail lists
     * behind its figures. 'phone' is the Agent app's auto-upload, 'web' is this
     * screen — the office needs the two apart because they arrive at different
     * times and are keyed by different hands. Every figure answers the same
     * question: which Agent, on which date and Round, put how much on which
     * number.
     *
     * @return array{phone: array<string, mixed>, web: array<string, mixed>}
     */
    private function channelBreakdown(Round $round, ?AgentSession $agentSession, bool $roundWide, string $saleMode): array
    {
        $threeDigit = $saleMode === '3d';
        $scope = function ($query) use ($agentSession, $round, $roundWide): void {
            if ($roundWide) {
                $query->where('rounds.id', $round->id);
            } else {
                $query->where('agent_sessions.id', $agentSession?->id ?? 0);
            }
        };
        $detail = $threeDigit ? 'three_digit_sale_details' : 'sale_details';
        $input = $threeDigit ? 'three_digit_sale_inputs' : 'sale_inputs';
        $inputId = $threeDigit ? 'three_digit_sale_input_id' : 'sale_input_id';

        $rows = function (array $statuses, bool $reason = false) use ($threeDigit, $detail, $input, $inputId, $scope): Collection {
            $query = $threeDigit
                ? ThreeDigitSaleDetail::query()
                : SaleDetail::query();
            $query
                ->join($input, $input.'.id', '=', $detail.'.'.$inputId)
                ->join('agent_sessions', 'agent_sessions.id', '=', $input.'.agent_session_id')
                ->join('agents', 'agents.id', '=', 'agent_sessions.agent_id')
                ->join('rounds', 'rounds.id', '=', 'agent_sessions.round_id')
                ->whereIn($detail.'.status', $statuses)
                ->when($threeDigit === false, fn ($q) => $q->where($detail.'.is_excluded', false))
                ->select([
                    'rounds.round_date as round_date',
                    'rounds.round_no as round_no',
                    'agents.agent_code as agent_code',
                    'agents.agent_name as agent_name',
                    $detail.'.number as number',
                    $input.'.source as source',
                ])
                ->selectRaw('COUNT(*) as number_count, SUM('.$detail.'.amount) as total_amount');
            if ($reason) {
                $column = $detail.'.reject_reason';
                $query->addSelect(DB::raw($column.' as reason'))->groupBy($column);
            }
            $query->groupBy('rounds.round_date', 'rounds.round_no', 'agents.agent_code', 'agents.agent_name', $detail.'.number', $input.'.source');
            $scope($query);

            return $query->get()->map(fn ($row): array => $this->channelRow($row, $reason));
        };

        $accepted = $rows(['accepted']);
        $rejected = $rows(['rejected'], true);

        $breakdown = [];
        foreach (['phone', 'web'] as $source) {
            $acceptedRows = $this->channelSubset($accepted, $source);
            $rejectedRows = $this->channelSubset($rejected, $source);

            $breakdown[$source] = [
                'accepted_numbers' => (int) array_sum(array_column($acceptedRows, 'count')),
                'accepted_amount' => round(array_sum(array_column($acceptedRows, 'amount')), 2),
                'rejected_numbers' => (int) array_sum(array_column($rejectedRows, 'count')),
                'numbers' => $acceptedRows,
                'rejected' => $rejectedRows,
            ];
        }

        return $breakdown;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function channelSubset(Collection $rows, string $source): array
    {
        return $rows->where('source', $source)
            ->sortBy([['agent', 'asc'], ['date', 'desc'], ['amount', 'desc']])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function channelRow(object $row, bool $reason): array
    {
        return [
            'date' => substr((string) $row->round_date, 0, 10),
            'round_no' => (int) $row->round_no,
            'agent' => trim($row->agent_code.' · '.(string) $row->agent_name),
            'number' => str_pad((string) $row->number, 2, '0', STR_PAD_LEFT),
            'count' => (int) $row->number_count,
            'amount' => round((float) $row->total_amount, 2),
            'source' => (string) ($row->source ?? 'web'),
            'reason' => $reason ? $this->rejectReasonLabel((string) ($row->reason ?? '')) : null,
        ];
    }

    private function rejectReasonLabel(string $reason): string
    {
        if ($reason === '') {
            return __('app.sale.status_rejected');
        }

        $key = 'app.sale.reason_'.strtolower($reason);

        return Lang::has($key) ? __($key) : str_replace('_', ' ', $reason);
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
            'agentOptions' => $agentOptions,
            'selectableCount' => $agentOptions->where('selectable', true)->count(),
            'numberLimits' => $round->number_limits ?? [],
            'hotNumbers' => $round->hotNumbers()->orderBy('number')->get(),
            'overLimitRows' => $saleMode === '2d' ? $this->amountLimits->numberBreaches($round) : [],
            'numberAmountLimit' => $saleMode === '2d' ? $this->amountLimits->twoDigitCap($round) : null,
            'channelBreakdown' => $this->channelBreakdown($round, $agentSession, $roundWide, $saleMode),
            'amountLimitBreaches' => $this->bannerBreaches($round, $agentSession, $awaitingAgent, $saleMode),
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
            'ownSettlement' => $awaitingAgent ? null : $calculator->calculate($round, (int) $user->id)['agents']
                ->first(fn (array $row): bool => $row['agent']->id === $agentSession->agent_id),
        ]);
    }

    /**
     * "Accepted but over the Amount Limit" sentence for the alert bar and the
     * save confirmation. 2D reads it from the Round's per-number caps, 3D from
     * its tiers. Empty while nothing has passed a cap, so a warning never adds
     * noise.
     */
    private function amountLimitNotice(Round $round, ?int $agentId, ?int $operatorId, string $saleMode): string
    {
        if ($saleMode === '2d') {
            $parts = array_map(fn (array $row): string => sprintf(
                'No. %s · limit %s · sold %s · over %s',
                $row['number'],
                number_format($row['limit'], 2),
                number_format($row['sold'], 2),
                number_format($row['excess'], 2)
            ), $this->amountLimits->numberBreaches($round));

            return $parts === [] ? '' : ' · ABOVE AMOUNT LIMIT: '.implode(' | ', $parts);
        }

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
            'amount_limit_breaches' => $this->bannerBreaches($agentSession->round, $agentSession, false, $saleMode),
            'over_limit_rows' => $saleMode === '2d'
                ? $this->amountLimits->numberBreaches($agentSession->round)
                : [],
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
