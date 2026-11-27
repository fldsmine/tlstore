<?php

use App\Enums\TransactionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `transactions.status` used to mix several spellings of "settled":
 * `posted` (the ledger status, copied verbatim), `success` and `successful`.
 * Phase 2 defines one vocabulary (`App\Enums\TransactionStatus`), so existing
 * rows are rewritten to their canonical value.
 *
 * The API keeps accepting the legacy spellings as aliases, so older clients
 * continue to work against the normalised data.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (TransactionStatus::legacyAliases() as $legacy => $canonical) {
            DB::table('transactions')
                ->where('status', $legacy)
                ->update(['status' => $canonical->value, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Lossy by design: `completed` cannot be mapped back to a specific
        // legacy spelling, and the canonical value is strictly more accurate.
    }
};
