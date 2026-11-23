<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            if (! Schema::hasColumn('loans', 'application_fee_type')) {
                $table->string('application_fee_type', 16)->default('fixed')->after('application_fee_currency');
            }

            if (! Schema::hasColumn('loans', 'application_fee_percentage')) {
                $table->decimal('application_fee_percentage', 8, 4)->nullable()->after('application_fee_type');
            }

            if (! Schema::hasColumn('loans', 'disbursement_account_name')) {
                $table->string('disbursement_account_name', 120)->nullable()->after('application_fee_coupon_id');
            }

            if (! Schema::hasColumn('loans', 'disbursement_account_number')) {
                $table->string('disbursement_account_number', 34)->nullable()->after('disbursement_account_name');
            }

            if (! Schema::hasColumn('loans', 'disbursement_bank_name')) {
                $table->string('disbursement_bank_name', 120)->nullable()->after('disbursement_account_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                'application_fee_type',
                'application_fee_percentage',
                'disbursement_account_name',
                'disbursement_account_number',
                'disbursement_bank_name',
            ], fn (string $column): bool => Schema::hasColumn('loans', $column)));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
