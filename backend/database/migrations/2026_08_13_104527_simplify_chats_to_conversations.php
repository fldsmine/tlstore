<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * LiveChat becomes plain conversational messaging: one persistent thread per (customer, agent),
     * no purchase flow. Drop the flow schema (status/deadlines/amount/proof/cart) and the coupon
     * sale binding; add a last_message_at sort key.
     */
    public function up(): void
    {
        // 1. Collapse each (customer, agent) pair to ONE conversation by MERGING, not discarding.
        //    The old flow minted a fresh chat row per purchase request, so a pair can hold several
        //    threads. We keep the earliest-started row as the survivor, re-point every message from
        //    the other threads onto it (preserving the full history rather than cascade-deleting it),
        //    fold the read markers forward, then delete the now message-less losers.
        $pairs = DB::table('chats')
            ->select('customer_id', 'agent_id')
            ->groupBy('customer_id', 'agent_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($pairs as $pair) {
            $rows = DB::table('chats')
                ->where('customer_id', $pair->customer_id)->where('agent_id', $pair->agent_id)
                ->orderBy('created_at')->orderBy('id')
                ->get();

            $survivorId = $rows->first()->id;
            $loserIds = $rows->slice(1)->pluck('id')->all();

            // Preserve conversation history: move the losers' messages onto the survivor.
            DB::table('chat_messages')->whereIn('chat_id', $loserIds)->update(['chat_id' => $survivorId]);

            // Keep the most-recent read point across the merged threads so unread counts stay correct.
            DB::table('chats')->where('id', $survivorId)->update([
                'customer_read_at' => $rows->pluck('customer_read_at')->filter()->max(),
                'agent_read_at' => $rows->pluck('agent_read_at')->filter()->max(),
            ]);

            // Losers now hold no messages; remove them (cascade has nothing left to delete).
            DB::table('chats')->whereIn('id', $loserIds)->delete();
        }

        // 2. Inbox sort key, backfilled from the latest message (or created_at for empty threads).
        //    Guarded so the migration can complete safely after a partial/failed prior run.
        if (! Schema::hasColumn('chats', 'last_message_at')) {
            Schema::table('chats', function (Blueprint $table) {
                $table->timestamp('last_message_at')->nullable()->after('agent_read_at')->index();
            });
        }

        DB::table('chats')->orderBy('id')->chunkById(200, function ($chats) {
            foreach ($chats as $chat) {
                $last = DB::table('chat_messages')->where('chat_id', $chat->id)->max('created_at');
                DB::table('chats')->where('id', $chat->id)->update(['last_message_at' => $last ?: $chat->created_at]);
            }
        });

        // 3. Drop the flow indexes + columns; enforce one-thread-per-pair.
        //    On MySQL the (agent_id, status) composite is the index backing the agent_id foreign key,
        //    so a plain agent_id index must exist BEFORE the composite is dropped (else error 1553).
        if (Schema::hasColumn('chats', 'status')) {
            if (! Schema::hasIndex('chats', 'chats_agent_id_index')) {
                Schema::table('chats', function (Blueprint $table) {
                    $table->index('agent_id');
                });
            }
            Schema::table('chats', function (Blueprint $table) {
                $table->dropIndex(['agent_id', 'status']);
            });
        }
        if (Schema::hasColumn('chats', 'ended_at')) {
            Schema::table('chats', function (Blueprint $table) {
                $table->dropIndex(['ended_at']);
            });
        }

        $flowColumns = array_values(array_filter([
            'status', 'region', 'amount', 'proof_path', 'satisfied', 'ended_reason', 'agent_messages_used',
            'accepted_at', 'pay_deadline_at', 'verify_deadline_at', 'escalation_ends_at', 'auto_close_at', 'ended_at',
        ], fn (string $column): bool => Schema::hasColumn('chats', $column)));
        if ($flowColumns !== []) {
            Schema::table('chats', function (Blueprint $table) use ($flowColumns) {
                $table->dropColumn($flowColumns);
            });
        }

        if (! Schema::hasIndex('chats', 'chats_customer_id_agent_id_unique')) {
            Schema::table('chats', function (Blueprint $table) {
                $table->unique(['customer_id', 'agent_id']);
            });
        }

        // 4. The cart table is gone.
        Schema::dropIfExists('chat_items');

        // 5. Coupons no longer bind to an in-app sale. Drop the FK + sold_at index before the columns.
        if (Schema::hasColumn('coupons', 'chat_id') || Schema::hasColumn('coupons', 'sold_at')) {
            Schema::table('coupons', function (Blueprint $table) {
                if (Schema::hasColumn('coupons', 'chat_id')) {
                    $table->dropForeign(['chat_id']);
                }
                if (Schema::hasColumn('coupons', 'sold_at')) {
                    $table->dropIndex(['sold_at']);
                }
            });
            $couponColumns = array_values(array_filter(
                ['sold_at', 'chat_id'],
                fn (string $column): bool => Schema::hasColumn('coupons', $column)
            ));
            Schema::table('coupons', function (Blueprint $table) use ($couponColumns) {
                $table->dropColumn($couponColumns);
            });
        }
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->timestamp('sold_at')->nullable()->after('used_at')->index();
            $table->foreignUuid('chat_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        Schema::create('chat_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('chat_id')->constrained()->cascadeOnDelete();
            $table->string('coupon_type');
            $table->uuid('product_id');
            $table->string('product_name');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 36, 6)->default(0);
            $table->timestamps();
            $table->index('chat_id');
        });

        Schema::table('chats', function (Blueprint $table) {
            $table->dropUnique(['customer_id', 'agent_id']);
            $table->dropIndex(['last_message_at']);
            $table->dropColumn('last_message_at');

            $table->string('status')->default('requested');
            $table->string('region')->default('nigeria');
            $table->decimal('amount', 36, 6)->default(0);
            $table->string('proof_path')->nullable();
            $table->boolean('satisfied')->nullable();
            $table->string('ended_reason')->nullable();
            $table->unsignedTinyInteger('agent_messages_used')->default(0);
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('pay_deadline_at')->nullable();
            $table->timestamp('verify_deadline_at')->nullable();
            $table->timestamp('escalation_ends_at')->nullable();
            $table->timestamp('auto_close_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->index(['agent_id', 'status']);
            $table->index('ended_at');
        });

        // The composite (agent_id, status) index above again backs the agent_id FK, so the standalone
        // agent_id index up() added can be dropped.
        if (Schema::hasIndex('chats', 'chats_agent_id_index')) {
            Schema::table('chats', function (Blueprint $table) {
                $table->dropIndex(['agent_id']);
            });
        }
    }
};
