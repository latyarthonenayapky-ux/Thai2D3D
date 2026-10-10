<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agent_sessions', function (Blueprint $table) {

            $table->id();

            // Round ချိတ်
            $table->foreignId('round_id')
                ->constrained('rounds')
                ->cascadeOnDelete();

            // Agent ချိတ်
            $table->foreignId('agent_id')
                ->constrained('agents')
                ->cascadeOnDelete();

            // Operator ချိတ်
            $table->foreignId('operator_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Example: 260902-WYA-03
            $table->string('session_code')
                ->unique();

            $table->enum('status', [
                'open',
                'closed',
            ])
                ->default('open');

            $table->timestamp('opened_at')
                ->nullable();

            $table->timestamp('closed_at')
                ->nullable();

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_sessions');
    }
};
