<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * job_listings.types  (multi-select)
 * ------------------------------------------------------------------
 * The `type` column was a single-value enum (hire | swap | energy_exchange).
 * Studios now want to mark a single listing as both Direct Hire AND Swap,
 * so we store the full set in a JSON `types` column. The original `type`
 * column stays as the "primary" type for backward compat with any code
 * still reading the singular field — it's set to types[0] on write.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            $table->json('types')->nullable()->after('type');
        });

        // Backfill: each existing job becomes [type]
        DB::statement('UPDATE job_listings SET types = JSON_ARRAY(type) WHERE types IS NULL');
    }

    public function down(): void
    {
        Schema::table('job_listings', function (Blueprint $table) {
            $table->dropColumn('types');
        });
    }
};