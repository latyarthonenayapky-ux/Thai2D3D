<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_inputs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('agent_session_id')
                ->constrained('agent_sessions')
                ->cascadeOnDelete();

            $table->foreignId('operator_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('original_input', 255);

            $table->string('normalized_input', 255)->nullable();

            $table->string('input_type', 50);

            $table->string('code', 20)->nullable();

            $table->string('number_argument', 100)->nullable();

            $table->unsignedInteger('number_count')->default(0);

            $table->decimal('amount', 12, 2)->default(0);

            $table->decimal('total_amount', 14, 2)->default(0);

            $table->enum('status', [
                'accepted',
                'rejected',
                'pending',
            ])->default('accepted');

            $table->text('reject_reason')->nullable();

            $table->timestamps();

            $table->index([
                'agent_session_id',
                'created_at',
            ]);

            $table->index([
                'operator_id',
                'created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_inputs');
    }
};
