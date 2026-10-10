<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conflictingAssignments = DB::table('agent_sessions')
            ->select('agent_id', 'round_id')
            ->groupBy('agent_id', 'round_id')
            ->havingRaw('COUNT(DISTINCT operator_id) > 1')
            ->first();

        if ($conflictingAssignments) {
            throw new RuntimeException(
                'Cannot migrate Agent assignments: an Agent has sessions with multiple Operators in the same Round. Resolve those assignments and retry.'
            );
        }

        Schema::table('agents', function (Blueprint $table) {
            $table->foreignId('admin_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
        });

        $legacyAgentOwners = DB::table('agents')
            ->join('users as operators', 'agents.operator_id', '=', 'operators.id')
            ->select('agents.id', 'operators.admin_id')
            ->whereNull('agents.admin_id')
            ->get();

        if ($legacyAgentOwners->contains(fn ($legacyAgentOwner) => $legacyAgentOwner->admin_id === null)) {
            throw new RuntimeException(
                'Cannot migrate Agent ownership: assign every legacy Operator to an Admin before retrying.'
            );
        }

        foreach ($legacyAgentOwners as $legacyAgentOwner) {
            DB::table('agents')
                ->where('id', $legacyAgentOwner->id)
                ->update(['admin_id' => $legacyAgentOwner->admin_id]);
        }

        Schema::table('agent_round_settings', function (Blueprint $table) {
            $table->foreignId('operator_id')->nullable()->after('round_id')->constrained('users');
        });

        $legacyAssignments = DB::table('agent_sessions')
            ->select('agent_id', 'round_id', DB::raw('MIN(operator_id) as operator_id'))
            ->groupBy('agent_id', 'round_id')
            ->get();

        foreach ($legacyAssignments as $assignment) {
            DB::table('agent_round_settings')->updateOrInsert(
                [
                    'agent_id' => $assignment->agent_id,
                    'round_id' => $assignment->round_id,
                ],
                [
                    'operator_id' => $assignment->operator_id,
                    'updated_at' => now(),
                ]
            );
        }

        $operatorUniqueIndex = collect(Schema::getIndexes('agents'))
            ->firstWhere('name', 'agents_operator_id_unique');

        if ($operatorUniqueIndex) {
            Schema::table('agents', function (Blueprint $table) {
                $table->dropUnique('agents_operator_id_unique');
            });
        }

        Schema::table('agents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('operator_id');
        });
    }

    public function down(): void
    {
        $ambiguousAgent = DB::table('agent_round_settings')
            ->select('agent_id')
            ->groupBy('agent_id')
            ->havingRaw('COUNT(DISTINCT operator_id) != 1')
            ->first();

        if ($ambiguousAgent) {
            throw new RuntimeException(
                'Cannot roll back Round-scoped Agent ownership while an Agent has unclaimed Rounds or multiple Operators across Rounds.'
            );
        }

        $unassignedAgent = DB::table('agents')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('agent_round_settings')
                    ->whereColumn('agent_round_settings.agent_id', 'agents.id');
            })
            ->exists();

        if ($unassignedAgent) {
            throw new RuntimeException(
                'Cannot roll back Round-scoped Agent ownership while an Agent has no Operator assignment in any Round.'
            );
        }

        Schema::table('agents', function (Blueprint $table) {
            $table->foreignId('operator_id')->nullable()->after('admin_id')->constrained('users')->nullOnDelete();
        });

        $legacyAssignments = DB::table('agent_round_settings')
            ->select('agent_id', DB::raw('MIN(operator_id) as operator_id'))
            ->groupBy('agent_id')
            ->get();

        foreach ($legacyAssignments as $assignment) {
            DB::table('agents')
                ->where('id', $assignment->agent_id)
                ->update(['operator_id' => $assignment->operator_id]);
        }

        Schema::table('agents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_id');
        });

        Schema::table('agent_round_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('operator_id');
        });
    }
};
