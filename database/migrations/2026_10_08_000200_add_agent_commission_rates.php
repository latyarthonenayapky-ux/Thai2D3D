<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->decimal('commission_2d_percent', 5, 2)->default(0)->after('phone');
            $table->decimal('commission_3d_percent', 5, 2)->default(0)->after('commission_2d_percent');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['commission_2d_percent', 'commission_3d_percent']);
        });
    }
};
