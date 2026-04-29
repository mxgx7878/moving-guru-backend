<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('userId')->constrained('users')->onDelete('cascade');
            $table->string('planId', 32);
            $table->string('stripeSubscriptionId', 64)->nullable()->unique();
            $table->enum('status', [
                'incomplete', 'trialing', 'active', 'past_due', 'cancelled', 'unpaid',
            ])->default('incomplete');
            $table->timestamp('currentPeriodStart')->nullable();
            $table->timestamp('currentPeriodEnd')->nullable();
            $table->boolean('cancelAtPeriodEnd')->default(false);
            $table->timestamp('cancelledAt')->nullable();
            $table->timestamp('trialEndsAt')->nullable();
            $table->timestamps();

            $table->index('userId');
            $table->index('status');

            $table->foreign('planId')->references('id')->on('plans');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};