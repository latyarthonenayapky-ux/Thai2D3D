<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ResetUserPassword extends Command
{
    protected $signature = 'thai2d3d:reset-password
        {email? : The email address of the account whose password should be reset}
        {--list : List all existing user accounts and exit}';

    protected $description = 'Reset the password of an existing owner, admin, or operator account';

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listUsers();
        }

        $email = $this->argument('email');

        if (! $email) {
            $this->listUsers();
            $email = $this->ask('Email address of the account to reset');
        }

        $email = trim((string) $email);

        if ($email === '') {
            $this->components->error('Email address is required.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            // Fall back to a case-insensitive lookup so that typos in casing
            // do not block an administrator from recovering an account.
            $user = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();
        }

        if (! $user) {
            $this->components->error("No account found for email [{$email}].");
            $this->listUsers();

            return self::FAILURE;
        }

        $this->line("Resetting password for: <comment>{$user->name}</comment> <{$user->email}> (role: {$user->role})");

        $password = $this->secret('New password (at least 12 characters)');
        $confirmation = $this->secret('Confirm new password');

        $validator = Validator::make([
            'email' => $user->email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user->forceFill([
            'password' => Hash::make($password),
            // Rotate the remember token so that any existing "keep me signed
            // in" cookies for this account stop working after the reset.
            'remember_token' => Str::random(60),
        ])->save();

        $this->components->info("Password for {$user->email} has been reset.");

        return self::SUCCESS;
    }

    private function listUsers(): int
    {
        $users = User::query()
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'role', 'status']);

        if ($users->isEmpty()) {
            $this->components->warn('No user accounts exist yet. Run `php artisan thai2d3d:make-owner` first.');

            return self::SUCCESS;
        }

        $rows = $users->map(fn (User $u) => [
            $u->id,
            $u->name,
            $u->email,
            $u->role,
            $u->status ? 'active' : 'inactive',
        ])->all();

        $this->table(['ID', 'Name', 'Email', 'Role', 'Status'], $rows);

        return self::SUCCESS;
    }
}
