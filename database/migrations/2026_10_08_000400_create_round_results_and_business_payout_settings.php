<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_business_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->decimal('payout_2d_multiplier', 14, 2)->nullable();
            $table->decimal('payout_3d_multiplier', 14, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('round_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('round_id')->constrained('rounds')->cascadeOnDelete();
            $table->string('result_2d', 2)->nullable();
            $table->string('result_3d', 3)->nullable();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['admin_id', 'round_id']);
        });

        Schema::create('round_result_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_result_id')->constrained('round_results')->cascadeOnDelete();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->string('field', 20);
            $table->string('old_value', 3)->nullable();
            $table->string('new_value', 3)->nullable();
            $table->timestamps();
            $table->index(['round_result_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('round_result_changes');
        Schema::dropIfExists('round_results');
        Schema::dropIfExists('admin_business_settings');
    }
};
