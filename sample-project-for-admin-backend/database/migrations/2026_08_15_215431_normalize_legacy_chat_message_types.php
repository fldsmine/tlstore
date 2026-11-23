<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The conversational-chat refactor slimmed App\Enums\ChatMessageType to text/system/image/file,
     * but chat_messages from the old purchase flow still carry retired types (product_prompt,
     * bank_details, proof, activation_code, satisfaction, telegram). Reading those rows now throws
     * a ValueError on the enum cast, which 500s any thread that contains one. Remap them to valid
     * types: the payment-proof upload (its image path lives in meta['path']) becomes an image;
     * every other retired type becomes a plain system line. Idempotent.
     */
    public function up(): void
    {
        DB::table('chat_messages')->where('type', 'proof')->update(['type' => 'image']);

        DB::table('chat_messages')
            ->whereNotIn('type', ['text', 'system', 'image', 'file'])
            ->update(['type' => 'system']);
    }

    public function down(): void
    {
        // The original per-message types cannot be reconstructed; nothing to reverse.
    }
};
