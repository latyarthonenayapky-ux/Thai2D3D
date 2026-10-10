<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateOwnerUser extends Command
{
    protected $signature = 'thai2d3d:make-owner';

    protected $description = 'Create the initial owner account';

    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->components->error(
                'The owner can only be created before any other user accounts exist.'
            );

            return self::FAILURE;
        }

        $name = $this->ask('Owner name');
        $email = $this->ask('Owner email');
        $password = $this->secret('Password (at least 12 characters)');
        $confirmation = $this->secret('Confirm password');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'owner',
            'status' => true,
        ]);

        $this->components->info("Owner {$email} created.");

        return self::SUCCESS;
    }
}
