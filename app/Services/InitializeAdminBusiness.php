<?php

namespace App\Services;

use App\Models\AdminBusinessSetting;
use App\Models\CodeRule;
use App\Models\RoundSchedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InitializeAdminBusiness
{
    public function initialize(User $admin): void
    {
        DB::transaction(function () use ($admin): void {
            AdminBusinessSetting::query()->firstOrCreate(
                ['admin_id' => $admin->id],
                ['payout_2d_multiplier' => null, 'payout_3d_multiplier' => null]
            );

            foreach ([1, 2, 3] as $roundNo) {
                RoundSchedule::query()->firstOrCreate(
                    ['admin_id' => $admin->id, 'round_no' => $roundNo],
                    ['start_time' => null, 'close_time' => null, 'number_limit' => null, 'number_limit_3d' => null]
                );
            }

            CodeRule::query()
                ->whereNull('admin_id')
                ->whereIn('code', ['W', 'N', 'X'])
                ->get()
                ->each(function (CodeRule $rule) use ($admin): void {
                    CodeRule::query()->firstOrCreate(
                        ['admin_id' => $admin->id, 'code' => $rule->code],
                        [
                            'name' => $rule->name,
                            'description' => $rule->description,
                            'rule_type' => $rule->rule_type,
                            'rule_config' => $rule->rule_config,
                            'allow_bracket' => $rule->allow_bracket,
                            'is_system' => true,
                            'is_active' => $rule->is_active,
                            'sort_order' => $rule->sort_order,
                        ]
                    );
                });
        });
    }
}
