<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An admin-dispatched sponsored SMS "campaign" — fanned out to every Cloud user's inbox.
        Schema::create('cloud_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cloud_number_id')->nullable()->index(); // the sponsor number it's "from"
            $table->string('sender_name');
            $table->string('sender_number')->nullable();
            $table->string('avatar')->nullable();
            $table->string('subject');
            $table->text('body');
            $table->decimal('earn_rate', 36, 6)->default(0); // USD credited on claim (0 = no earning)
            $table->string('claim_url')->nullable();         // optional sponsor "offer" link, opened on claim
            $table->uuid('created_by')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamps();
        });

        // Per-user delivered message (sponsored earning SMS OR a system notification). One shared inbox.
        Schema::create('cloud_inbox_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('sponsored'); // sponsored | system
            $table->uuid('cloud_message_id')->nullable()->index();
            $table->string('sender_name');
            $table->string('sender_number')->nullable();
            $table->string('avatar')->nullable();
            $table->string('subject');
            $table->text('body');
            $table->decimal('earn_rate', 36, 6)->default(0);
            $table->string('claim_url')->nullable(); // optional sponsor "offer" link, opened on claim
            $table->timestamp('read_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'claimed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloud_inbox_messages');
        Schema::dropIfExists('cloud_messages');
    }
};
