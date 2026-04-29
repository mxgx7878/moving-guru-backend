<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('userId')->constrained('users')->onDelete('cascade');
            $table->foreignId('subscriptionId')->nullable()
                  ->constrained('subscriptions')->onDelete('set null');
            $table->string('stripeInvoiceId', 64)->nullable()->unique();
            $table->string('stripePaymentIntentId', 64)->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->timestamp('paidAt')->nullable();
            $table->string('description', 255)->nullable();
            $table->text('hostedInvoiceUrl')->nullable();
            $table->text('invoicePdfUrl')->nullable();
            $table->timestamps();

            $table->index('userId');
            $table->index('subscriptionId');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};