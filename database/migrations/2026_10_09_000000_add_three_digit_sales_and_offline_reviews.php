<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('round_schedules', function (Blueprint $table): void {
            $table->unsignedInteger('number_limit_3d')->nullable()->after('number_limit');
        });

        Schema::table('rounds', function (Blueprint $table): void {
            $table->unsignedInteger('number_limit_3d')->nullable()->after('number_limit');
        });

        Schema::table('agent_round_settings', function (Blueprint $table): void {
            $table->decimal('amount_limit_3d', 14, 2)->nullable()->after('amount_limit');
        });

        Schema::table('sale_inputs', function (Blueprint $table): void {
            $table->uuid('client_uuid')->nullable();
            $table->unique(['operator_id', 'client_uuid']);
        });

        Schema::create('three_digit_hot_numbers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('round_id')->constrained('rounds')->cascadeOnDelete();
            $table->string('number', 3);
            $table->timestamps();
            $table->unique(['round_id', 'number']);
        });

        Schema::create('three_digit_sale_inputs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_session_id')->constrained('agent_sessions')->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('client_uuid')->nullable();
            $table->string('original_input', 255);
            $table->string('normalized_input', 255);
            $table->string('input_type', 50);
            $table->string('code', 20);
            $table->unsignedInteger('number_count')->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->enum('status', ['accepted', 'rejected'])->default('accepted');
            $table->text('reject_reason')->nullable();
            $table->timestamps();
            $table->unique(['operator_id', 'client_uuid']);
            $table->index(['agent_session_id', 'created_at']);
        });

        Schema::create('three_digit_sale_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('three_digit_sale_input_id')
                ->constrained('three_digit_sale_inputs')
                ->cascadeOnDelete();
            $table->string('number', 3);
            $table->decimal('amount', 12, 2)->default(0);
            $table->enum('status', ['accepted', 'rejected'])->default('accepted');
            $table->string('reject_reason')->nullable();
            $table->timestamps();
            $table->index(['number']);
            $table->index(['three_digit_sale_input_id', 'number']);
        });

        Schema::create('offline_sync_reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('client_uuid');
            $table->enum('sale_type', ['2d', '3d']);
            $table->foreignId('operator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('agent_session_id')->constrained('agent_sessions')->cascadeOnDelete();
            $table->foreignId('round_id')->constrained('rounds')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->string('original_input', 255);
            $table->timestamp('recorded_at')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('conflict_reason');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('sale_input_id')->nullable()->constrained('sale_inputs')->nullOnDelete();
            $table->foreignId('three_digit_sale_input_id')->nullable()
                ->constrained('three_digit_sale_inputs')
                ->nullOnDelete();
            $table->timestamps();
            $table->unique(['operator_id', 'client_uuid']);
            $table->index(['round_id', 'status', 'created_at']);
            $table->index(['agent_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_sync_reviews');
        Schema::dropIfExists('three_digit_sale_details');
        Schema::dropIfExists('three_digit_sale_inputs');
        Schema::dropIfExists('three_digit_hot_numbers');
        Schema::table('sale_inputs', function (Blueprint $table): void {
            $table->dropUnique(['operator_id', 'client_uuid']);
            $table->dropColumn('client_uuid');
        });
        Schema::table('agent_round_settings', function (Blueprint $table): void {
            $table->dropColumn('amount_limit_3d');
        });
        Schema::table('rounds', function (Blueprint $table): void {
            $table->dropColumn('number_limit_3d');
        });
        Schema::table('round_schedules', function (Blueprint $table): void {
            $table->dropColumn('number_limit_3d');
        });
    }
};
