<?php

namespace App\Services;

use App\Models\AdminBusinessSetting;
use App\Models\ThreeDigitDraw;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ThreeDigitDrawPeriodReport
{
    public function build(
        int $adminId,
        string $period,
        string $date,
        ?int $operatorId = null,
        ?int $agentId = null
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

        $now = Carbon::now('Asia/Yangon');
        $draws = ThreeDigitDraw::query()
            ->with(['result', 'agentSettings.agent', 'agentSettings.handler'])
            ->where('admin_id', $adminId)
            ->whereDate('draw_date', '>=', $start->toDateString())
            ->whereDate('draw_date', '<=', $end->toDateString())
            ->where(function ($query) use ($now): void {
                $today = $now->toDateString();
                $query->whereDate('draw_date', '<', $today)
                    ->orWhere(function ($query) use ($now, $today): void {
                        $query->whereDate('draw_date', $today)
                            ->whereTime('result_time', '<=', $now->format('H:i:s'));
                    });
            })
            ->when($operatorId !== null, fn ($query) => $query->whereHas(
                'agentSettings',
                fn ($settings) => $settings->where('handler_id', $operatorId)
            ))
            ->when($agentId !== null, fn ($query) => $query->whereHas(
                'agentSettings',
                fn ($settings) => $settings->where('agent_id', $agentId)
            ))
            ->orderBy('draw_date')
            ->get();

        $multiplier = AdminBusinessSetting::query()
            ->where('admin_id', $adminId)
            ->value('payout_3d_multiplier');
        $drawRows = $draws->map(function (ThreeDigitDraw $draw) use ($operatorId, $agentId, $multiplier): array {
            $settings = $draw->agentSettings
                ->when($operatorId !== null, fn ($rows) => $rows->where('handler_id', $operatorId))
                ->when($agentId !== null, fn ($rows) => $rows->where('agent_id', $agentId));
            $stakes = DB::table('three_digit_sale_details as d')
                ->join('three_digit_sale_inputs as i', 'i.id', '=', 'd.three_digit_sale_input_id')
                ->where('i.three_digit_draw_id', $draw->id)
                ->where('d.status', 'accepted')
                ->select('i.agent_id')
                ->selectRaw('COALESCE(SUM(d.amount), 0) as total_stake')
                ->selectRaw('COALESCE(SUM(CASE WHEN d.number = ? THEN d.amount ELSE 0 END), 0) as winning_stake', [
                    $draw->result?->result ?? '',
                ])
                ->groupBy('i.agent_id')
                ->get()
                ->keyBy('agent_id');

            $agents = $settings->map(function ($setting) use ($draw, $multiplier, $stakes): array {
                $stakeRow = $stakes->get($setting->agent_id);
                $stake = round((float) ($stakeRow->total_stake ?? 0), 2);
                $winningStake = round((float) ($stakeRow->winning_stake ?? 0), 2);
                $winnings = $multiplier === null ? 0.0 : round($winningStake * (float) $multiplier, 2);
                $commission = round($stake * (float) $setting->agent->commission_3d_percent / 100, 2);

                return [
                    'draw_id' => (int) $draw->id,
                    'draw_date' => $draw->draw_date->toDateString(),
                    'result' => $draw->result?->result,
                    'agent_id' => (int) $setting->agent_id,
                    'agent_code' => $setting->agent->agent_code,
                    'agent_name' => $setting->agent->agent_name,
                    'handler_name' => $setting->handler?->name,
                    'total_stake' => $stake,
                    'winning_stake' => $winningStake,
                    'winnings' => $winnings,
                    'commission' => $commission,
                    'business_net' => round($stake - $winnings - $commission, 2),
                ];
            })->values();

            return [
                'id' => (int) $draw->id,
                'date' => $draw->draw_date->toDateString(),
                'result' => $draw->result?->result,
                'agent_rows' => $agents,
                'total_stake' => round((float) $agents->sum('total_stake'), 2),
                'winning_stake' => round((float) $agents->sum('winning_stake'), 2),
                'winnings' => round((float) $agents->sum('winnings'), 2),
                'commission' => round((float) $agents->sum('commission'), 2),
                'business_net' => round((float) $agents->sum('business_net'), 2),
            ];
        })->values();

        $agentRows = $drawRows->flatMap(fn (array $draw): array => $draw['agent_rows']->all())->values();
        $agentTotals = $agentRows->groupBy('agent_id')->map(function ($rows): array {
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
                'draw_count' => $rows->count(),
            ];
        })->values();
        $dailyRows = $drawRows->groupBy('date')->map(fn ($rows, string $date): array => [
            'date' => $date,
            'draw_count' => $rows->count(),
            'total_stake' => round((float) $rows->sum('total_stake'), 2),
            'winning_stake' => round((float) $rows->sum('winning_stake'), 2),
            'winnings' => round((float) $rows->sum('winnings'), 2),
            'commission' => round((float) $rows->sum('commission'), 2),
            'business_net' => round((float) $rows->sum('business_net'), 2),
        ])->values();

        return [
            'period' => $period,
            'selected_date' => $selectedDate,
            'start_date' => $start,
            'end_date' => $end,
            'draw_count' => $drawRows->count(),
            'draw_rows' => $drawRows,
            'daily_rows' => $dailyRows,
            'agent_rows' => $agentRows,
            'agent_totals' => $agentTotals,
            'totals' => [
                'total_stake' => round((float) $drawRows->sum('total_stake'), 2),
                'winning_stake' => round((float) $drawRows->sum('winning_stake'), 2),
                'winnings' => round((float) $drawRows->sum('winnings'), 2),
                'commission' => round((float) $drawRows->sum('commission'), 2),
                'business_net' => round((float) $drawRows->sum('business_net'), 2),
            ],
            'payout_configured' => $multiplier !== null,
        ];
    }
}
