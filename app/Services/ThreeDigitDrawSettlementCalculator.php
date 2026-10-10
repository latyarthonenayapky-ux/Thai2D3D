<?php

namespace App\Services;

use App\Models\AdminBusinessSetting;
use App\Models\ThreeDigitDraw;
use App\Models\ThreeDigitDrawAgentSetting;
use App\Models\ThreeDigitSaleDetail;

class ThreeDigitDrawSettlementCalculator
{
    public function calculate(ThreeDigitDraw $draw): array
    {
        $multiplier = AdminBusinessSetting::query()
            ->where('admin_id', $draw->admin_id)
            ->value('payout_3d_multiplier');
        $result = $draw->result?->result;
        $agents = ThreeDigitDrawAgentSetting::query()
            ->with(['agent', 'handler'])
            ->where('three_digit_draw_id', $draw->id)
            ->get()
            ->map(function (ThreeDigitDrawAgentSetting $setting) use ($draw, $multiplier, $result): array {
                $details = ThreeDigitSaleDetail::query()
                    ->whereHas('saleInput', fn ($query) => $query
                        ->where('three_digit_draw_id', $draw->id)
                        ->where('agent_id', $setting->agent_id))
                    ->where('status', 'accepted')
                    ->get(['number', 'amount']);
                $stake = (float) $details->sum('amount');
                $winningStake = $result === null
                    ? 0.0
                    : (float) $details->where('number', $result)->sum('amount');
                $winnings = $multiplier === null ? 0.0 : $winningStake * (float) $multiplier;
                $commission = $stake * (float) $setting->agent->commission_3d_percent / 100;

                return [
                    'setting' => $setting,
                    'agent' => $setting->agent,
                    'handler' => $setting->handler,
                    'total_stake' => round($stake, 2),
                    'winning_stake' => round($winningStake, 2),
                    'winnings' => round($winnings, 2),
                    'commission' => round($commission, 2),
                    'business_net' => round($stake - $winnings - $commission, 2),
                ];
            });

        return [
            'agents' => $agents,
            'totals' => [
                'total_stake' => round((float) $agents->sum('total_stake'), 2),
                'winning_stake' => round((float) $agents->sum('winning_stake'), 2),
                'winnings' => round((float) $agents->sum('winnings'), 2),
                'commission' => round((float) $agents->sum('commission'), 2),
                'business_net' => round((float) $agents->sum('business_net'), 2),
            ],
            'payout_configured' => $multiplier !== null,
        ];
    }
}
