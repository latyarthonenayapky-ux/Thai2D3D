<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Database\Seeder;

class AgentSeeder extends Seeder
{
    public function run(): void
    {
        $operator = User::where('role', 'operator')->first();

        if (! $operator) {
            throw new \Exception('Operator user not found.');
        }

        Agent::updateOrCreate(
            [
                'agent_code' => 'WYA01',
            ],
            [
                'admin_id' => $operator->admin_id,
                'agent_name' => 'WYA Agent',
                'phone' => '09123456789',
                'status' => 'active',
            ]
        );
    }
}
