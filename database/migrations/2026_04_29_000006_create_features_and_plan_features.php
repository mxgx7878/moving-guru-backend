<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Master features table — defined once
        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('label', 120);
            $table->string('description', 255)->nullable();
            $table->enum('role', ['instructor', 'studio', 'both'])->default('both');
            $table->unsignedSmallInteger('sortOrder')->default(0);
            $table->timestamps();
        });

        // Seed the 9 features
        DB::table('features')->insert([
            ['key' => 'profile_visibility', 'label' => 'Profile Visibility',  'description' => 'Appear in studio search results',           'role' => 'instructor', 'sortOrder' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'job_applications',   'label' => 'Apply to Jobs',       'description' => 'Apply for studio job listings',             'role' => 'instructor', 'sortOrder' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'save_jobs',          'label' => 'Save Jobs',           'description' => 'Bookmark job listings for later',           'role' => 'instructor', 'sortOrder' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'post_jobs',          'label' => 'Post Jobs',           'description' => 'Create and manage job listings',            'role' => 'studio',     'sortOrder' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'search_instructors', 'label' => 'Search Instructors',  'description' => 'Browse the full instructor directory',      'role' => 'studio',     'sortOrder' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'favourites',         'label' => 'Save Favourites',     'description' => 'Bookmark favourite instructors',            'role' => 'studio',     'sortOrder' => 6, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'messaging',          'label' => 'Messaging',           'description' => 'Send and receive messages',                 'role' => 'both',       'sortOrder' => 7, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'grow_posts',         'label' => 'Grow Posts',          'description' => 'Create and publish posts on the Grow feed', 'role' => 'both',       'sortOrder' => 8, 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'reviews',            'label' => 'Reviews',             'description' => 'Give and receive reviews',                  'role' => 'both',       'sortOrder' => 9, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 2. Pivot table — references feature ID, not duplicated keys
        Schema::create('plan_features', function (Blueprint $table) {
            $table->string('planId', 32);
            $table->foreignId('featureId')->constrained('features')->onDelete('cascade');
            $table->primary(['planId', 'featureId']);
            $table->foreign('planId')->references('id')->on('plans')->onDelete('cascade');
        });

        // Seed: every plan gets every feature by default. Admin unchecks per plan.
        $planIds    = DB::table('plans')->pluck('id');
        $featureIds = DB::table('features')->pluck('id');
        $rows = [];
        foreach ($planIds as $planId) {
            foreach ($featureIds as $featureId) {
                $rows[] = ['planId' => $planId, 'featureId' => $featureId];
            }
        }
        if ($rows) DB::table('plan_features')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('features');
    }
};