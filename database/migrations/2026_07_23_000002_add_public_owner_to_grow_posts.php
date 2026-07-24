<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grow_posts', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('contact_name')->nullable()->after('user_id');
            $table->string('contact_email')->nullable()->index()->after('contact_name');
            $table->string('organization_name')->nullable()->after('contact_email');
            $table->string('date_label')->nullable()->after('organization_name');
        });

        Schema::table('grow_post_payments', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('contact_email')->nullable()->index()->after('user_id');
            $table->string('stripe_customer_id')->nullable()->after('contact_email');
            $table->string('public_token_hash', 64)->nullable()->index()->after('stripe_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('grow_post_payments', function (Blueprint $table) {
            $table->dropColumn(['contact_email', 'stripe_customer_id', 'public_token_hash']);
        });
        Schema::table('grow_posts', function (Blueprint $table) {
            $table->dropColumn(['contact_name', 'contact_email', 'organization_name', 'date_label']);
        });
    }
};
