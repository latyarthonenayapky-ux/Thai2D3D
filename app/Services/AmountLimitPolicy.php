<?php

namespace App\Services;

use App\Exceptions\AmountLimitExceededException;
use App\Models\AgentRoundSetting;
use App\Models\OperatorRoundSetting;
use App\Models\Round;
use App\Models\SaleDetail;
use App\Models\ThreeDigitSaleDetail;

/**
 * Amount Limit is monitored on three tiers for one Round:
 * - Agent    — agent_round_settings.amount_limit / amount_limit_3d
 * - Operator — operator_round_settings.amount_limit / amount_limit_3d
 * - Round    — rounds.amount_limit_all / amount_limit_all_3d (all Agents and Operators together)
 *
 * A tier counts the money already accepted in that scope, so voided and
 * excluded numbers never consume the limit. An unset limit means the tier is
 * not monitored. Each tier has a mode: 'warn' accepts the sale and flags it
 * (the default — the limit is a pre-agreed credit ceiling), 'block' refuses a
 * sale that would pass it.
 */
class AmountLimitPolicy
{
    public const TIER_AGENT = 'agent';

    public const TIER_OPERATOR = 'operator';

    public const TIER_ROUND = 'round';

    public const MODE_WARN = 'warn';

    public const MODE_BLOCK = 'block';

    public static function normalizeMode(mixed $mode): string
    {
        return $mode === self::MODE_BLOCK ? self::MODE_BLOCK : self::MODE_WARN;
    }

    public function assertWithinLimits(
        Round $round,
        int $agentId,
        int $operatorId,
        float $incoming,
        string $mode = '2d'
    ): void {
        if ($incoming <= 0) {
            return;
        }

        foreach ($this->tiers($round, $agentId, $operatorId, $mode) as $tier) {
            if ($tier['limit'] === null || $tier['mode'] !== self::MODE_BLOCK) {
                continue;
            }

            if (round($tier['used'] + $incoming, 2) <= round((float) $tier['limit'], 2)) {
                continue;
            }

            throw new AmountLimitExceededException(
                $tier['key'],
                $tier['label'],
                (float) $tier['limit'],
                $tier['used'],
                $incoming
            );
        }
    }

    /**
     * Tiers whose accepted total has already passed the limit, whatever the
     * mode — this is what the warning banner and the coloured amounts read.
     *
     * @return array<int, array{key: string, label: string, limit: float, used: float, over: float, mode: string}>
     */
    public function breaches(Round $round, ?int $agentId, ?int $operatorId, string $mode = '2d'): array
    {
        return collect($this->tiers($round, (int) $agentId, (int) $operatorId, $mode))
            ->filter(fn (array $tier): bool => $tier['limit'] !== null && round($tier['used'], 2) > round($tier['limit'], 2))
            ->map(function (array $tier): array {
                $tier['over'] = round($tier['used'] - $tier['limit'], 2);

                return $tier;
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{key: string, label: string, limit: float|null, used: float, mode: string}>
     */
    public function tiers(Round $round, int $agentId, int $operatorId, string $mode = '2d'): array
    {
        $threeDigit = $mode === '3d';
        $column = $threeDigit ? 'amount_limit_3d' : 'amount_limit';
        $modeWord = $threeDigit ? '3D ' : '';

        $agentSetting = AgentRoundSetting::query()
            ->where('round_id', $round->id)
            ->where('agent_id', $agentId)
            ->first([$column, 'amount_limit_mode']);

        $operatorSetting = OperatorRoundSetting::query()
            ->where('round_id', $round->id)
            ->where('operator_id', $operatorId)
            ->first([$column, 'amount_limit_mode']);

        return [
            [
                'key' => self::TIER_AGENT,
                'label' => "{$modeWord}Agent Amount Limit",
                'limit' => $agentSetting?->{$column} === null ? null : (float) $agentSetting->{$column},
                'used' => $threeDigit
                    ? $this->threeDigitTotal($round, $agentId, null)
                    : $this->twoDigitTotal($round, $agentId, null),
                'mode' => self::normalizeMode($agentSetting?->amount_limit_mode),
            ],
            [
                'key' => self::TIER_OPERATOR,
                'label' => "{$modeWord}Operator Amount Limit",
                'limit' => $operatorSetting?->{$column} === null ? null : (float) $operatorSetting->{$column},
                'used' => $threeDigit
                    ? $this->threeDigitTotal($round, null, $operatorId)
                    : $this->twoDigitTotal($round, null, $operatorId),
                'mode' => self::normalizeMode($operatorSetting?->amount_limit_mode),
            ],
            [
                'key' => self::TIER_ROUND,
                'label' => "{$modeWord}Round Amount Limit (all Agents and Operators)",
                'limit' => $round->{$threeDigit ? 'amount_limit_all_3d' : 'amount_limit_all'} === null
                    ? null
                    : (float) $round->{$threeDigit ? 'amount_limit_all_3d' : 'amount_limit_all'},
                'used' => $threeDigit
                    ? $this->threeDigitTotal($round, null, null)
                    : $this->twoDigitTotal($round, null, null),
                'mode' => self::normalizeMode($round->amount_limit_all_mode),
            ],
        ];
    }

    private function twoDigitTotal(Round $round, ?int $agentId, ?int $operatorId): float
    {
        return (float) SaleDetail::query()
            ->join('sale_inputs', 'sale_inputs.id', '=', 'sale_details.sale_input_id')
            ->join('agent_sessions', 'agent_sessions.id', '=', 'sale_inputs.agent_session_id')
            ->where('agent_sessions.round_id', $round->id)
            ->where('sale_details.status', 'accepted')
            ->where('sale_details.is_excluded', false)
            ->when($agentId !== null, fn ($query) => $query->where('agent_sessions.agent_id', $agentId))
            ->when($operatorId !== null, fn ($query) => $query->where('sale_inputs.operator_id', $operatorId))
            ->sum('sale_details.amount');
    }

    private function threeDigitTotal(Round $round, ?int $agentId, ?int $operatorId): float
    {
        return (float) ThreeDigitSaleDetail::query()
            ->join('three_digit_sale_inputs', 'three_digit_sale_inputs.id', '=', 'three_digit_sale_details.three_digit_sale_input_id')
            ->join('agent_sessions', 'agent_sessions.id', '=', 'three_digit_sale_inputs.agent_session_id')
            ->where('agent_sessions.round_id', $round->id)
            ->where('three_digit_sale_details.status', 'accepted')
            ->when($agentId !== null, fn ($query) => $query->where('agent_sessions.agent_id', $agentId))
            ->when($operatorId !== null, fn ($query) => $query->where('three_digit_sale_inputs.operator_id', $operatorId))
            ->sum('three_digit_sale_details.amount');
    }
}
