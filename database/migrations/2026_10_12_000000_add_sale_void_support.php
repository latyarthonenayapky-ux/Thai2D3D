<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 'voided' is a status the columns were not created with, so the enums have
     * to be widened before a sale can be corrected.
     *
     * @var array<string, array{string, array<int, string>}>
     */
    private array $statusEnums = [
        'sale_inputs' => ['status', ['accepted', 'rejected', 'pending', 'voided']],
        'sale_details' => ['status', ['accepted', 'rejected', 'excluded', 'voided']],
        'three_digit_sale_inputs' => ['status', ['accepted', 'rejected', 'voided']],
        'three_digit_sale_details' => ['status', ['accepted', 'rejected', 'voided']],
    ];

    public function up(): void
    {
        foreach ($this->statusEnums as $table => [$column, $values]) {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $values): void {
                $blueprint->enum($column, $values)->default('accepted')->change();
            });
        }

        foreach (['sale_inputs', 'three_digit_sale_inputs'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->timestamp('voided_at')->nullable();
                $blueprint->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
                $blueprint->string('void_reason')->nullable();
                $blueprint->string('pre_void_status')->nullable();
                $blueprint->json('pre_void_detail_statuses')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['sale_inputs', 'three_digit_sale_inputs'] as $table) {
            DB::table($table)->where('status', 'voided')->update(['status' => 'rejected']);
        }
        foreach (['sale_details', 'three_digit_sale_details'] as $table) {
            DB::table($table)->where('status', 'voided')->update(['status' => 'accepted']);
        }

        foreach (['sale_inputs', 'three_digit_sale_inputs'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropConstrainedForeignId('voided_by');
                $blueprint->dropColumn([
                    'voided_at',
                    'void_reason',
                    'pre_void_status',
                    'pre_void_detail_statuses',
                ]);
            });
        }

        Schema::table('sale_inputs', function (Blueprint $blueprint): void {
            $blueprint->enum('status', ['accepted', 'rejected', 'pending'])->default('accepted')->change();
        });
        Schema::table('sale_details', function (Blueprint $blueprint): void {
            $blueprint->enum('status', ['accepted', 'rejected', 'excluded'])->default('accepted')->change();
        });
        foreach (['three_digit_sale_inputs', 'three_digit_sale_details'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->enum('status', ['accepted', 'rejected'])->default('accepted')->change();
            });
        }
    }
};
