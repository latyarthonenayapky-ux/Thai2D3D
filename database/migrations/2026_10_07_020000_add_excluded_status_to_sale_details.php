<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_details', function (Blueprint $table) {
            $table->enum('status', [
                'accepted',
                'rejected',
                'excluded',
            ])->default('accepted')->change();
        });
    }

    public function down(): void
    {
        DB::table('sale_details')
            ->where('status', 'excluded')
            ->update(['status' => 'accepted']);

        Schema::table('sale_details', function (Blueprint $table) {
            $table->enum('status', [
                'accepted',
                'rejected',
            ])->default('accepted')->change();
        });
    }
};
