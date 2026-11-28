<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_impersonation_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Only the SHA-256 hash of the hand-off code is stored; the plain
            // value exists once, in the link the administrator opens.
            $table->char('code_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('consumed_ip', 45)->nullable();
            $table->string('consumed_user_agent', 255)->nullable();
            $table->foreignId('personal_access_token_id')->nullable()
                ->constrained('personal_access_tokens')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['admin_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_impersonation_links');
    }
};
