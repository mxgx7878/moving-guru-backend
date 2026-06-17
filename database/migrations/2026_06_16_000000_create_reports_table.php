<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reporterId');       // who reported (receiver)
            $table->unsignedBigInteger('reportedUserId');   // who is reported (sender)
            $table->unsignedBigInteger('conversationId')->nullable();
            $table->unsignedBigInteger('messageId')->nullable();
            $table->enum('type', ['message', 'profile']);
            $table->string('reason');
            $table->text('details')->nullable();
            $table->json('reportedMessage')->nullable();    // snapshot of the reported message
            $table->json('contextSnapshot')->nullable();    // last 10 messages snapshot
            $table->string('status')->default('pending');   // pending|reviewed|resolved|dismissed
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