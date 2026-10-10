<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow a Round to be created without a fixed opening time. When start_time
     * is null the Round opens automatically as soon as the previous Round closes.
     */
    public function up(): void
    {
        Schema::table('rounds', function (Blueprint $table): void {
            $table->time('start_time')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('rounds')->whereNull('start_time')->update(['start_time' => '00:00:00']);

        Schema::table('rounds', function (Blueprint $table): void {
            $table->time('start_time')->nullable(false)->change();
        });
    }
};
