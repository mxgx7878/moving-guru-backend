<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
         Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'social_links')) {
                $table->json('social_links')->nullable();
            }
            if (!Schema::hasColumn('users', 'profile_views')) {
                $table->unsignedBigInteger('profile_views')->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['social_links', 'profile_views']);
        });
    }
};