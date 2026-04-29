<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->string('id', 32)->primary();
            $table->string('name', 64);
            $table->string('description', 255)->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('interval', ['month', 'year'])->default('month');
            $table->unsignedTinyInteger('intervalCount')->default(1);
            $table->string('period', 16);
            $table->string('stripePriceId', 64)->nullable();
            $table->json('features')->nullable();
            $table->boolean('isFeatured')->default(false);
            $table->boolean('isActive')->default(true);
            $table->unsignedSmallInteger('sortOrder')->default(0);
            $table->timestamps();
        });

        // Seed the three default plans. Replace `price_REPLACE_*` with the real
        // Stripe Price IDs from your Stripe Dashboard once products are created.
        DB::table('plans')->insert([
            [
                'id'             => 'monthly',
                'name'           => 'Monthly',
                'description'    => 'Flexible, cancel anytime',
                'price'          => 15.00,
                'interval'       => 'month',
                'intervalCount'  => 1,
                'period'         => '/mo',
                'stripePriceId'  => 'price_REPLACE_MONTHLY',
                'isFeatured'     => false,
                'sortOrder'      => 1,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'id'             => 'biannual',
                'name'           => '6 Months',
                'description'    => 'Save 50% vs monthly',
                'price'          => 45.00,
                'interval'       => 'month',
                'intervalCount'  => 6,
                'period'         => '/6mo',
                'stripePriceId'  => 'price_REPLACE_BIANNUAL',
                'isFeatured'     => true,
                'sortOrder'      => 2,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'id'             => 'annual',
                'name'           => '12 Months',
                'description'    => 'Best value — ~$5/mo',
                'price'          => 60.00,
                'interval'       => 'year',
                'intervalCount'  => 1,
                'period'         => '/yr',
                'stripePriceId'  => 'price_REPLACE_ANNUAL',
                'isFeatured'     => false,
                'sortOrder'      => 3,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};