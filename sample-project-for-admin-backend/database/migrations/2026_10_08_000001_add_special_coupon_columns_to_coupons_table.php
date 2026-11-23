<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            // HMAC-SHA256 hash for secure lookup (64 characters)
            if (!Schema::hasColumn('coupons', 'code_hash')) {
                $table->string('code_hash', 64)->nullable()->after('code');
            }

            // Expiration timestamp for the coupon
            if (!Schema::hasColumn('coupons', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('used_at');
            }

            // Indexes for performance
            if (!Schema::hasIndex('coupons', 'code_hash')) {
                $table->index('code_hash');
            }

            if (!Schema::hasIndex('coupons', 'expires_at')) {
                $table->index('expires_at');
            }

            // Composite index for withdrawal validation (user_id + used_at)
            $table->index(['user_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (Schema::hasColumn('coupons', 'code_hash')) {
                $table->dropColumn('code_hash');
            }

            if (Schema::hasColumn('coupons', 'expires_at')) {
                $table->dropColumn('expires_at');
            }

            // Drop indexes if they exist
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes('coupons');

            if (isset($indexes['coupons_code_hash_index'])) {
                $table->dropIndex('coupons_code_hash_index');
            }

            if (isset($indexes['coupons_expires_at_index'])) {
                $table->dropIndex('coupons_expires_at_index');
            }

            if (isset($indexes['coupons_user_id_used_at_index'])) {
                $table->dropIndex('coupons_user_id_used_at_index');
            }
        });
    }
};
