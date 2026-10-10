<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireTempAccounts extends Command
{
    protected $signature = 'users:expire-temp';

    protected $description = 'Deactivate expired temporary accounts and the operators linked to them';

    public function handle(): int
    {
        $expired = User::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now('Asia/Yangon'))
            ->where('status', true)
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired temporary accounts.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($expired): void {
            $adminIds = $expired->pluck('id')->all();

            // Operators linked to an expiring admin lose their access too.
            User::query()
                ->where('role', 'operator')
                ->whereIn('admin_id', $adminIds)
                ->where('status', true)
                ->update(['status' => false]);

            $expired->each(function (User $user): void {
                $user->forceFill(['status' => false])->save();
            });
        });

        $this->info("Deactivated {$expired->count()} expired temporary account(s).");

        return self::SUCCESS;
    }
}
