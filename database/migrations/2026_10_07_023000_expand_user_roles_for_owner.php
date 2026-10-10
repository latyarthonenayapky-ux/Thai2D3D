<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getColumnType('users', 'role') !== 'string') {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role', 20)
                    ->default('operator')
                    ->change();
            });
        }
    }

    public function down(): void
    {
        DB::table('users')
            ->where('role', 'owner')
            ->update(['role' => 'admin']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'operator'])
                ->default('operator')
                ->change();
        });
    }
};
