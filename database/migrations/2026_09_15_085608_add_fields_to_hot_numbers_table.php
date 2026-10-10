<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hot_numbers')) {
            return;
        }

        Schema::table('hot_numbers', function (Blueprint $table) {
            if (! Schema::hasColumn('hot_numbers', 'round_id')) {
                $table->foreignId('round_id')
                    ->after('id')
                    ->constrained('rounds')
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('hot_numbers', 'number')) {
                $table->string('number', 2)
                    ->after('round_id');
            }
        });
    }

    public function down(): void
    {
        // The create migration owns the fresh-install schema.
    }
};
