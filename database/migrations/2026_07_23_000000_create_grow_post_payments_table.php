<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grow_post_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grow_post_id')->nullable()->constrained('grow_posts')->nullOnDelete();
            $table->string('purpose', 20);
            $table->string('pricing_tier', 20)->nullable();
            $table->unsignedSmallInteger('duration_days');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('aud');
            $table->string('stripe_payment_intent_id')->unique();
            $table->string('status', 30)->index();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grow_post_payments');
    }
};
