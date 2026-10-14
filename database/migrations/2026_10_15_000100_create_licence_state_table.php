<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row: which licence the copy is currently running on, and the furthest
 * forward in time the app has ever seen.
 *
 * last_seen_at is the clock rollback reference. A customer who winds back the
 * system clock to stretch an expired licence moves now backwards past it, and
 * the licence is treated as expired with no grace.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licence_state', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('active_licence_id')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->foreign('active_licence_id')
                ->references('id')
                ->on('licences')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licence_state');
    }
};
