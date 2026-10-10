<?php

namespace Database\Seeders;

use App\Models\Round;
use App\Models\RoundSchedule;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class RoundSeeder extends Seeder
{
    public function run(): void
    {
        $today = Carbon::today('Asia/Yangon');

        foreach (RoundSchedule::query()->orderBy('admin_id')->orderBy('round_no')->get() as $schedule) {
            if ($schedule->start_time === null || $schedule->close_time === null) {
                continue;
            }

            $round = Round::query()
                ->where('admin_id', $schedule->admin_id)
                ->whereDate('round_date', $today->toDateString())
                ->where('round_no', $schedule->round_no)
                ->first();

            if ($round) {
                continue;
            }

            Round::query()->create([
                'admin_id' => $schedule->admin_id,
                'round_date' => $today->toDateString(),
                'round_no' => $schedule->round_no,
                'start_time' => $schedule->start_time,
                'close_time' => $schedule->close_time,
                'number_limit' => $schedule->number_limit ?? 0,
                'status' => 'closed',
            ]);
        }
    }
}
