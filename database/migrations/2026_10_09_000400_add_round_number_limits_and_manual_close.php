<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            $table->json('number_limits')->nullable()->after('number_limit');
            $table->boolean('manually_closed')->default(false)->after('manual_reopen');
        });
    }

    public function down(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            $table->dropColumn(['number_limits', 'manually_closed']);
        });
    }
};
