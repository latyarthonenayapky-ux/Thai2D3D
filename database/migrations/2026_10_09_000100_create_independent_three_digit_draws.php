<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('three_digit_draws', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->date('draw_date');
            $table->time('open_time')->default('00:00:00');
            $table->time('result_time')->default('15:30:00');
            $table->unsignedInteger('number_limit')->nullable();
            $table->enum('status', ['open', 'closed'])->default('closed');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['admin_id', 'draw_date']);
            $table->index(['draw_date', 'status']);
        });

        Schema::create('three_digit_draw_agent_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('three_digit_draw_id')->constrained('three_digit_draws')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->foreignId('handler_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount_limit', 15, 2)->nullable();
            $table->timestamps();
            $table->unique(['three_digit_draw_id', 'agent_id'], 'tdd_agent_settings_draw_agent_unique');
            $table->index(['handler_id', 'three_digit_draw_id'], 'tdd_agent_settings_handler_draw_index');
        });

        Schema::create('three_digit_draw_hot_numbers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('three_digit_draw_id')->constrained('three_digit_draws')->cascadeOnDelete();
            $table->string('number', 3);
            $table->timestamps();
            $table->unique(['three_digit_draw_id', 'number']);
        });

        Schema::create('three_digit_draw_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('three_digit_draw_id')->constrained('three_digit_draws')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('result', 3)->nullable();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['three_digit_draw_id', 'admin_id']);
        });

        Schema::create('three_digit_draw_result_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('three_digit_draw_result_id');
            $table->foreignId('changed_by');
            $table->string('old_value', 3)->nullable();
            $table->string('new_value', 3)->nullable();
            $table->timestamps();
            $table->foreign('three_digit_draw_result_id', 'tdd_result_changes_result_foreign')
                ->references('id')->on('three_digit_draw_results')->cascadeOnDelete();
            $table->foreign('changed_by', 'tdd_result_changes_changed_by_foreign')
                ->references('id')->on('users')->restrictOnDelete();
            $table->index(['three_digit_draw_result_id', 'created_at'], 'tdd_result_changes_result_created_index');
        });

        Schema::table('three_digit_sale_inputs', function (Blueprint $table): void {
            $table->foreignId('three_digit_draw_id')->nullable()->after('agent_session_id')
                ->constrained('three_digit_draws')->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->after('three_digit_draw_id')
                ->constrained('agents')->cascadeOnDelete();
            $table->foreignId('draw_agent_setting_id')->nullable()->after('agent_id')
                ->constrained('three_digit_draw_agent_settings')->nullOnDelete();
            $table->foreignId('agent_session_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('three_digit_sale_inputs')->whereNull('agent_session_id')->delete();
        Schema::table('three_digit_sale_inputs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('draw_agent_setting_id');
            $table->dropConstrainedForeignId('agent_id');
            $table->dropConstrainedForeignId('three_digit_draw_id');
            $table->foreignId('agent_session_id')->nullable(false)->change();
        });
        Schema::dropIfExists('three_digit_draw_result_changes');
        Schema::dropIfExists('three_digit_draw_results');
        Schema::dropIfExists('three_digit_draw_hot_numbers');
        Schema::dropIfExists('three_digit_draw_agent_settings');
        Schema::dropIfExists('three_digit_draws');
    }
};
