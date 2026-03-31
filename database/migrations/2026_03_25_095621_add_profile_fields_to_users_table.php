<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'age')) {
                $table->integer('age')->nullable();
            }
            if (!Schema::hasColumn('users', 'pronouns')) {
                $table->string('pronouns')->nullable();
            }
            if (!Schema::hasColumn('users', 'studio')) {
                $table->string('studio')->nullable();
            }
            if (!Schema::hasColumn('users', 'location')) {
                $table->string('location')->nullable();
            }
            if (!Schema::hasColumn('users', 'countryFrom')) {
                $table->string('countryFrom')->nullable();
            }
            if (!Schema::hasColumn('users', 'travelingTo')) {
                $table->string('travelingTo')->nullable();
            }
            if (!Schema::hasColumn('users', 'availability')) {
                $table->string('availability')->nullable();
            }
            if (!Schema::hasColumn('users', 'disciplines')) {
                $table->json('disciplines')->nullable();
            }
            if (!Schema::hasColumn('users', 'languages')) {
                $table->json('languages')->nullable();
            }
            if (!Schema::hasColumn('users', 'openTo')) {
                $table->json('openTo')->nullable();
            }
            if (!Schema::hasColumn('users', 'profileStatus')) {
                $table->string('profileStatus')->default('active');
            }
            if (!Schema::hasColumn('users', 'bio')) {
                $table->text('bio')->nullable();
            }
            if (!Schema::hasColumn('users', 'plan')) {
                $table->string('plan')->default('monthly');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'age','pronouns','studio','location','countryFrom','travelingTo',
                'availability','disciplines','languages','openTo','profileStatus','bio','plan'
            ]);
        });
    }
};