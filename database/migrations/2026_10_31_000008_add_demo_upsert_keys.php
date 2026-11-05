<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->unsignedBigInteger('reserved_share')->default(0)->comment('Share slots temporarily reserved for active tasks');
        });

        Schema::table('app_store_listing_snapshots', function (Blueprint $table) {
            $table->unique(['app_id', 'store', 'captured_at'], 'app_store_snapshot_unique');
        });

        Schema::table('manual_deposit_submissions', function (Blueprint $table) {
            $table->unique('payment_intent_id');
        });

        Schema::table('user_location_entitlements', function (Blueprint $table) {
            $table->unique(['user_subscription_id', 'target_type', 'target_code'], 'user_location_entitlement_unique');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->string('notification_key', 120)->nullable()->unique();
        });

        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->unique(['notification_id', 'user_device_id', 'channel'], 'notification_delivery_unique');
        });

        Schema::table('risk_signals', function (Blueprint $table) {
            $table->string('signal_key', 120)->nullable()->unique();
        });

        Schema::table('moderation_decisions', function (Blueprint $table) {
            $table->string('decision_key', 120)->nullable()->unique();
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('audit_key', 120)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropUnique(['audit_key']);
            $table->dropColumn('audit_key');
        });

        Schema::table('moderation_decisions', function (Blueprint $table) {
            $table->dropUnique(['decision_key']);
            $table->dropColumn('decision_key');
        });

        Schema::table('risk_signals', function (Blueprint $table) {
            $table->dropUnique(['signal_key']);
            $table->dropColumn('signal_key');
        });

        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->dropUnique('notification_delivery_unique');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique(['notification_key']);
            $table->dropColumn('notification_key');
        });

        Schema::table('user_location_entitlements', function (Blueprint $table) {
            $table->dropUnique('user_location_entitlement_unique');
        });

        Schema::table('manual_deposit_submissions', function (Blueprint $table) {
            $table->dropUnique(['payment_intent_id']);
        });

        Schema::table('app_store_listing_snapshots', function (Blueprint $table) {
            $table->dropUnique('app_store_snapshot_unique');
        });

        Schema::table('ads', function (Blueprint $table) {
            $table->dropColumn('reserved_share');
        });
    }
};
