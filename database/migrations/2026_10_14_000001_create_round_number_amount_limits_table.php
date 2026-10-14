<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The 2D Amount Limit is set per number (00–99) for a whole Round, counting
 * every Agent and Operator under that Round's Admin together — that is how the
 * office caps how much a single number may carry.
 *
 * A row only exists for a number the Admin actually capped. Passing the cap is
 * allowed; the over-cap numbers are listed for review instead of refused.
 */
return new class extends Migration
{
    public function up(): void
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

    public function down(): void
    {
        Schema::dropIfExists('round_number_amount_limits');
    }
};
