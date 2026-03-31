<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('age')->nullable();
            $table->string('pronouns')->nullable();
            $table->string('studio')->nullable();
            $table->string('location')->nullable();
            $table->string('countryFrom')->nullable();
            $table->string('travelingTo')->nullable();
            $table->string('availability')->nullable();
            $table->json('disciplines')->nullable();
            $table->json('languages')->nullable();
            $table->json('openTo')->nullable();
            $table->string('profileStatus')->default('active');
            $table->text('bio')->nullable();
            $table->string('plan')->default('monthly');
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