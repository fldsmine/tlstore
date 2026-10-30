<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            if (! Schema::hasColumn('loans', 'loan_type')) {
                $table->string('loan_type', 32)->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('loans', 'agreement_accepted_at')) {
                $table->timestamp('agreement_accepted_at')->nullable()->after('due_date');
            }

            if (! Schema::hasColumn('loans', 'application_fee_amount')) {
                $table->decimal('application_fee_amount', 36, 6)->nullable()->after('agreement_accepted_at');
            }

            if (! Schema::hasColumn('loans', 'application_fee_currency')) {
                $table->string('application_fee_currency', 3)->nullable()->after('application_fee_amount');
            }

            if (! Schema::hasColumn('loans', 'application_fee_reference')) {
                $table->string('application_fee_reference', 64)->nullable()->after('application_fee_currency');
                $table->index('application_fee_reference', 'loans_application_fee_reference_index');
            }

            if (! Schema::hasColumn('loans', 'application_fee_coupon_id')) {
                $table->foreignUuid('application_fee_coupon_id')->nullable()->after('application_fee_reference')
                    ->constrained('coupons')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            if (Schema::hasColumn('loans', 'application_fee_coupon_id')) {
                $table->dropConstrainedForeignId('application_fee_coupon_id');
            }

            if (Schema::hasColumn('loans', 'application_fee_reference')) {
                $table->dropIndex('loans_application_fee_reference_index');
            }

            $columns = array_values(array_filter([
                'loan_type',
                'agreement_accepted_at',
                'application_fee_amount',
                'application_fee_currency',
                'application_fee_reference',
            ], fn (string $column): bool => Schema::hasColumn('loans', $column)));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
