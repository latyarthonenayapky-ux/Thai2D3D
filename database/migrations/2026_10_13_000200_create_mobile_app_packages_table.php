<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_app_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('stored_path')->nullable();
            $table->string('file_name');
            $table->string('version', 32)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique('admin_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_app_packages');
    }
};
