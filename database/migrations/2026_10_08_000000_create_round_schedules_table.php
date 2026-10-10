<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('round_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('round_no');
            $table->time('start_time')->nullable();
            $table->time('close_time')->nullable();
            $table->unsignedInteger('number_limit')->nullable();
            $table->timestamps();
            $table->unique(['admin_id', 'round_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('round_schedules');
    }
};
