<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * job_listings
 * ------------------------------------------------------------------
 * Studios post job openings, swap offers, and energy exchanges here.
 * Named `job_listings` (not `jobs`) because Laravel's queue system
 * reserves the `jobs` table name — keeping the API URL as `/jobs`.
 *
 * Related: job_applications (next migration)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('job_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            $table->string('title');

            // hire = Direct Hire | swap = Instructor Swap | energy_exchange = Energy Exchange
            $table->enum('type', ['hire', 'swap', 'energy_exchange'])
                  ->default('hire');

            // Permanent / Temporary / Substitute / Weekend cover / Casual
            $table->enum('role_type', [
                'permanent', 'temporary', 'substitute', 'weekend_cover', 'casual',
            ])->default('permanent');

            $table->text('description');
            $table->json('disciplines')->nullable();
            $table->string('location')->nullable();
            $table->date('start_date')->nullable();
            $table->string('duration')->nullable();
            $table->string('compensation')->nullable();
            $table->text('requirements')->nullable();

            // Matches the QUALIFICATION_LEVELS list shared across frontend
            // (Studio Profile + JobListings form). Keep the two in sync.
            $table->enum('qualification_level', [
                'none', 'intermediate', 'diploma', 'bachelors', 'masters',
                'doctorate', 'cert_200hr', 'cert_500hr',
                'cert_comprehensive', 'cert_specialized',
            ])->default('none');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Useful indexes for the public browse endpoint
            $table->index(['is_active', 'type']);
            $table->index('location');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_listings');
    }
};