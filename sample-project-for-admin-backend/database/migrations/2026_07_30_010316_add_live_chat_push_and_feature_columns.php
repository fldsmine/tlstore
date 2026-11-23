<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('agent_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('requested');

            // The cart lives in chat_items; delivered codes bind back via coupons.chat_id (multi-buy).
            $table->string('region')->default('nigeria');
            $table->decimal('amount', 36, 6)->default(0); // whole-order total snapshot (USD)

            $table->string('proof_path')->nullable();

            $table->boolean('satisfied')->nullable();
            // Per-participant "seen up to" markers — drive the unread badges in the chat list.
            $table->timestamp('customer_read_at')->nullable();
            $table->timestamp('agent_read_at')->nullable();
            $table->string('ended_reason')->nullable();
            $table->unsignedTinyInteger('agent_messages_used')->default(0); // free-message cap

            // Server-authoritative deadlines
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('pay_deadline_at')->nullable();
            $table->timestamp('verify_deadline_at')->nullable();
            $table->timestamp('escalation_ends_at')->nullable();
            $table->timestamp('auto_close_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->timestamps();

            $table->index(['agent_id', 'status']);
            $table->index(['customer_id', 'created_at']);
            $table->index('ended_at'); // cleanup sweep
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('chat_id')->constrained()->cascadeOnDelete();
            $table->string('sender');            // ChatMessageSender: customer|agent|system
            $table->string('type')->default('text'); // ChatMessageType
            $table->text('body')->nullable();
            $table->json('meta')->nullable();    // attachment path, code, product payload, telegram links…
            $table->timestamps();

            $table->index(['chat_id', 'created_at']);
        });

        // A chat's cart — one row per (product, quantity) line a customer wants to buy in this session.
        Schema::create('chat_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('chat_id')->constrained()->cascadeOnDelete();
            $table->string('coupon_type');
            $table->uuid('product_id');
            $table->string('product_name');                 // snapshot — survives product edits/deletion
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 36, 6)->default(0); // region-resolved customer price at add time
            $table->timestamps();
            $table->index('chat_id');
        });

        Schema::table('coupons', function (Blueprint $table) {
            // Set when a live-chat sale binds this code to a customer. Distinct from used_at (redeemed).
            $table->timestamp('sold_at')->nullable()->after('used_at')->index();
            // A chat can own MANY delivered codes (multi-buy), so link each coupon back to its sale chat.
            $table->foreignUuid('chat_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        Schema::connection(config('webpush.database_connection'))->create(config('webpush.table_name'), function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->morphs('subscribable', 'push_subscriptions_subscribable_morph_idx');
            $table->string('endpoint', 500)->unique();
            $table->string('public_key')->nullable();
            $table->string('auth_token')->nullable();
            $table->string('content_encoding')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection(config('webpush.database_connection'))->dropIfExists(config('webpush.table_name'));

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropForeign(['chat_id']);
            $table->dropColumn(['chat_id', 'sold_at']);
        });

        Schema::dropIfExists('chat_items');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chats');
    }
};
