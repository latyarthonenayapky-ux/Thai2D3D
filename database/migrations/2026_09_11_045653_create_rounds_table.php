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
        Schema::create('rounds', function (Blueprint $table) {

            $table->id();

            // Round Date
            $table->date('round_date');

            // 01,02,03
            $table->integer('round_no');

            // Start Time
            $table->time('start_time');

            // Admin Manual Close Time
            $table->time('close_time');

            $table->enum('status', [
                'open',
                'closed',
            ])
                ->default('open');

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rounds');
    }
};
