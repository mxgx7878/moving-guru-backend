<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reviewer_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            $table->foreignId('reviewee_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            $table->enum('direction', ['studio_to_instructor', 'instructor_to_studio']);

            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();

            $table->foreignId('job_listing_id')
                  ->nullable()
                  ->constrained('job_listings')
                  ->onDelete('set null');

            $table->timestamps();

            $table->index(['reviewee_id', 'direction']);
            $table->index('reviewer_id');

            $table->unique(
                ['reviewer_id', 'reviewee_id', 'job_listing_id'],
                'uniq_review_reviewer_reviewee_job'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};