<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();

            $table->enum('type', ['announcement', 'event', 'news'])
                  ->default('announcement');

            $table->string('title');
            $table->text('body');

            // Who should see it: everyone, only instructors, or only studios
            $table->enum('audience', ['all', 'instructors', 'studios'])
                  ->default('all');

            // Draft vs. live on the platform
            $table->enum('status', ['draft', 'published'])->default('draft');

            $table->string('cover_url')->nullable();
            $table->string('link_url')->nullable();
            $table->string('link_label')->nullable();

            // Only relevant when type = event
            $table->dateTime('event_date')->nullable();
            $table->string('event_location')->nullable();

            $table->boolean('is_pinned')->default(false);
            $table->timestamp('published_at')->nullable();

            // Admin who authored it (soft-linked in case user is deleted)
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();

            // Hot paths: public feed queries
            $table->index(['status', 'audience']);
            $table->index(['type']);
            $table->index(['is_pinned', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};