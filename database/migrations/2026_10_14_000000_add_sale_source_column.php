<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which channel typed a sale: 'web' is the Sale Entry screen and the browser
 * offline tool, 'phone' is the Agent app's auto-upload.
 *
 * The Admin's sales sheet has to show the two channels apart, but nothing ever
 * recorded them separately, so every row that already exists falls back to the
 * column default and reads as 'web'.
 */
return new class extends Migration
{
    private const TABLES = ['sale_inputs', 'three_digit_sale_inputs', 'offline_sync_reviews'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('source', 12)->default('web');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('source');
            });
        }
    }
};
