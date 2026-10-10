<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_rules', function (Blueprint $table) {
            $table->id();

            // Code used by operator: A, B, F, W, N, X, P, ++, --, +-, -+
            $table->string('code', 20)->unique();

            // Display name
            $table->string('name', 100);

            // Explanation for Admin
            $table->text('description')->nullable();

            // number_set, special, dynamic, etc.
            $table->string('rule_type', 30);

            // Rule configuration stored as JSON
            $table->json('rule_config')->nullable();

            // Whether [ ] exclusion is allowed
            $table->boolean('allow_bracket')->default(false);

            // System rule or Admin-created rule
            $table->boolean('is_system')->default(false);

            // Admin can disable a rule
            $table->boolean('is_active')->default(true);

            // Display order
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_rules');
    }
};
