<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('age')->nullable();
            $table->string('pronouns')->nullable();
            $table->string('studio')->nullable();
            $table->string('location')->nullable();
            $table->string('countryFrom')->nullable();
            $table->string('travelingTo')->nullable();
            $table->string('availability')->nullable();
            $table->json('disciplines')->nullable();
            $table->json('languages')->nullable();
            $table->json('openTo')->nullable();
            $table->string('profileStatus')->default('active');
            $table->text('bio')->nullable();
            $table->string('plan')->default('monthly');
            $table->json('social_links')->nullable();
            $table->string('profile_picture')->nullable();
            $table->string('background_image')->nullable();
            $table->json('gallery_photos')->nullable();
            $table->text('lookingFor')->nullable();
            $table->timestamps();
        });

        // Add role to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('client')->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::dropIfExists('user_details');
    }
};
