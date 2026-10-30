<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            if (! Schema::hasColumn('loans', 'guarantor_form_fee_reference')) {
                $table->string('guarantor_form_fee_reference', 64)->nullable()->after('exported_at');
                $table->index('guarantor_form_fee_reference', 'loans_guarantor_form_fee_reference_index');
            }

            if (! Schema::hasColumn('loans', 'guarantor_form_fee_amount')) {
                $table->decimal('guarantor_form_fee_amount', 36, 6)->nullable()->after('guarantor_form_fee_reference');
            }

            if (! Schema::hasColumn('loans', 'guarantor_form_fee_currency')) {
                $table->string('guarantor_form_fee_currency', 3)->nullable()->after('guarantor_form_fee_amount');
            }

            if (! Schema::hasColumn('loans', 'guarantor_form_fee_paid_at')) {
                $table->timestamp('guarantor_form_fee_paid_at')->nullable()->after('guarantor_form_fee_currency');
            }

            if (! Schema::hasColumn('loans', 'guarantor_form_data')) {
                $table->json('guarantor_form_data')->nullable()->after('guarantor_form_fee_paid_at');
            }

            if (! Schema::hasColumn('loans', 'guarantor_form_submitted_at')) {
                $table->timestamp('guarantor_form_submitted_at')->nullable()->after('guarantor_form_data');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            if (Schema::hasColumn('loans', 'guarantor_form_fee_reference')) {
                $table->dropIndex('loans_guarantor_form_fee_reference_index');
            }

            $columns = array_values(array_filter([
                'guarantor_form_fee_reference',
                'guarantor_form_fee_amount',
                'guarantor_form_fee_currency',
                'guarantor_form_fee_paid_at',
                'guarantor_form_data',
                'guarantor_form_submitted_at',
            ], fn (string $column): bool => Schema::hasColumn('loans', $column)));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
