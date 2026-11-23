<?php

use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Identity
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('username')->nullable()->unique();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone')->nullable()->unique();
            $table->string('country', 2)->default('NG')->index(); // ISO2; relation key for Country (countryInfo)
            $table->string('avatar')->nullable();
            $table->text('biography')->nullable();
            $table->string('gender')->nullable();
            $table->string('status')->default(UserStatus::ACTIVE->value);

            // Agent / vendor (call + WhatsApp built from `phone`)
            $table->boolean('is_agent')->default(false);
            $table->boolean('is_special')->default(false); // special vendor — may transfer agent_balance to other vendors
            $table->boolean('withdrawals_disabled')->default(false); // admin kill-switch: block all withdrawals for this user
            $table->boolean('show_on_agents_page')->default(false);

            // Referral + package (the referral code IS the username)
            $table->foreignUuid('referrer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('package_id')->nullable()->index(); // FK to packages (created later); Eloquent relation only.
            $table->uuid('cloud_storage_plan_id')->nullable()->index(); // FK to cloud_storage_plans; Eloquent relation only.
            $table->timestamp('cloud_plan_expires_at')->nullable(); // Cloud plan validity end (null = no expiry)

            // Earning wallets — total is derived; lifetime only ever increases.
            $table->decimal('call_earnings_balance', 36, 6)->default(0);
            $table->decimal('ads_revenue_balance', 36, 6)->default(0);
            $table->decimal('referral_bonus_balance', 36, 6)->default(0);
            $table->decimal('reward_balance', 36, 6)->default(0); // withdrawable Rewards wallet (target rewards land here when claimed)
            $table->decimal('agent_balance', 36, 6)->default(0); // agents pay for tokens from here
            $table->decimal('cloud_balance', 36, 6)->default(0); // Nextel Cloud sponsored-call earnings
            $table->decimal('lifetime_earnings', 36, 6)->default(0)->index();

            // Auth / security
            $table->string('password');
            $table->string('transaction_pin')->nullable();

            $table->timestamp('last_claim_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index(); // online-presence heartbeat (Nextel Cloud broadcasts)
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
