<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add rejected_at timestamp to job_applications.
 *
 * Client spec: if a studio rejects an applicant, that instructor cannot
 * re-apply to the same job listing for 3 months. This column records the
 * moment of rejection so we can compute the re-apply unlock date cleanly
 * (can_reapply_at = rejected_at + 3 months).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('viewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropColumn('rejected_at');
        });
    }
};