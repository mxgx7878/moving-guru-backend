<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grow-only promo codes.
 *
 * Deliberately a SEPARATE table from `promo_codes` (which powers the Stripe
 * subscription flow). These codes never touch Stripe — Grow charges are
 * one-off PaymentIntents, so the discount is computed here and subtracted
 * from the intent amount directly. Nothing in the subscription module reads
 * or writes this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('grow_promo_codes')) {
            return;
        }

        Schema::create('grow_promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('discount_type', 10);
            $table->decimal('discount_value', 10, 2);
            $table->string('currency', 3)->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('times_redeemed')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grow_promo_codes');
    }
};
