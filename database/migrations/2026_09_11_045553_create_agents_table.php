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
        Schema::create('agents', function (Blueprint $table) {

            $table->id();

            // Agent Code
            $table->string('agent_code')->unique();

            // Agent Name
            $table->string('agent_name');

            // Operator owner
            $table->foreignId('operator_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('phone')->nullable();

            $table->enum('status', [
                'active',
                'inactive',
            ])->default('active');

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
