<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateRound = DB::table('rounds')
            ->select('round_date', 'round_no')
            ->groupBy('round_date', 'round_no')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicateRound) {
            throw new RuntimeException(
                'Cannot enforce one Round per date and number while duplicate Round records exist. Resolve the duplicates and retry.'
            );
        }

        Schema::table('rounds', function (Blueprint $table) {
            $table->foreignId('admin_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->boolean('manual_reopen')->default(false);
            $table->unique(['admin_id', 'round_date', 'round_no']);
        });

        DB::table('rounds')
            ->whereNull('opened_at')
            ->where(function ($query): void {
                $query->where('status', 'open')
                    ->orWhereIn('id', DB::table('agent_sessions')->select('round_id'));
            })
            ->update(['opened_at' => DB::raw('created_at')]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('admin_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });

        Schema::table('code_rules', function (Blueprint $table) {
            $table->foreignId('admin_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
            $table->dropUnique(['code']);
            $table->unique(['admin_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('code_rules', function (Blueprint $table) {
            $table->dropUnique(['admin_id', 'code']);
            $table->unique('code');
            $table->dropConstrainedForeignId('admin_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_id');
        });

        if (collect(Schema::getIndexes('agents'))->contains('name', 'agents_operator_id_unique')) {
            Schema::table('agents', function (Blueprint $table) {
                $table->dropUnique('agents_operator_id_unique');
            });
        }

        Schema::table('rounds', function (Blueprint $table) {
            $table->dropUnique(['admin_id', 'round_date', 'round_no']);
            $table->dropConstrainedForeignId('admin_id');
            $table->dropColumn(['opened_at', 'manual_reopen']);
        });
    }
};
