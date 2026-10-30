<?php

use App\Enums\TransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('reference');
            $table->string('gateway_reference')->nullable()->after('provider');
            $table->index('gateway_reference');
        });

        // Pin every legacy in-flight charge to its originating provider so verify/requery/webhook
        // still route correctly after this ships. meta.gateway holds it today; default to korapay.
        // Portable across SQLite (dev) + MySQL (prod): decode meta in PHP rather than JSON_EXTRACT.
        DB::table('transactions')
            ->whereIn('type', [TransactionType::BILLS_TOPUP->value, TransactionType::WEBSITE_RENEWAL->value])
            ->whereNull('provider')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $meta = json_decode((string) ($row->meta ?? ''), true) ?: [];
                    DB::table('transactions')->where('id', $row->id)->update([
                        'provider' => (string) ($meta['gateway'] ?? 'korapay'),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['gateway_reference']);
            $table->dropColumn(['provider', 'gateway_reference']);
        });
    }
};
