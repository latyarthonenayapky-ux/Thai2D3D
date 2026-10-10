<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentRoundSetting;
use App\Models\AgentSession;
use App\Models\Round;
use App\Models\ThreeDigitDraw;
use App\Models\ThreeDigitDrawAgentSetting;
use App\Models\ThreeDigitDrawHotNumber;
use App\Models\ThreeDigitHotNumber;
use App\Models\ThreeDigitSaleDetail;
use App\Models\ThreeDigitSaleInput;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ThreeDigitSaleProcessor
{
    public function __construct(
        protected ThreeDigitInputParser $parser
    ) {}

    public function process(string $input, AgentSession $session, ?string $clientUuid = null): ThreeDigitSaleInput
    {
        return DB::transaction(function () use ($input, $session, $clientUuid): ThreeDigitSaleInput {
            if ($clientUuid !== null) {
                $existing = ThreeDigitSaleInput::query()
                    ->where('operator_id', $session->operator_id)
                    ->where('client_uuid', $clientUuid)
                    ->first();
                if ($existing) {
                    return $existing->load('details');
                }
            }

            $round = Round::query()->whereKey($session->round_id)->lockForUpdate()->first();
            if (! $round || $round->status !== 'open') {
                throw new RuntimeException('Round is not open.');
            }

            $lockedSession = AgentSession::query()->lockForUpdate()->find($session->id);
            if (! $lockedSession || $lockedSession->status !== 'open') {
                throw new RuntimeException('Agent session is not open.');
            }

            $handler = $lockedSession->operator;
            $agent = $lockedSession->agent;
            $belongsToBusiness = $handler?->isAdmin()
                ? (int) $handler->id === (int) $round->admin_id
                : $handler?->isOperator() && (int) $handler->admin_id === (int) $round->admin_id;
            if (
                ! $handler
                || ! $handler->status
                || ! $belongsToBusiness
                || ! $agent
                || (int) $agent->admin_id !== (int) $round->admin_id
            ) {
                throw new RuntimeException('This Agent session does not belong to the handler’s business.');
            }

            $assigned = AgentRoundSetting::query()
                ->where('agent_id', $lockedSession->agent_id)
                ->where('round_id', $round->id)
                ->where('operator_id', $lockedSession->operator_id)
                ->exists();
            if (! $assigned) {
                throw new RuntimeException('This account does not own the Agent assignment for this Round.');
            }

            if ($round->number_limit_3d === null || $round->number_limit_3d < 1) {
                throw new RuntimeException('The Admin has not configured a 3D Number Limit for this Round.');
            }

            $parsed = $this->parser->parse($input);
            $hotNumbers = ThreeDigitHotNumber::query()
                ->where('round_id', $round->id)
                ->pluck('number')
                ->all();
            $eligibleNumbers = array_values(array_filter(
                $parsed['numbers'],
                fn (string $number): bool => ! in_array($number, $hotNumbers, true)
            ));
            $acceptedCount = ThreeDigitSaleDetail::query()
                ->whereHas('saleInput.agentSession', fn ($query) => $query->where('round_id', $round->id))
                ->where('status', 'accepted')
                ->count();
            $exceedsLimit = $acceptedCount + count($eligibleNumbers) > $round->number_limit_3d;

            $saleInput = ThreeDigitSaleInput::query()->create([
                'agent_session_id' => $lockedSession->id,
                'operator_id' => $lockedSession->operator_id,
                'client_uuid' => $clientUuid,
                'original_input' => $parsed['original_input'],
                'normalized_input' => $parsed['normalized_input'],
                'input_type' => $parsed['type'],
                'code' => $parsed['code'],
                'number_count' => $parsed['number_count'],
                'amount' => $parsed['amount'],
                'total_amount' => $parsed['total_amount'],
                'status' => 'accepted',
            ]);

            foreach ($parsed['numbers'] as $number) {
                $isHot = in_array($number, $hotNumbers, true);
                $isRejected = $isHot || $exceedsLimit;
                ThreeDigitSaleDetail::query()->create([
                    'three_digit_sale_input_id' => $saleInput->id,
                    'number' => $number,
                    'amount' => $parsed['amount'],
                    'status' => $isRejected ? 'rejected' : 'accepted',
                    'reject_reason' => $isHot ? 'HOT_NUMBER_3D' : ($exceedsLimit ? 'NUMBER_LIMIT_3D' : null),
                ]);
            }

            if ($exceedsLimit) {
                $saleInput->update([
                    'status' => 'rejected',
                    'reject_reason' => 'NUMBER_LIMIT_3D',
                ]);
            } elseif (count($eligibleNumbers) === 0) {
                $saleInput->update([
                    'status' => 'rejected',
                    'reject_reason' => 'ALL_NUMBERS_REJECTED',
                ]);
            }

            return $saleInput->fresh('details');
        });
    }

    public function processForDraw(
        string $input,
        ThreeDigitDraw $draw,
        Agent $agent,
        User $handler,
        ?string $clientUuid = null
    ): ThreeDigitSaleInput {
        return DB::transaction(function () use ($input, $draw, $agent, $handler, $clientUuid): ThreeDigitSaleInput {
            $lockedDraw = ThreeDigitDraw::query()->whereKey($draw->id)->lockForUpdate()->firstOrFail();
            $lockedHandler = User::query()->whereKey($handler->id)->lockForUpdate()->firstOrFail();
            $lockedAgent = Agent::query()->whereKey($agent->id)->lockForUpdate()->firstOrFail();
            $belongsToBusiness = $lockedHandler->isAdmin()
                ? (int) $lockedHandler->id === (int) $lockedDraw->admin_id
                : $lockedHandler->isOperator() && (int) $lockedHandler->admin_id === (int) $lockedDraw->admin_id;
            if (
                ! $lockedHandler->status
                || ! $belongsToBusiness
                || (int) $lockedAgent->admin_id !== (int) $lockedDraw->admin_id
                || $lockedAgent->status !== 'active'
            ) {
                throw new RuntimeException('This Agent and account do not belong to the 3D Draw business.');
            }

            if ($clientUuid !== null) {
                $existing = ThreeDigitSaleInput::query()
                    ->where('operator_id', $lockedHandler->id)
                    ->where('client_uuid', $clientUuid)
                    ->first();
                if ($existing) {
                    if (
                        (int) $existing->three_digit_draw_id !== (int) $lockedDraw->id
                        || (int) $existing->agent_id !== (int) $lockedAgent->id
                    ) {
                        throw new RuntimeException('This input ID was already used for a different Agent or 3D Draw.');
                    }

                    return $existing->load('details');
                }
            }

            $now = now('Asia/Yangon');
            $drawStartsAt = $lockedDraw->draw_date->toDateString().' '.$lockedDraw->open_time;
            $drawClosesAt = $lockedDraw->draw_date->toDateString().' '.$lockedDraw->result_time;
            if (
                $lockedDraw->status !== 'open'
                || $now->lessThan($drawStartsAt)
                || $now->greaterThanOrEqualTo($drawClosesAt)
            ) {
                throw new RuntimeException('This 3D Draw is not open for sales.');
            }

            $setting = ThreeDigitDrawAgentSetting::query()
                ->where('three_digit_draw_id', $lockedDraw->id)
                ->where('agent_id', $lockedAgent->id)
                ->lockForUpdate()
                ->first();
            if (! $setting || (int) $setting->handler_id !== (int) $lockedHandler->id) {
                throw new RuntimeException('This account does not own the Agent assignment for this 3D Draw.');
            }
            if ($lockedDraw->number_limit === null || $lockedDraw->number_limit < 1) {
                throw new RuntimeException('The Admin has not configured this 3D Draw Number Limit.');
            }

            $parsed = $this->parser->parse($input);
            $hotNumbers = ThreeDigitDrawHotNumber::query()
                ->where('three_digit_draw_id', $lockedDraw->id)
                ->pluck('number')
                ->all();
            $eligibleNumbers = array_values(array_filter(
                $parsed['numbers'],
                fn (string $number): bool => ! in_array($number, $hotNumbers, true)
            ));
            $acceptedCount = ThreeDigitSaleDetail::query()
                ->whereHas('saleInput', fn ($query) => $query->where('three_digit_draw_id', $lockedDraw->id))
                ->where('status', 'accepted')
                ->count();
            $exceedsLimit = $acceptedCount + count($eligibleNumbers) > $lockedDraw->number_limit;

            $saleInput = ThreeDigitSaleInput::query()->create([
                'agent_session_id' => null,
                'three_digit_draw_id' => $lockedDraw->id,
                'agent_id' => $lockedAgent->id,
                'draw_agent_setting_id' => $setting->id,
                'operator_id' => $lockedHandler->id,
                'client_uuid' => $clientUuid,
                'original_input' => $parsed['original_input'],
                'normalized_input' => $parsed['normalized_input'],
                'input_type' => $parsed['type'],
                'code' => $parsed['code'],
                'number_count' => $parsed['number_count'],
                'amount' => $parsed['amount'],
                'total_amount' => $parsed['total_amount'],
                'status' => 'accepted',
            ]);

            foreach ($parsed['numbers'] as $number) {
                $isHot = in_array($number, $hotNumbers, true);
                $isRejected = $isHot || $exceedsLimit;
                ThreeDigitSaleDetail::query()->create([
                    'three_digit_sale_input_id' => $saleInput->id,
                    'number' => $number,
                    'amount' => $parsed['amount'],
                    'status' => $isRejected ? 'rejected' : 'accepted',
                    'reject_reason' => $isHot ? 'HOT_NUMBER_3D' : ($exceedsLimit ? 'NUMBER_LIMIT_3D' : null),
                ]);
            }

            if ($exceedsLimit) {
                $saleInput->update(['status' => 'rejected', 'reject_reason' => 'NUMBER_LIMIT_3D']);
            } elseif ($eligibleNumbers === []) {
                $saleInput->update(['status' => 'rejected', 'reject_reason' => 'ALL_NUMBERS_REJECTED']);
            }

            return $saleInput->fresh('details');
        });
    }
}
