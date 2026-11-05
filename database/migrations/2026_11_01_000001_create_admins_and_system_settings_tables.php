<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table): void {
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

        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('setting_key', 120)->unique();
            $table->json('value')->nullable();
            $table->boolean('is_public')->default(false);
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('moderation_decisions', function (Blueprint $table): void {
            $table->foreignId('ad_share_event_id')->nullable()->after('review_submission_id')
                ->constrained('ad_share_events')->cascadeOnDelete();
            $table->foreignId('decided_by_admin_id')->nullable()->after('decided_by')
                ->constrained('admins')->nullOnDelete();
            $table->index(['ad_share_event_id', 'decided_at'], 'moderation_ad_event_decided_idx');
        });

        Schema::table('manual_deposit_submissions', function (Blueprint $table): void {
            $table->foreignId('reviewed_by_admin_id')->nullable()->after('reviewed_by')
                ->constrained('admins')->nullOnDelete();
        });

        Schema::table('withdrawal_requests', function (Blueprint $table): void {
            $table->foreignId('processed_by_admin_id')->nullable()->after('processed_by')
                ->constrained('admins')->nullOnDelete();
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->foreignId('actor_admin_id')->nullable()->after('actor_user_id')
                ->constrained('admins')->nullOnDelete();
            $table->index(['actor_admin_id', 'created_at'], 'audit_admin_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('audit_admin_created_idx');
            $table->dropConstrainedForeignId('actor_admin_id');
        });

        Schema::table('withdrawal_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('processed_by_admin_id');
        });

        Schema::table('manual_deposit_submissions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by_admin_id');
        });

        Schema::table('moderation_decisions', function (Blueprint $table): void {
            $table->dropIndex('moderation_ad_event_decided_idx');
            $table->dropConstrainedForeignId('decided_by_admin_id');
            $table->dropConstrainedForeignId('ad_share_event_id');
        });

        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('admins');
    }
};
