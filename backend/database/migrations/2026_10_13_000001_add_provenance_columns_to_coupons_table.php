<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provenance for special (Storage-Activation) codes. Those codes now reach users three ways, and a
 * code that authorizes a withdrawal has to be traceable back to why it exists:
 *
 *   vendor  — minted by an agent (the original channel; the default for existing rows)
 *   direct  — bought by the user on the Revenue Authentication page with a card (payment_reference
 *             is the charge that paid for it)
 *   admin   — issued by a super admin against payment proof sent to support (issued_by_admin_id)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (! Schema::hasColumn('coupons', 'source')) {
                $table->string('source', 20)->default('vendor')->after('is_special');
            }

            if (! Schema::hasColumn('coupons', 'payment_reference')) {
                $table->string('payment_reference', 64)->nullable()->after('source');
            }

            if (! Schema::hasColumn('coupons', 'issued_by_admin_id')) {
                $table->foreignUuid('issued_by_admin_id')->nullable()->after('payment_reference')
                    ->constrained('admins')->nullOnDelete();
            }

            // Support answers "I paid but have no code" by searching the payment reference.
            // Why support handed the code out (payment proof, charge under review…) — a code that
            // authorises a real withdrawal should carry its reason.
            if (! Schema::hasColumn('coupons', 'issued_reason')) {
                $table->string('issued_reason', 255)->nullable()->after('issued_by_admin_id');
            }

            if (! Schema::hasIndex('coupons', 'payment_reference')) {
                $table->index('payment_reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (Schema::hasIndex('coupons', 'payment_reference')) {
                $table->dropIndex('coupons_payment_reference_index');
            }

            if (Schema::hasColumn('coupons', 'issued_by_admin_id')) {
                $table->dropConstrainedForeignId('issued_by_admin_id');
            }

            if (Schema::hasColumn('coupons', 'payment_reference')) {
                $table->dropColumn('payment_reference');
            }

            if (Schema::hasColumn('coupons', 'issued_reason')) {
                $table->dropColumn('issued_reason');
            }

            if (Schema::hasColumn('coupons', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
