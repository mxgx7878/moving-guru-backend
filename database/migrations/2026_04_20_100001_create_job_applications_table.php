<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * job_applications
 * ------------------------------------------------------------------
 * An instructor applies / expresses interest in a studio's job
 * listing. Status starts 'pending' and the studio moves it through
 * viewed / accepted / rejected. The instructor can also withdraw.
 *
 * Unique (job_listing_id, instructor_id) prevents a single
 * instructor from applying to the same listing twice.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('job_listing_id')
                  ->constrained('job_listings')
                  ->onDelete('cascade');

            $table->foreignId('instructor_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            $table->text('message')->nullable();

            $table->enum('status', [
                'pending', 'viewed', 'accepted', 'rejected', 'withdrawn',
            ])->default('pending');

            $table->timestamp('viewed_at')->nullable();
            $table->timestamps();

            // One application per instructor per listing
            $table->unique(['job_listing_id', 'instructor_id'], 'uniq_job_instructor');

            $table->index(['instructor_id', 'status']);
            $table->index(['job_listing_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};