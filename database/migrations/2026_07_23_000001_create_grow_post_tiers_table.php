<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grow_post_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('AUD');
            $table->unsignedSmallInteger('duration_days');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('grow_post_tiers')->insert([
            ['name' => '1 month', 'description' => 'Up to 30 days live', 'price' => 30, 'currency' => 'AUD', 'duration_days' => 30, 'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '3 months', 'description' => 'Up to 3 months live', 'price' => 40, 'currency' => 'AUD', 'duration_days' => 90, 'is_active' => true, 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '6 months', 'description' => 'Up to 6 months live', 'price' => 60, 'currency' => 'AUD', 'duration_days' => 180, 'is_active' => true, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('grow_post_tiers');
    }
};
