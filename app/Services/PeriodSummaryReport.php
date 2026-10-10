<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PeriodSummaryReport
{
    public function build(
        int $adminId,
        string $period,
        string $date,
        ?int $operatorId = null,
        ?int $agentId = null,
        ?int $roundNo = null
    ): array {
        $selectedDate = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Yangon');
        if (! $selectedDate || $selectedDate->format('Y-m-d') !== $date) {
            throw ValidationException::withMessages(['date' => 'Select a valid report date.']);
        }

        [$start, $end] = match ($period) {
            'daily' => [$selectedDate, $selectedDate],
            'weekly' => [
                $selectedDate->startOfWeek(Carbon::MONDAY),
                $selectedDate->startOfWeek(Carbon::MONDAY)->addDays(6),
            ],
            'monthly' => [$selectedDate->startOfMonth(), $selectedDate->endOfMonth()],
            'yearly' => [$selectedDate->startOfYear(), $selectedDate->endOfYear()],
            default => throw ValidationException::withMessages(['period' => 'Select a supported summary period.']),
        };

        $roundRows = DB::table('rounds as r')
            ->leftJoin('round_results as rr', 'rr.round_id', '=', 'r.id')
            ->leftJoin('admin_business_settings as abs', 'abs.admin_id', '=', 'r.admin_id')
            ->leftJoin('agent_round_settings as ars', function ($join) use ($operatorId, $agentId): void {
                $join->on('ars.round_id', '=', 'r.id');
                if ($operatorId !== null) {
                    $join->where('ars.operator_id', '=', $operatorId);
                }
                if ($agentId !== null) {
                    $join->where('ars.agent_id', '=', $agentId);
                }
            })
            ->leftJoin('agents as a', 'a.id', '=', 'ars.agent_id')
            ->leftJoin('users as operators', 'operators.id', '=', 'ars.operator_id')
            ->leftJoin('agent_sessions as ases', function ($join): void {
                $join->on('ases.round_id', '=', 'r.id')
                    ->on('ases.agent_id', '=', 'ars.agent_id')
                    ->on('ases.operator_id', '=', 'ars.operator_id');
            })
            ->leftJoin('sale_inputs as si', 'si.agent_session_id', '=', 'ases.id')
            ->leftJoin('sale_details as sd', function ($join): void {
                $join->on('sd.sale_input_id', '=', 'si.id')
                    ->where('sd.status', '=', 'accepted')
                    ->where('sd.is_excluded', '=', false);
            })
            ->where('r.admin_id', $adminId)
            ->where('r.status', 'closed')
            ->when($roundNo !== null, fn ($query) => $query->where('r.round_no', $roundNo))
            ->whereBetween('r.round_date', [$start->startOfDay(), $end->endOfDay()])
            ->select([
                'r.id as round_id',
                'r.round_date',
                'r.round_no',
                'rr.result_2d',
                'rr.result_3d',
                'a.id as agent_id',
                'a.agent_code',
                'a.agent_name',
                'ars.operator_id',
                'operators.name as operator_name',
                'abs.payout_2d_multiplier',
            ])
            ->selectRaw('COALESCE(a.commission_2d_percent, 0) as commission_2d_percent')
            ->selectRaw('COALESCE(SUM(sd.amount), 0) as accepted_stake')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN rr.result_2d IS NOT NULL AND sd.number = rr.result_2d THEN sd.amount ELSE 0 END), 0) as winning_stake'
            )
            ->groupBy(
                'r.id',
                'r.round_date',
                'r.round_no',
                'rr.result_2d',
                'rr.result_3d',
                'a.id',
                'a.agent_code',
                'a.agent_name',
                'ars.operator_id',
                'operators.name',
                'abs.payout_2d_multiplier',
                'a.commission_2d_percent'
            )
            ->orderBy('r.round_date')
            ->orderBy('r.round_no')
            ->get();

        $threeDigitRows = DB::table('rounds as r')
            ->leftJoin('round_results as rr', 'rr.round_id', '=', 'r.id')
            ->leftJoin('admin_business_settings as abs', 'abs.admin_id', '=', 'r.admin_id')
            ->leftJoin('agent_round_settings as ars', function ($join) use ($operatorId, $agentId): void {
                $join->on('ars.round_id', '=', 'r.id');
                if ($operatorId !== null) {
                    $join->where('ars.operator_id', '=', $operatorId);
                }
                if ($agentId !== null) {
                    $join->where('ars.agent_id', '=', $agentId);
                }
            })
            ->leftJoin('agents as a', 'a.id', '=', 'ars.agent_id')
            ->leftJoin('agent_sessions as ases', function ($join): void {
                $join->on('ases.round_id', '=', 'r.id')
                    ->on('ases.agent_id', '=', 'ars.agent_id')
                    ->on('ases.operator_id', '=', 'ars.operator_id');
            })
            ->leftJoin('three_digit_sale_inputs as tsi', 'tsi.agent_session_id', '=', 'ases.id')
            ->leftJoin('three_digit_sale_details as tsd', function ($join): void {
                $join->on('tsd.three_digit_sale_input_id', '=', 'tsi.id')
                    ->where('tsd.status', '=', 'accepted');
            })
            ->where('r.admin_id', $adminId)
            ->where('r.status', 'closed')
            ->when($roundNo !== null, fn ($query) => $query->where('r.round_no', $roundNo))
            ->whereBetween('r.round_date', [$start->startOfDay(), $end->endOfDay()])
            ->select([
                'r.id as round_id',
                'a.id as agent_id',
                'abs.payout_3d_multiplier',
            ])
            ->selectRaw('COALESCE(a.commission_3d_percent, 0) as commission_3d_percent')
            ->selectRaw('COALESCE(SUM(tsd.amount), 0) as accepted_stake_3d')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN rr.result_3d IS NOT NULL AND tsd.number = rr.result_3d THEN tsd.amount ELSE 0 END), 0) as winning_stake_3d'
            )
            ->groupBy('r.id', 'a.id', 'abs.payout_3d_multiplier', 'a.commission_3d_percent')
            ->get()
            ->keyBy(fn ($row): string => $row->round_id.':'.($row->agent_id ?? 'null'));

        $rounds = $roundRows->groupBy('round_id')->map(function ($agents) use ($threeDigitRows): array {
            $first = $agents->first();
            $agentTotals = $agents
                ->filter(fn ($agent): bool => $agent->agent_id !== null)
                ->map(function ($agent) use ($first, $threeDigitRows): array {
                    $stake = round((float) $agent->accepted_stake, 2);
                    $winningStake = round((float) $agent->winning_stake, 2);
                    $winnings = $first->payout_2d_multiplier === null
                        ? 0.0
                        : round($winningStake * (float) $first->payout_2d_multiplier, 2);
                    $commission = round($stake * (float) $agent->commission_2d_percent / 100, 2);
                    $threeDigit = $threeDigitRows->get($agent->round_id.':'.$agent->agent_id);
                    $threeDigitStake = round((float) ($threeDigit->accepted_stake_3d ?? 0), 2);
                    $threeDigitWinningStake = round((float) ($threeDigit->winning_stake_3d ?? 0), 2);
                    $threeDigitWinnings = $threeDigit?->payout_3d_multiplier === null
                        ? 0.0
                        : round($threeDigitWinningStake * (float) $threeDigit->payout_3d_multiplier, 2);
                    $threeDigitCommission = round(
                        $threeDigitStake * (float) ($threeDigit->commission_3d_percent ?? 0) / 100,
                        2
                    );

                    return [
                        'agent_id' => (int) $agent->agent_id,
                        'agent_code' => $agent->agent_code,
                        'agent_name' => $agent->agent_name,
                        'operator_name' => $agent->operator_name,
                        'round_id' => (int) $first->round_id,
                        'date' => $first->round_date,
                        'round_no' => (int) $first->round_no,
                        'result_2d' => $first->result_2d,
                        'total_stake' => $stake,
                        'winning_stake' => $winningStake,
                        'winnings' => $winnings,
                        'commission' => $commission,
                        'business_net' => round($stake - $winnings - $commission, 2),
                        'three_digit_total_stake' => $threeDigitStake,
                        'three_digit_winning_stake' => $threeDigitWinningStake,
                        'three_digit_winnings' => $threeDigitWinnings,
                        'three_digit_commission' => $threeDigitCommission,
                        'three_digit_business_net' => round(
                            $threeDigitStake - $threeDigitWinnings - $threeDigitCommission,
                            2
                        ),
                    ];
                });
            $acceptedStake = (float) $agentTotals->sum('total_stake');
            $winningStake = (float) $agentTotals->sum('winning_stake');
            $winnings = (float) $agentTotals->sum('winnings');
            $commission = (float) $agentTotals->sum('commission');
            $threeDigitStake = (float) $agentTotals->sum('three_digit_total_stake');
            $threeDigitWinningStake = (float) $agentTotals->sum('three_digit_winning_stake');
            $threeDigitWinnings = (float) $agentTotals->sum('three_digit_winnings');
            $threeDigitCommission = (float) $agentTotals->sum('three_digit_commission');

            return [
                'id' => (int) $first->round_id,
                'date' => $first->round_date,
                'round_no' => (int) $first->round_no,
                'result_2d' => $first->result_2d,
                'result_3d' => $first->result_3d,
                'total_stake' => round($acceptedStake, 2),
                'winning_stake' => round($winningStake, 2),
                'winnings' => round($winnings, 2),
                'commission' => round($commission, 2),
                'business_net' => round((float) $agentTotals->sum('business_net'), 2),
                'three_digit_total_stake' => round($threeDigitStake, 2),
                'three_digit_winning_stake' => round($threeDigitWinningStake, 2),
                'three_digit_winnings' => round($threeDigitWinnings, 2),
                'three_digit_commission' => round($threeDigitCommission, 2),
                'three_digit_business_net' => round((float) $agentTotals->sum('three_digit_business_net'), 2),
                'agent_rows' => $agentTotals->values(),
            ];
        })->values();

        $agentRoundRows = $rounds->flatMap(fn (array $round) => $round['agent_rows'])->values();
        $agentTotals = $agentRoundRows->groupBy('agent_id')->map(function ($rows): array {
            $first = $rows->first();

            return [
                'agent_id' => $first['agent_id'],
                'agent_code' => $first['agent_code'],
                'agent_name' => $first['agent_name'],
                'total_stake' => round((float) $rows->sum('total_stake'), 2),
                'winning_stake' => round((float) $rows->sum('winning_stake'), 2),
                'winnings' => round((float) $rows->sum('winnings'), 2),
                'commission' => round((float) $rows->sum('commission'), 2),
                'business_net' => round((float) $rows->sum('business_net'), 2),
                'three_digit_total_stake' => round((float) $rows->sum('three_digit_total_stake'), 2),
                'three_digit_winning_stake' => round((float) $rows->sum('three_digit_winning_stake'), 2),
                'three_digit_winnings' => round((float) $rows->sum('three_digit_winnings'), 2),
                'three_digit_commission' => round((float) $rows->sum('three_digit_commission'), 2),
                'three_digit_business_net' => round((float) $rows->sum('three_digit_business_net'), 2),
                'round_count' => $rows->count(),
            ];
        })->values();

        $dailyRows = $rounds->groupBy('date')->map(function ($day, string $date): array {
            return [
                'date' => $date,
                'round_count' => $day->count(),
                'total_stake' => round((float) $day->sum('total_stake'), 2),
                'winning_stake' => round((float) $day->sum('winning_stake'), 2),
                'winnings' => round((float) $day->sum('winnings'), 2),
                'commission' => round((float) $day->sum('commission'), 2),
                'business_net' => round((float) $day->sum('business_net'), 2),
                'three_digit_total_stake' => round((float) $day->sum('three_digit_total_stake'), 2),
                'three_digit_winnings' => round((float) $day->sum('three_digit_winnings'), 2),
                'three_digit_business_net' => round((float) $day->sum('three_digit_business_net'), 2),
            ];
        })->values();

        $totals = [
            'round_count' => $rounds->count(),
            'total_stake' => round((float) $rounds->sum('total_stake'), 2),
            'winning_stake' => round((float) $rounds->sum('winning_stake'), 2),
            'winnings' => round((float) $rounds->sum('winnings'), 2),
            'commission' => round((float) $rounds->sum('commission'), 2),
            'business_net' => round((float) $rounds->sum('business_net'), 2),
            'three_digit_total_stake' => round((float) $rounds->sum('three_digit_total_stake'), 2),
            'three_digit_winning_stake' => round((float) $rounds->sum('three_digit_winning_stake'), 2),
            'three_digit_winnings' => round((float) $rounds->sum('three_digit_winnings'), 2),
            'three_digit_commission' => round((float) $rounds->sum('three_digit_commission'), 2),
            'three_digit_business_net' => round((float) $rounds->sum('three_digit_business_net'), 2),
        ];

        return [
            'period' => $period,
            'selected_date' => $selectedDate,
            'start_date' => $start,
            'end_date' => $end,
            'daily_rows' => $dailyRows,
            'rounds' => $rounds,
            'agent_rows' => $agentRoundRows,
            'agent_totals' => $agentTotals,
            'totals' => $totals,
        ];
    }
}
