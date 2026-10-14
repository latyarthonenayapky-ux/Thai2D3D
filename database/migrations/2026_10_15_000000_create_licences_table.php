<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every licence this installation has ever been given, newest last.
 *
 * The table is append-only: renewing inserts a row instead of updating one, so
 * the owner can always show the vendor which licence was active when, and a
 * bad renewal never destroys the record of the previous good one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licences', function (Blueprint $table): void {
            $table->id();
            $table->string('licence_id', 64)->index();
            $table->string('email');
            $table->string('plan', 64);
            $table->unsignedInteger('max_admins')->default(0);
            $table->unsignedInteger('max_agents')->default(0);
            $table->date('issued_at');
            $table->date('expires_at');
            $table->text('licence_string');
            $table->boolean('signature_ok')->default(true);
            $table->timestamp('installed_at')->nullable();
            $table->string('revoked_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licences');
    }
};
