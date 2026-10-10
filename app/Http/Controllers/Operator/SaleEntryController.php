<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AgentRoundSetting;
use App\Models\AgentSession;
use App\Models\SaleDetail;
use App\Models\ThreeDigitSaleDetail;
use App\Services\RoundSettlementCalculator;
use App\Services\SaleProcessor;
use App\Services\ThreeDigitSaleProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class SaleEntryController extends Controller
{
    public function show(Request $request, AgentSession $agentSession, RoundSettlementCalculator $calculator): View
    {
        $this->authorizeSession($request, $agentSession);

        $saleMode = $request->query('mode', $request->session()->get('draw_mode', '2d'));
        abort_unless(in_array($saleMode, ['2d', '3d'], true), 404);
        $agentStatement = $request->query('view') === 'agent';
        $roundWide = $request->user()->isAdmin() && ! $agentStatement;
        $saleInputs = $agentSession->saleInputs()
            ->with('details')
            ->latest()
            ->limit(50)
            ->get();
        $threeDigitSaleInputs = $agentSession->threeDigitSaleInputs()
            ->with('details')
            ->latest()
            ->limit(50)
            ->get();

        $totalsQuery = SaleDetail::query()
            ->whereHas('saleInput', function ($query) use ($agentSession, $roundWide): void {
                if ($roundWide) {
                    $query->whereHas('agentSession', fn ($session) => $session->where('round_id', $agentSession->round_id));
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
            ->whereHas('saleInput', function ($query) use ($agentSession, $roundWide): void {
                if ($roundWide) {
                    $query->whereHas('agentSession', fn ($session) => $session->where('round_id', $agentSession->round_id));
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
        $agentChoices = collect();
        if ($request->user()->isAdmin() && $saleMode === '2d') {
            $adminId = (int) $request->user()->id;
            $agentChoices = Agent::query()
                ->where('admin_id', $adminId)
                ->where('status', 'active')
                ->with([
                    'roundSettings' => fn ($query) => $query
                        ->where('round_id', $agentSession->round_id)
                        ->with('operator'),
                    'sessions' => fn ($query) => $query
                        ->where('round_id', $agentSession->round_id)
                        ->orderByDesc('id')
                        ->with('operator'),
                ])
                ->orderBy('agent_code')
                ->get()
                ->map(function (Agent $agent) use ($agentSession, $adminId, $agentStatement): array {
                    $assignment = $agent->roundSettings->first();
                    $session = $agent->sessions->firstWhere('status', 'open')
                        ?? $agent->sessions->first();
                    $ownerId = $assignment?->operator_id ?? $session?->operator_id;
                    $isOwnedByAdmin = $ownerId !== null && (int) $ownerId === $adminId;
                    $isUnassigned = $ownerId === null;
                    $selectable = $isOwnedByAdmin || $isUnassigned;
                    $openForAdmin = $isOwnedByAdmin && $session?->status === 'open';

                    return [
                        'agent' => $agent,
                        'selected' => (int) $agent->id === (int) $agentSession->agent_id,
                        'selectable' => $selectable,
                        'url' => $openForAdmin
                            ? route('operator.sales.show', [
                                $session,
                                'mode' => '2d',
                                'view' => $agentStatement ? 'agent' : null,
                            ])
                            : null,
                        'claim_url' => $selectable && ! $openForAdmin
                            ? route('operator.agent-sessions.claim', [$agentSession->round_id, $agent])
                            : null,
                        'status' => ! $selectable
                            ? 'In use by '.($assignment?->operator?->name ?? $session?->operator?->name ?? 'another account')
                            : ($openForAdmin
                                ? 'Assigned to you'
                                : ($isOwnedByAdmin ? 'Reopen your assignment' : 'Available · claim to start')),
                    ];
                });
        }

        return view('operator.sales.show', [
            'agentSession' => $agentSession->load(['agent', 'round']),
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
            'availableSessions' => AgentSession::query()
                ->with(['agent', 'round'])
                ->where('operator_id', $request->user()->id)
                ->where('round_id', $agentSession->round_id)
                ->where('status', 'open')
                ->orderBy('agent_id')
                ->get(),
            'agentChoices' => $agentChoices,
            'soldItems' => $roundWide
                ? $this->soldItemsForRound($agentSession->round_id, $saleMode)
                : $this->soldItemsForSession($agentSession->id, $saleMode),
            'agentSoldItems' => $this->soldItemsForSession($agentSession->id, $saleMode),
            'amountLimit' => AgentRoundSetting::query()
                ->where('agent_id', $agentSession->agent_id)
                ->where('round_id', $agentSession->round_id)
                ->value($saleMode === '3d' ? 'amount_limit_3d' : 'amount_limit'),
            'numberLimits' => $agentSession->round->number_limits ?? [],
            'hotNumbers' => $agentSession->round->hotNumbers()->orderBy('number')->get(),
            'threeDigitAmountLimit' => (float) (AgentRoundSetting::query()
                ->where('agent_id', $agentSession->agent_id)
                ->where('round_id', $agentSession->round_id)
                ->value('amount_limit_3d') ?? 0),
            'canEnterSales' => $agentSession->status === 'open' && $agentSession->round->status === 'open',
            'ownSettlement' => $calculator->calculate($agentSession->round, (int) $request->user()->id)['agents']
                ->first(fn (array $row): bool => $row['agent']->id === $agentSession->agent_id),
        ]);
    }

    public function offlineShell(Request $request, AgentSession $agentSession): View
    {
        abort_unless($request->user()->isOperator(), 403);
        $this->authorizeSession($request, $agentSession);

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
        $this->authorizeSession($request, $agentSession);
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
                );

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

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $saleInput->status,
                'message' => sprintf(
                    '%s · %d accepted number(s), %d rejected.',
                    ucfirst($saleInput->status),
                    $acceptedNumbers,
                    $rejectedNumbers
                ),
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
            ));
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

    private function authorizeSession(Request $request, AgentSession $agentSession): void
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isOperator(), 403);

        $agentSession->loadMissing(['round', 'agent']);
        $adminId = $user->isAdmin() ? $user->id : $user->admin_id;
        abort_unless(
            (int) $agentSession->operator_id === (int) $user->id
            && (int) $agentSession->round->admin_id === (int) $adminId
            && (int) $agentSession->agent->admin_id === (int) $adminId,
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
