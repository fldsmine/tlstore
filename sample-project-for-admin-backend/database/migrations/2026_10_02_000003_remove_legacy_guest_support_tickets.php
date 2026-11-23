<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_tickets')
            || ! Schema::hasColumn('support_tickets', 'public_access_token')) {
            return;
        }

        // Earlier builds allowed tickets with no authenticated owner. They cannot
        // remain accessible under the registered-user-only support policy.
        $guestTicketIds = DB::table('support_tickets')
            ->whereNull('user_id')
            ->pluck('id');

        if ($guestTicketIds->isNotEmpty()) {
            DB::table('support_ticket_messages')
                ->whereIn('support_ticket_id', $guestTicketIds)
                ->delete();
            DB::table('support_tickets')->whereIn('id', $guestTicketIds)->delete();
        }

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropColumn('public_access_token');
        });

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->uuid('user_id')->nullable(false)->change();
        });

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('support_tickets')
            || Schema::hasColumn('support_tickets', 'public_access_token')) {
            return;
        }

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->text('public_access_token')->nullable();
        });

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->uuid('user_id')->nullable()->change();
        });

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};
