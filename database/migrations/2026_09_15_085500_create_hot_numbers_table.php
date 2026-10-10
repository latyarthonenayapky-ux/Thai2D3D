<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hot_numbers')) {
            return;
        }

        Schema::create('hot_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')
                ->constrained('rounds')
                ->cascadeOnDelete();
            $table->string('number', 2);
            $table->timestamps();
            $table->unique(['round_id', 'number']);
            $table->index('number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hot_numbers');
    }
};
