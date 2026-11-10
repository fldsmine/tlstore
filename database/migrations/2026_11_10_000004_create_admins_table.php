<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * admins
 *
 * Back-office operator accounts, separate from app users.
 *
 * Relationships:
 *   - has many system_settings (system_settings.updated_by_admin_id)
 *   - has many app_versions (app_versions.created_by)
 *   - has many moderation_decisions (moderation_decisions.decided_by_admin_id)
 *   - has many manual_deposit_submissions (manual_deposit_submissions.reviewed_by_admin_id)
 *   - has many withdrawal_requests (withdrawal_requests.processed_by_admin_id)
 *   - has many audit_logs (audit_logs.actor_admin_id)
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 180);
            $table->string('email', 191)->unique();
            $table->string('password');
            $table->string('role', 32)->default('admin');
            $table->string('status', 24)->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
