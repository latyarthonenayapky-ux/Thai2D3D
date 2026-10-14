<?php

namespace App\Services;

use App\Exceptions\AmountLimitExceededException;
use App\Models\AgentRoundSetting;
use App\Models\OperatorRoundSetting;
use App\Models\Round;
use App\Models\SaleDetail;
use App\Models\ThreeDigitSaleDetail;

/**
 * 2D Amount Limit is ONE figure per Round — rounds.amount_limit_all — and it
 * applies to every number 00–99, counting every Agent and Operator under that
 * Round's Admin together. Passing it is allowed — numberBreaches() lists the
 * over-cap numbers for review, so 2D sales never call assertWithinLimits().
 *
 * 3D keeps the three scalar tiers:
 * - Agent    — agent_round_settings.amount_limit_3d
 * - Operator — operator_round_settings.amount_limit_3d
 * - Round    — rounds.amount_limit_all_3d (all Agents and Operators together)
 *
 * A tier counts the money already accepted in that scope, so voided numbers
 * never consume the limit. An unset limit means the tier is not monitored. Each
 * tier has a mode: 'warn' accepts the sale and flags it (the default — the
 * limit is a pre-agreed credit ceiling), 'block' refuses a sale that would pass
 * it.
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
        string $mode = '3d'
    ): void {
        if ($mode !== '3d' || $incoming <= 0) {
            return;
        }

        foreach ($this->tiers($round, $agentId, $operatorId) as $tier) {
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
     * 3D tiers whose accepted total has already passed the limit, whatever the
     * mode — this is what the warning banner and the coloured amounts read.
     * 2D is monitored through numberBreaches() instead, so it reports no tier
     * breaches here.
     *
     * @return array<int, array{key: string, label: string, limit: float, used: float, over: float, mode: string}>
     */
    public function breaches(Round $round, ?int $agentId, ?int $operatorId, string $mode = '2d'): array
    {
        if ($mode !== '3d') {
            return [];
        }

        return collect($this->tiers($round, (int) $agentId, (int) $operatorId))
            ->filter(fn (array $tier): bool => $tier['limit'] !== null && round($tier['used'], 2) > round((float) $tier['limit'], 2))
            ->map(function (array $tier): array {
                $tier['over'] = round($tier['used'] - $tier['limit'], 2);

                return $tier;
            })
            ->values()
            ->all();
    }

    /**
     * Every number whose round-wide accepted total has passed the Round's single
     * 2D Amount Limit.
     *
     * @return array<int, array{number: string, limit: float, sold: float, excess: float}>
     */
    public function numberBreaches(Round $round): array
    {
        $cap = $round->amount_limit_all === null ? null : round((float) $round->amount_limit_all, 2);

        if ($cap === null) {
            return [];
        }

        // At most one row per number 00–99, so the cap is compared in PHP —
        // HAVING with a bound float is compared as text on SQLite.
        $sold = SaleDetail::query()
            ->join('sale_inputs', 'sale_inputs.id', '=', 'sale_details.sale_input_id')
            ->join('agent_sessions', 'agent_sessions.id', '=', 'sale_inputs.agent_session_id')
            ->where('agent_sessions.round_id', $round->id)
            ->where('sale_details.status', 'accepted')
            ->where('sale_details.is_excluded', false)
            ->select('sale_details.number as number')
            ->selectRaw('SUM(sale_details.amount) as total_amount')
            ->groupBy('sale_details.number')
            ->get();

        return $sold
            ->map(fn ($row): array => [
                'number' => str_pad((string) $row->number, 2, '0', STR_PAD_LEFT),
                'limit' => $cap,
                'sold' => round((float) $row->total_amount, 2),
                'excess' => round((float) $row->total_amount - $cap, 2),
            ])
            ->filter(fn (array $row): bool => $row['sold'] > $cap)
            // stable chained sorts: number first, then excess high→low wins
            ->sortBy('number')
            ->sortByDesc('excess')
            ->values()
            ->all();
    }

    /**
     * The Round's single 2D cap, or null when the Round is uncapped.
     */
    public function twoDigitCap(Round $round): ?float
    {
        return $round->amount_limit_all === null ? null : round((float) $round->amount_limit_all, 2);
    }

    /**
     * @return array<int, array{key: string, label: string, limit: float|null, used: float, mode: string}>
     */
    private function tiers(Round $round, int $agentId, int $operatorId): array
    {
        $agentSetting = AgentRoundSetting::query()
            ->where('round_id', $round->id)
            ->where('agent_id', $agentId)
            ->first(['amount_limit_3d', 'amount_limit_mode']);

        $operatorSetting = OperatorRoundSetting::query()
            ->where('round_id', $round->id)
            ->where('operator_id', $operatorId)
            ->first(['amount_limit_3d', 'amount_limit_mode']);

        return [
            [
                'key' => self::TIER_AGENT,
                'label' => '3D Agent Amount Limit',
                'limit' => $agentSetting?->amount_limit_3d === null ? null : (float) $agentSetting->amount_limit_3d,
                'used' => $this->threeDigitTotal($round, $agentId, null),
                'mode' => self::normalizeMode($agentSetting?->amount_limit_mode),
            ],
            [
                'key' => self::TIER_OPERATOR,
                'label' => '3D Operator Amount Limit',
                'limit' => $operatorSetting?->amount_limit_3d === null ? null : (float) $operatorSetting->amount_limit_3d,
                'used' => $this->threeDigitTotal($round, null, $operatorId),
                'mode' => self::normalizeMode($operatorSetting?->amount_limit_mode),
            ],
            [
                'key' => self::TIER_ROUND,
                'label' => '3D Round Amount Limit (all Agents and Operators)',
                'limit' => $round->amount_limit_all_3d === null ? null : (float) $round->amount_limit_all_3d,
                'used' => $this->threeDigitTotal($round, null, null),
                'mode' => self::normalizeMode($round->amount_limit_all_mode),
            ],
        ];
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
