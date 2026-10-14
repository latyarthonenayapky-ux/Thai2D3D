<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_round_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained('rounds')->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount_limit', 15, 2)->nullable();
            $table->decimal('amount_limit_3d', 15, 2)->nullable();
            $table->timestamps();

            $table->unique(['round_id', 'operator_id']);
        });

        Schema::table('rounds', function (Blueprint $table) {
            $table->decimal('amount_limit_all', 15, 2)->nullable()->after('number_limits');
            $table->decimal('amount_limit_all_3d', 15, 2)->nullable()->after('amount_limit_all');
        });
    }

    public function down(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            $table->dropColumn(['amount_limit_all', 'amount_limit_all_3d']);
        });

        Schema::dropIfExists('operator_round_settings');
    }
};
