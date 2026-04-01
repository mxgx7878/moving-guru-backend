<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viewer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('viewed_user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('viewed_at')->useCurrent();

            $table->index(['viewed_user_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_views');
    }
};
