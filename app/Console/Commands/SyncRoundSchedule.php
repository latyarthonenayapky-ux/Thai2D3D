<?php

namespace App\Console\Commands;

use App\Models\Round;
use App\Models\RoundSchedule;
use App\Models\User;
use App\Services\SyncThreeDigitDraws;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncRoundSchedule extends Command
{
    protected $signature = 'rounds:sync-schedule';

    protected $description = 'Create daily rounds and apply their scheduled open and close times';

    public function handle(SyncThreeDigitDraws $threeDigitDraws): int
    {
        $now = Carbon::now('Asia/Yangon');
        $changed = 0;
        foreach (User::query()->whereIn('role', ['admin', 'temp_admin'])->where('status', true)->get() as $admin) {
            $threeDigitDraws->syncForAdmin($admin, $now);
            $schedules = RoundSchedule::query()
                ->where('admin_id', $admin->id)
                ->orderBy('round_no')
                ->get()
                ->keyBy('round_no');

            foreach ([0, 1] as $offset) {
                $date = $now->copy()->startOfDay()->addDays($offset);
                foreach ($schedules as $schedule) {
                    $this->syncUnopenedRound($date, $schedule);
                }
            }

            $rounds = Round::query()
                ->where('admin_id', $admin->id)
                ->whereBetween('round_date', [
                    $now->toDateString(),
                    $now->copy()->addDay()->toDateString(),
                ])
                ->orderBy('round_date')
                ->orderBy('round_no')
                ->get();

            $previousClose = null;
            $previousDate = null;

            foreach ($rounds as $round) {
                $schedule = $schedules->get($round->round_no);
                if (! $schedule) {
                    continue;
                }

                $dateKey = $round->round_date->toDateString();
                if ($previousDate !== $dateKey) {
                    $previousDate = $dateKey;
                    $previousClose = null;
                }

                $closesAt = $this->roundTime($round, $round->close_time);

                // When no start time is configured, the Round opens the moment the
                // previous Round on the same date closes (the first Round opens at
                // the start of the day), keeping Sale Entry continuously available.
                $opensAt = $round->start_time !== null
                    ? $this->roundTime($round, $round->start_time)
                    : ($previousClose ?? $round->round_date->copy()->startOfDay()->setTimezone('Asia/Yangon'));

                if ($round->status === 'open' && ! $round->manual_reopen && ! $round->manually_closed && $now->greaterThanOrEqualTo($closesAt)) {
                    DB::transaction(function () use ($round, $now, &$changed): void {
                        $locked = Round::query()->lockForUpdate()->find($round->id);
                        if (! $locked || $locked->status !== 'open' || $locked->manual_reopen || $locked->manually_closed) {
                            return;
                        }

                        $locked->forceFill([
                            'status' => 'closed',
                            'manual_reopen' => false,
                        ])->save();
                        $this->closeRoundSessions($locked, $now);
                        $changed++;
                    });
                }

                $previousClose = $closesAt;

                if ($round->status !== 'closed'
                    || $round->manual_reopen
                    || $round->manually_closed
                    || $round->close_time === null
                    || $now->lessThan($opensAt)
                    || $now->greaterThanOrEqualTo($closesAt)) {
                    continue;
                }

                DB::transaction(function () use ($round, $now, &$changed): void {
                    $locked = Round::query()->lockForUpdate()->find($round->id);
                    if (! $locked || $locked->status !== 'closed' || $locked->manual_reopen || $locked->manually_closed) {
                        return;
                    }

                    $locked->forceFill([
                        'status' => 'open',
                        'opened_at' => $now,
                    ])->save();

                    $manuallyReopened = Round::query()
                        ->where('admin_id', $locked->admin_id)
                        ->where('status', 'open')
                        ->where('manual_reopen', true)
                        ->where(function ($query) use ($locked): void {
                            $query->where('round_date', '<', $locked->round_date)
                                ->orWhere(function ($query) use ($locked): void {
                                    $query->where('round_date', $locked->round_date)
                                        ->where('round_no', '<', $locked->round_no);
                                });
                        })
                        ->get();

                    foreach ($manuallyReopened as $previousRound) {
                        $previousRound->forceFill([
                            'status' => 'closed',
                            'manual_reopen' => false,
                        ])->save();
                        $this->closeRoundSessions($previousRound, $now);
                    }

                    $changed++;
                });
            }
        }

        $this->info("Round schedule synced; {$changed} lifecycle change(s).");

        return self::SUCCESS;
    }

    private function syncUnopenedRound(Carbon $date, RoundSchedule $schedule): void
    {
        if ($schedule->close_time === null) {
            return;
        }

        $round = Round::query()
            ->where('admin_id', $schedule->admin_id)
            ->whereDate('round_date', $date->toDateString())
            ->where('round_no', $schedule->round_no)
            ->first();

        if ($round && (
            $round->status === 'open'
            || $round->opened_at !== null
            || $round->sessions()->exists()
        )) {
            return;
        }

        $attributes = [
            'start_time' => $schedule->start_time,
            'close_time' => $schedule->close_time,
            'number_limit_3d' => $schedule->number_limit_3d,
            'status' => 'closed',
            'manual_reopen' => false,
        ];

        if ($round) {
            $round->update($attributes);

            return;
        }

        Round::query()->create($attributes + [
            'admin_id' => $schedule->admin_id,
            'round_date' => $date->toDateString(),
            'round_no' => $schedule->round_no,
        ]);
    }

    private function roundTime(Round $round, string $time): Carbon
    {
        return Carbon::parse($round->round_date->toDateString().' '.$time, 'Asia/Yangon');
    }

    private function closeRoundSessions(Round $round, Carbon $closedAt): void
    {
        $round->sessions()
            ->where('status', 'open')
            ->update([
                'status' => 'closed',
                'closed_at' => $closedAt,
                'updated_at' => $closedAt,
            ]);
    }
}
