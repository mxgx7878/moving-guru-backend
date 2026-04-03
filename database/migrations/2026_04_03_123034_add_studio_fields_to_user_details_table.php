<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// php artisan migrate
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_details', function (Blueprint $table) {
            // Studio fields (camelCase — consistent with existing columns)
            $table->string('studioName')->nullable()->after('studio');
            $table->string('contactName')->nullable()->after('studioName');
            $table->string('country')->nullable()->after('location');
            $table->string('phone')->nullable()->after('country');
            $table->string('website')->nullable()->after('phone');
            $table->string('studioSize')->nullable()->after('website');
            $table->string('instagram')->nullable()->after('studioSize');

            // Instructor extra fields
            $table->string('availableFrom')->nullable()->after('availability');
            $table->string('availableTo')->nullable()->after('availableFrom');
            $table->boolean('flexibleDates')->default(false)->after('availableTo');
        });
    }

    public function down(): void
    {
        Schema::table('user_details', function (Blueprint $table) {
            $table->dropColumn([
                'studioName', 'contactName', 'country', 'phone',
                'website', 'studioSize', 'instagram',
                'availableFrom', 'availableTo', 'flexibleDates',
            ]);
        });
    }
};