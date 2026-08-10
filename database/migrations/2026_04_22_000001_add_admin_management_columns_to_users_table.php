<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('role')->index();

            $table->boolean('is_verified')->default(false)->after('status');

            $table->timestamp('approved_at')->nullable()->after('is_verified');
            $table->foreignId('approved_by')->nullable()->after('approved_at')
                  ->constrained('users')->nullOnDelete();

            $table->timestamp('suspended_at')->nullable()->after('approved_by');
            $table->text('suspension_reason')->nullable()->after('suspended_at');

            $table->timestamp('rejected_at')->nullable()->after('suspension_reason');
            $table->text('rejection_reason')->nullable()->after('rejected_at');

            $table->timestamp('last_login_at')->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'status', 'is_verified',
                'approved_at',
                'suspended_at', 'suspension_reason',
                'rejected_at',  'rejection_reason',
                'last_login_at',
            ]);
        });
    }
};