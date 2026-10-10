<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sale_input_id')
                ->constrained('sale_inputs')
                ->cascadeOnDelete();

            $table->string('number', 2);

            $table->decimal('amount', 12, 2)->default(0);

            $table->boolean('is_excluded')->default(false);

            $table->enum('status', [
                'accepted',
                'rejected',
            ])->default('accepted');

            $table->string('reject_reason')->nullable();

            $table->timestamps();

            $table->index([
                'number',
            ]);

            $table->index([
                'sale_input_id',
                'number',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_details');
    }
};
