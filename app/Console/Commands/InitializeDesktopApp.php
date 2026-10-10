<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class InitializeDesktopApp extends Command
{
    protected $signature = 'thai2d3d:desktop-initialize';

    protected $description = 'Prepare the local desktop database for first use';

    public function handle(): int
    {
        if ($this->call('migrate', ['--force' => true]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        if ($this->call('db:seed', [
            '--class' => 'Database\\Seeders\\CodeRuleSeeder',
            '--force' => true,
        ]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->line('DESKTOP_OWNER_SETUP_REQUIRED='.(User::query()->exists() ? '0' : '1'));

        return self::SUCCESS;
    }
}
