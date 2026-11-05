<?php

use App\Models\LedgerEntry;
use App\Observers\LedgerEntryObserver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transactions') || ! Schema::hasTable('ledger_entries')) {
            return;
        }

        $sync = app(LedgerEntryObserver::class);
        LedgerEntry::query()->with('wallet')->chunkById(250, function ($entries) use ($sync): void {
            foreach ($entries as $entry) {
                $sync->created($entry);
            }
        });
    }

    public function down(): void
    {
        // Transaction history is retained if this data migration is rolled back.
    }
};
