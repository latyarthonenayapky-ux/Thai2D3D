<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Agent;
use App\Models\AgentRoundSetting;
use App\Models\AgentSession;
use App\Models\OfflineSyncReview;
use App\Models\Round;
use App\Models\SaleDetail;
use App\Models\SaleInput;
use App\Models\ThreeDigitSaleDetail;
use App\Models\ThreeDigitSaleInput;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AgentSessionController extends AgentApiController
{
    public function open(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'round_id' => ['required', 'integer', 'min:1'],
        ]);

        $agent = $this->agent($request);

        $round = Round::query()
            ->whereKey($validated['round_id'])
            ->where('admin_id', $agent->admin_id)
            ->first();

        if (! $round) {
            return response()->json(['message' => 'This Round is not part of your business.'], 404);
        }

        $existing = AgentSession::query()
            ->with('round')
            ->where('agent_id', $agent->id)
            ->where('round_id', $round->id)
            ->where('status', 'open')
            ->first();

        if ($existing) {
            return response()->json([
                'session' => $this->sessionPayload($existing),
                'reused' => true,
            ]);
        }

        $operatorId = $this->resolveOperatorId($agent, $round);

        if (! $operatorId) {
            return response()->json([
                'message' => "No Operator is assigned to {$agent->agent_code} for Round {$round->round_no}. Ask the office to assign it first.",
            ], 422);
        }

        try {
            $session = AgentSession::openSession($agent, $round, $operatorId);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'session' => $this->sessionPayload($session->load('round')),
            'reused' => false,
        ], 201);
    }

    public function state(Request $request, AgentSession $agentSession): JsonResponse
    {
        $agent = $this->agent($request);
        $this->authorizeSession($agent, $agentSession);

        $twoDigitIds = SaleInput::query()
            ->where('agent_session_id', $agentSession->id)
            ->pluck('id');
        $threeDigitIds = ThreeDigitSaleInput::query()
            ->where('agent_session_id', $agentSession->id)
            ->pluck('id');

        $soldTwoDigit = SaleDetail::query()
            ->whereIn('sale_input_id', $twoDigitIds)
            ->where('status', 'accepted')
            ->sum('amount');
        $soldThreeDigit = ThreeDigitSaleDetail::query()
            ->whereIn('three_digit_sale_input_id', $threeDigitIds)
            ->where('status', 'accepted')
            ->sum('amount');

        $entries = SaleInput::query()
            ->whereIn('id', $twoDigitIds)
            ->get(['client_uuid', 'original_input', 'status', 'total_amount', 'created_at'])
            ->map(fn (SaleInput $sale): array => [
                'client_uuid' => $sale->client_uuid,
                'sale_type' => '2d',
                'input' => $sale->original_input,
                'status' => $sale->status,
                'total_amount' => (float) $sale->total_amount,
                'created_at' => optional($sale->created_at)->toIso8601String(),
            ])
            ->concat(ThreeDigitSaleInput::query()
                ->whereIn('id', $threeDigitIds)
                ->get(['client_uuid', 'original_input', 'status', 'total_amount', 'created_at'])
                ->map(fn (ThreeDigitSaleInput $sale): array => [
                    'client_uuid' => $sale->client_uuid,
                    'sale_type' => '3d',
                    'input' => $sale->original_input,
                    'status' => $sale->status,
                    'total_amount' => (float) $sale->total_amount,
                    'created_at' => optional($sale->created_at)->toIso8601String(),
                ]))
            ->sortByDesc('created_at')
            ->take(20)
            ->values();

        return response()->json([
            'session' => $this->sessionPayload($agentSession->load('round')),
            'totals' => [
                'entries_2d' => $twoDigitIds->count(),
                'entries_3d' => $threeDigitIds->count(),
                'sold_amount_2d' => (float) $soldTwoDigit,
                'sold_amount_3d' => (float) $soldThreeDigit,
                'awaiting_review' => OfflineSyncReview::query()
                    ->where('agent_session_id', $agentSession->id)
                    ->where('status', 'pending')
                    ->count(),
            ],
            'entries' => $entries->all(),
        ]);
    }

    /**
     * Mobile sign-in carries no Operator account, so the sale is attributed to
     * the Operator already holding this Agent for the Round.
     */
    private function resolveOperatorId(Agent $agent, Round $round): ?int
    {
        $assigned = AgentRoundSetting::query()
            ->where('agent_id', $agent->id)
            ->where('round_id', $round->id)
            ->whereNotNull('operator_id')
            ->value('operator_id');

        $candidateId = $assigned ?? AgentSession::query()
            ->where('agent_id', $agent->id)
            ->latest('id')
            ->value('operator_id');

        if (! $candidateId) {
            return null;
        }

        $handler = User::query()->find($candidateId);

        return $handler?->status ? (int) $candidateId : null;
    }

    private function authorizeSession(Agent $agent, AgentSession $session): void
    {
        abort_unless((int) $session->agent_id === (int) $agent->id, 404);
    }
}
