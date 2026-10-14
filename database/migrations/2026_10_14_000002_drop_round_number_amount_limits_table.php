<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The 2D Amount Limit is one figure per Round again (rounds.amount_limit_all),
 * applied to every number 00–99 and counted across all Agents and Operators
 * together, so the per-number cap table has no reader left.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('round_number_amount_limits');
    }

    public function down(): void
    {
        Schema::create('round_number_amount_limits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('round_id')->constrained()->cascadeOnDelete();
            $table->string('number', 2);
            $table->decimal('amount_limit', 15, 2);
            $table->timestamps();

            $table->unique(['round_id', 'number']);
        });
    }
};
