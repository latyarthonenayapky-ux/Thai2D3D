<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table): void {
            $table->string('mobile_login_code', 32)->nullable()->unique()->after('agent_name');
            $table->string('mobile_password')->nullable()->after('mobile_login_code');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table): void {
            $table->dropUnique(['mobile_login_code']);
            $table->dropColumn(['mobile_login_code', 'mobile_password']);
        });
    }
};
