<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cash that actually moved between the office and one Agent.
 *
 * Settlement already works out what each Agent owes from a Round's sales, but
 * nothing recorded the money itself, so an Admin could not answer "how much of
 * this Agent's cash am I holding" without a notebook. Amounts are stored as
 * positive numbers; whether they come in or go out follows from the type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();

            // deposit / repayment bring cash in, credit / withdrawal take cash out.
            $table->enum('type', ['deposit', 'credit', 'repayment', 'withdrawal']);

            $table->decimal('amount', 15, 2);
            $table->date('occurs_on');
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['agent_id', 'occurs_on']);
            $table->index(['admin_id', 'occurs_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_ledger_entries');
    }
};
