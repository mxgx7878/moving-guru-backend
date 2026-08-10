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

            $table->enum('audience', ['all', 'instructors', 'studios'])
                  ->default('all');

            $table->enum('status', ['draft', 'published'])->default('draft');

            $table->string('cover_url')->nullable();
            $table->string('link_url')->nullable();
            $table->string('link_label')->nullable();

            $table->dateTime('event_date')->nullable();
            $table->string('event_location')->nullable();

            $table->boolean('is_pinned')->default(false);
            $table->timestamp('published_at')->nullable();

            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();

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