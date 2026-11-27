<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Support tickets were modelled for signed-in users only (`user_id` was made
 * NOT NULL by the original migration), which is why the public contact form had
 * nowhere to store a submission.
 *
 * Guest submissions reuse the existing inbox instead of a second table: the
 * owner becomes optional, and the guest's own name, email and request context
 * are stored on the ticket so support can reply by email.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('guest_name', 120)->nullable();
            $table->string('guest_email', 191)->nullable();
            $table->string('source', 24)->default('user');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->index(['guest_email', 'created_at']);
        });

        // Existing rows all came from the signed-in ticket screen.
        DB::table('support_tickets')->whereNull('source')->update(['source' => 'user']);
    }

    public function down(): void
    {
        // `user_id` cannot become NOT NULL while guest tickets exist.
        DB::table('support_tickets')->whereNull('user_id')->delete();

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropIndex(['guest_email', 'created_at']);
            $table->dropColumn(['guest_name', 'guest_email', 'source', 'ip_address', 'user_agent']);
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
