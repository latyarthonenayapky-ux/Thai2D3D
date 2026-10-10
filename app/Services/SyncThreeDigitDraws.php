<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SyncThreeDigitDraws
{
    public function syncForAdmin(User $admin, ?Carbon $now = null): void
    {
        $now = ($now ?? Carbon::now('Asia/Yangon'))->copy()->timezone('Asia/Yangon');
        $firstMonth = $now->copy()->startOfMonth()->subMonth();
        $lastMonth = $now->copy()->startOfMonth()->addMonths(2);
        $timestamps = now();
        $draws = [];

        for ($month = $firstMonth; $month->lessThanOrEqualTo($lastMonth); $month->addMonth()) {
            foreach ([1, 16] as $day) {
                $date = $month->copy()->day($day);
                $isDrawDay = $now->toDateString() === $date->toDateString();
                $isActive = $isDrawDay && $now->format('H:i:s') < '15:30:00';
                $closed = $now->toDateString() > $date->toDateString()
                    || ($isDrawDay && ! $isActive);
                $draws[] = [
                    'admin_id' => $admin->id,
                    'draw_date' => $date->toDateString(),
                    'open_time' => '00:00:00',
                    'result_time' => '15:30:00',
                    'status' => $isActive ? 'open' : 'closed',
                    'opened_at' => $isActive ? $date->copy()->startOfDay() : null,
                    'closed_at' => $closed ? $date->copy()->setTime(15, 30) : null,
                    'created_at' => $timestamps,
                    'updated_at' => $timestamps,
                ];
            }
        }

        DB::table('three_digit_draws')->upsert(
            $draws,
            ['admin_id', 'draw_date'],
            ['open_time', 'result_time', 'status', 'opened_at', 'closed_at', 'updated_at']
        );
    }
}
