<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * audit_logs
 *
 * Append-only audit trail for user and admin actions.
 *
 * Relationships:
 *   - actor_user_id  -> users.id (null on delete)
 *   - actor_admin_id -> admins.id (null on delete)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actor_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('audit_key', 120)->nullable()->unique();
            $table->string('action', 100);
            $table->string('entity_type', 80);
            $table->string('entity_id', 100);
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->string('request_id', 100)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entity_type', 'entity_id']);
            $table->index(['actor_user_id', 'created_at']);
            $table->index(['actor_admin_id', 'created_at'], 'audit_admin_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
