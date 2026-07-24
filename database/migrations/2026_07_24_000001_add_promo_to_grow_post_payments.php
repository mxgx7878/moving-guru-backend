<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record which Grow promo code (if any) was applied to a listing payment,
 * plus the pre-discount amount and the discount taken. All nullable — a
 * payment without a promo code just leaves these null, so existing rows and
 * the boost flow are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grow_post_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('grow_post_payments', 'grow_promo_code_id')) {
                $table->foreignId('grow_promo_code_id')
                    ->nullable()
                    ->after('pricing_tier')
                    ->constrained('grow_promo_codes')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('grow_post_payments', 'promo_code')) {
                $table->string('promo_code', 64)->nullable()->after('grow_promo_code_id');
            }
            if (!Schema::hasColumn('grow_post_payments', 'original_amount')) {
                $table->decimal('original_amount', 10, 2)->nullable()->after('amount');
            }
            if (!Schema::hasColumn('grow_post_payments', 'discount_amount')) {
                $table->decimal('discount_amount', 10, 2)->nullable()->after('original_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('grow_post_payments', function (Blueprint $table) {
            if (Schema::hasColumn('grow_post_payments', 'grow_promo_code_id')) {
                // Drop FK before column when the constrained() index exists.
                try { $table->dropForeign(['grow_promo_code_id']); } catch (\Throwable $e) {}
                $table->dropColumn('grow_promo_code_id');
            }
            foreach (['promo_code', 'original_amount', 'discount_amount'] as $col) {
                if (Schema::hasColumn('grow_post_payments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
