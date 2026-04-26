<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration {
    public function up(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            $table->unsignedInteger('vacancies')->default(1)->after('requirements');
            $table->unsignedInteger('positions_filled')->default(0)->after('vacancies');
        });
    }

    public function down(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            $table->dropColumn(['vacancies', 'positions_filled']);
        });
    }
};