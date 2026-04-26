<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * saved_instructors
 * ------------------------------------------------------------------
 * A studio can "favourite" an instructor so they appear on the
 * Saved Instructors page. Pivot is symmetric to Job Applications —
 * one row per (studio, instructor) pair, unique constraint prevents
 * duplicates even if the client double-fires the save action.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('saved_instructors', function (Blueprint $table) {
            $table->id();

            $table->foreignId('studio_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            $table->foreignId('instructor_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            $table->timestamps();

            $table->unique(['studio_id', 'instructor_id'], 'uniq_studio_instructor_saved');
            $table->index('instructor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_instructors');
    }
};