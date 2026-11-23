<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloud_inbox_messages', function (Blueprint $table) {
            // Thread grouping key: the unique sponsor number, so threads never merge when several
            // sponsors share a display line_number (e.g. a placeholder "0901 000 0000").
            $table->uuid('cloud_number_id')->nullable()->after('cloud_message_id')->index();
        });

        // Backfill existing sponsored messages from their source CloudMessage.
        DB::statement(
            'UPDATE cloud_inbox_messages
             SET cloud_number_id = (
                 SELECT cm.cloud_number_id FROM cloud_messages cm WHERE cm.id = cloud_inbox_messages.cloud_message_id
             )
             WHERE type = ? AND cloud_message_id IS NOT NULL',
            ['sponsored'],
        );
    }

    public function down(): void
    {
        Schema::table('cloud_inbox_messages', function (Blueprint $table) {
            $table->dropIndex(['cloud_number_id']);
            $table->dropColumn('cloud_number_id');
        });
    }
};
