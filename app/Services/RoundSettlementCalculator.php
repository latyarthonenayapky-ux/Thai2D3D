<?php

namespace App\Services;

use App\Models\AdminBusinessSetting;
use App\Models\AgentRoundSetting;
use App\Models\Round;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RoundSettlementCalculator
{
    /**
     * @return array{agents: Collection, totals: array<string, float>, payout_2d_configured: bool}
     */
    public function calculate(Round $round, ?int $operatorId = null): array
    {
        $payoutMultiplier = AdminBusinessSetting::query()
            ->where('admin_id', $round->admin_id)
            ->value('payout_2d_multiplier');
        $payoutMultiplier3d = AdminBusinessSetting::query()
            ->where('admin_id', $round->admin_id)
            ->value('payout_3d_multiplier');

        $agentSettings = AgentRoundSetting::query()
            ->with('agent')
            ->where('round_id', $round->id)
            ->when($operatorId !== null, fn ($query) => $query->where('operator_id', $operatorId))
            ->get()
            ->keyBy('agent_id');

        $stakes = DB::table('sale_details')
            ->join('sale_inputs', 'sale_details.sale_input_id', '=', 'sale_inputs.id')
            ->join('agent_sessions', 'sale_inputs.agent_session_id', '=', 'agent_sessions.id')
            ->where('agent_sessions.round_id', $round->id)
            ->where('sale_details.status', 'accepted')
            ->where('sale_details.is_excluded', false)
            ->when($operatorId !== null, fn ($query) => $query->where('agent_sessions.operator_id', $operatorId))
            ->select(
                'agent_sessions.agent_id',
                'sale_details.number',
                DB::raw('SUM(sale_details.amount) as stake')
            )
            ->groupBy('agent_sessions.agent_id', 'sale_details.number')
            ->get();

        $byAgent = $stakes->groupBy('agent_id');
        $stakes3d = DB::table('three_digit_sale_details')
            ->join('three_digit_sale_inputs', 'three_digit_sale_details.three_digit_sale_input_id', '=', 'three_digit_sale_inputs.id')
            ->join('agent_sessions', 'three_digit_sale_inputs.agent_session_id', '=', 'agent_sessions.id')
            ->where('agent_sessions.round_id', $round->id)
            ->where('three_digit_sale_details.status', 'accepted')
            ->when($operatorId !== null, fn ($query) => $query->where('agent_sessions.operator_id', $operatorId))
            ->select(
                'agent_sessions.agent_id',
                'three_digit_sale_details.number',
                DB::raw('SUM(three_digit_sale_details.amount) as stake')
            )
            ->groupBy('agent_sessions.agent_id', 'three_digit_sale_details.number')
            ->get();
        $byAgent3d = $stakes3d->groupBy('agent_id');
        $agents = $agentSettings->map(function (AgentRoundSetting $setting, int $agentId) use ($byAgent, $byAgent3d, $round, $payoutMultiplier, $payoutMultiplier3d): array {
            $agentStakes = $byAgent->get($agentId, collect());
            $totalStake = (float) $agentStakes->sum('stake');
            $winningStake = $round->result?->result_2d === null
                ? 0.0
                : (float) $agentStakes
                    ->where('number', $round->result->result_2d)
                    ->sum('stake');
            $winnings = $payoutMultiplier === null ? 0.0 : $winningStake * (float) $payoutMultiplier;
            $commission = $totalStake * (float) $setting->agent->commission_2d_percent / 100;
            $agentStakes3d = $byAgent3d->get($agentId, collect());
            $totalStake3d = (float) $agentStakes3d->sum('stake');
            $winningStake3d = $round->result?->result_3d === null
                ? 0.0
                : (float) $agentStakes3d->where('number', $round->result->result_3d)->sum('stake');
            $winnings3d = $payoutMultiplier3d === null ? 0.0 : $winningStake3d * (float) $payoutMultiplier3d;
            $commission3d = $totalStake3d * (float) $setting->agent->commission_3d_percent / 100;

            return [
                'agent' => $setting->agent,
                'operator' => $setting->operator,
                'total_stake' => round($totalStake, 2),
                'winning_stake' => round($winningStake, 2),
                'winnings' => round($winnings, 2),
                'commission' => round($commission, 2),
                'business_net' => round($totalStake - $winnings - $commission, 2),
                'three_digit_total_stake' => round($totalStake3d, 2),
                'three_digit_winning_stake' => round($winningStake3d, 2),
                'three_digit_winnings' => round($winnings3d, 2),
                'three_digit_commission' => round($commission3d, 2),
                'three_digit_business_net' => round($totalStake3d - $winnings3d - $commission3d, 2),
            ];
        })->values();

        return [
            'agents' => $agents,
            'totals' => [
                'total_stake' => round((float) $agents->sum('total_stake'), 2),
                'winning_stake' => round((float) $agents->sum('winning_stake'), 2),
                'winnings' => round((float) $agents->sum('winnings'), 2),
                'commission' => round((float) $agents->sum('commission'), 2),
                'business_net' => round((float) $agents->sum('business_net'), 2),
                'three_digit_total_stake' => round((float) $agents->sum('three_digit_total_stake'), 2),
                'three_digit_winning_stake' => round((float) $agents->sum('three_digit_winning_stake'), 2),
                'three_digit_winnings' => round((float) $agents->sum('three_digit_winnings'), 2),
                'three_digit_commission' => round((float) $agents->sum('three_digit_commission'), 2),
                'three_digit_business_net' => round((float) $agents->sum('three_digit_business_net'), 2),
            ],
            'payout_2d_configured' => $payoutMultiplier !== null,
            'payout_3d_configured' => $payoutMultiplier3d !== null,
        ];
    }
}
