<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users
 *
 * Relationships:
 *   - country_code -> countries.code (null on delete)
 *   - has many social_identities, user_devices, user_notification_preferences, user_social_accounts
 *   - has many wallets, ledger_entries (through wallets), transactions
 *   - has many user_subscriptions, referral_codes
 *   - has many review_tasks (reviewer_id), review_submissions (reviewer_id), testimonials
 *   - audited by audit_logs.actor_user_id
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 180);
            $table->string('username', 80)->unique();
            $table->string('email', 191)->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('security_pin', 255)->nullable();
            $table->boolean('two_factor')->default(false);
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_setup_started_at')->nullable();
            $table->timestamp('two_factor_enabled_at')->nullable();
            $table->unsignedBigInteger('two_factor_last_counter')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->string('phone', 32)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->char('preferred_currency', 3)->default('USD');
            $table->json('subscription_questionnaire_answers')->nullable();
            $table->json('subscription_questionnaire_completed_groups')->nullable();
            $table->timestamp('subscription_questionnaire_completed_at')->nullable();
            $table->integer('has_addon')->default(0)->comment('shows user has a package that has addons on it or not. 0=does not, 1 has');
            $table->string('referral_code')->nullable()->comment('auto set from referral_codes table');
            $table->integer('is_member')->default(0)->comment('0=is not, 1 has subscription');
            $table->string('avatar', 512)->nullable();
            $table->string('cover_image', 512)->nullable();
            $table->string('role', 32)->default('reviewer');
            $table->string('status', 24)->default('active');
            $table->boolean('is_banned')->default(false);
            $table->text('ban_reason')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('country_code')->references('code')->on('countries')->nullOnDelete();
            $table->index(['status', 'country_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
