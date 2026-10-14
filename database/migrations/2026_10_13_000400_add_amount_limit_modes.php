<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every Amount Limit tier gets a mode: 'warn' keeps accepting the sale and
 * flags it, 'block' refuses a sale that would pass the limit. Monitoring by
 * default, because the Admin sets the limit as a pre-agreed credit ceiling
 * rather than a hard stop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_round_settings', function (Blueprint $table) {
            $table->string('amount_limit_mode', 10)->default('warn')->after('amount_limit_3d');
        });

        Schema::table('operator_round_settings', function (Blueprint $table) {
            $table->string('amount_limit_mode', 10)->default('warn')->after('amount_limit_3d');
        });

        Schema::table('rounds', function (Blueprint $table) {
            $table->string('amount_limit_all_mode', 10)->default('warn')->after('amount_limit_all_3d');
        });
    }

    public function down(): void
    {
        Schema::table('agent_round_settings', function (Blueprint $table) {
            $table->dropColumn('amount_limit_mode');
        });

        Schema::table('operator_round_settings', function (Blueprint $table) {
            $table->dropColumn('amount_limit_mode');
        });

        Schema::table('rounds', function (Blueprint $table) {
            $table->dropColumn('amount_limit_all_mode');
        });
    }
};
