<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_round_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('agent_id')
                ->constrained('agents')
                ->cascadeOnDelete();

            $table->foreignId('round_id')
                ->constrained('rounds')
                ->cascadeOnDelete();

            $table->unsignedInteger('number_limit')->nullable();

            $table->decimal('amount_limit', 15, 2)->nullable();

            $table->timestamps();

            $table->unique(['agent_id', 'round_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_round_settings');
    }
};
