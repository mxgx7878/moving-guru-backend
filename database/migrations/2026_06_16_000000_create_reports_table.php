<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reporterId');
            $table->unsignedBigInteger('reportedUserId');
            $table->unsignedBigInteger('conversationId')->nullable();
            $table->unsignedBigInteger('messageId')->nullable();
            $table->enum('type', ['message', 'profile']);
            $table->string('reason');
            $table->text('details')->nullable();
            $table->json('reportedMessage')->nullable();
            $table->json('contextSnapshot')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->foreign('reporterId')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('reportedUserId')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('conversationId')->references('id')->on('conversations')->nullOnDelete();
            $table->foreign('messageId')->references('id')->on('messages')->nullOnDelete();

            $table->index(['status', 'created_at']);
            $table->index('reportedUserId');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};