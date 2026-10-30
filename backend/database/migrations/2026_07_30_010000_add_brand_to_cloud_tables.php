<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cloud calls/messages/inbox carry a denormalised sponsor `brand` snapshot. The column
     * lived in a dev-only migration, so main/staging/prod never got it while their code still
     * writes it — 500ing cloud calls & SMS. This adds it everywhere (idempotent).
     */
    public function up(): void
    {
        foreach (['cloud_calls', 'cloud_messages', 'cloud_inbox_messages'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'brand')) {
                Schema::table($table, fn (Blueprint $t) => $t->string('brand')->nullable());
            }
        }

        // Best-effort backfill from the still-linked sponsor number / parent message (portable
        // SQLite + MySQL). Wrapped so a backfill hiccup can never block the column add itself.
        try {
            DB::statement('UPDATE cloud_calls SET brand = (SELECT brand FROM cloud_numbers WHERE cloud_numbers.id = cloud_calls.cloud_number_id) WHERE brand IS NULL AND cloud_number_id IS NOT NULL');
            DB::statement('UPDATE cloud_messages SET brand = (SELECT brand FROM cloud_numbers WHERE cloud_numbers.id = cloud_messages.cloud_number_id) WHERE brand IS NULL AND cloud_number_id IS NOT NULL');
            DB::statement('UPDATE cloud_inbox_messages SET brand = (SELECT brand FROM cloud_messages WHERE cloud_messages.id = cloud_inbox_messages.cloud_message_id) WHERE brand IS NULL AND cloud_message_id IS NOT NULL');
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function down(): void
    {
        foreach (['cloud_calls', 'cloud_messages', 'cloud_inbox_messages'] as $table) {
            if (Schema::hasColumn($table, 'brand')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('brand'));
            }
        }
    }
};
