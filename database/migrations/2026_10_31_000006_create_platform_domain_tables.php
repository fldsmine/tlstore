<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->char('code', 2)->primary();
            $table->string('name', 120);
            $table->char('default_currency', 3)->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 120);
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        Schema::create('region_countries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->char('country_code', 2);
            $table->timestamps();
            $table->foreign('country_code')->references('code')->on('countries')->cascadeOnDelete();
            $table->unique(['region_id', 'country_code']);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 180);
            $table->string('email', 191)->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->char('country_code', 2)->nullable();
            $table->char('preferred_currency', 3)->default('USD');
            $table->string('role', 32)->default('reviewer');
            $table->string('status', 24)->default('active');
            $table->rememberToken();
            $table->timestamps();
            $table->foreign('country_code')->references('code')->on('countries')->nullOnDelete();
            $table->index(['status', 'country_code']);
        });

        Schema::create('social_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_user_id', 191);
            $table->string('provider_email', 191)->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_user_id']);
            $table->index(['user_id', 'provider']);
        });

        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 24);
            $table->text('push_token');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'revoked_at']);
        });

        Schema::create('app_review_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_key', 64)->unique();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('paid_amount_minor')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->string('location', 32)->nullable()->comment('Country or region code; NULL is global');
            $table->unsignedBigInteger('review_reward_minor');
            $table->unsignedInteger('maximum_review')->nullable();
            $table->unsignedInteger('reserved_reviews')->default(0);
            $table->unsignedInteger('completed_reviews')->default(0);
            $table->string('status', 24)->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'location']);
            $table->index(['app_id', 'status']);
        });

        Schema::create('app_store_listing_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->string('store', 80);
            $table->string('external_listing_id', 191)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedBigInteger('total_reviews')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();
            $table->index(['app_id', 'captured_at']);
            $table->index(['store', 'external_listing_id']);
        });

        Schema::create('review_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_key', 64)->unique();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->string('status', 24)->default('draft');
            $table->timestamps();
        });

        Schema::create('review_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_template_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 24)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['review_template_id', 'version']);
        });

        Schema::create('review_template_version_questions', function (Blueprint $table) {
            $table->id();

			$table->foreignId('review_template_version_id')
				  ->constrained('review_template_versions')
				  ->onDelete('cascade')
				  ->index('rtv_questions_rtv_id_foreign'); // Shorter identifier name
            $table->foreignId('review_question_id')->constrained('review_questions')->restrictOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->string('step_name', 120);
            $table->unsignedSmallInteger('question_order');
            $table->timestamps();
			$table->unique(['review_template_version_id', 'review_question_id'], 'review_template_review_question_ids_unique');
            $table->index(['review_template_version_id', 'step_order', 'question_order'], 'rtvq_order_idx');
        });

        Schema::create('review_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('app_review_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('review_template_version_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('reserved');
            $table->string('idempotency_key', 100)->unique();
            $table->unsignedBigInteger('reserved_reward_minor');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['app_review_campaign_id', 'reviewer_id']);
            $table->index(['reviewer_id', 'status']);
        });

        Schema::create('review_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_task_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('review_template_version_id')->constrained()->restrictOnDelete();
            $table->string('status', 32)->default('submitted');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['reviewer_id', 'status']);
        });

        Schema::create('review_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_question_id')->constrained('review_questions')->restrictOnDelete();
            $table->json('answer_value');
            $table->timestamps();
            $table->unique(['review_submission_id', 'review_question_id']);
        });

        Schema::create('moderation_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_submission_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 32);
            $table->string('reason_code', 64)->nullable();
            $table->text('reason')->nullable();
            $table->unsignedInteger('policy_version')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->index(['review_submission_id', 'decided_at']);
        });

        Schema::create('ad_share_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_id')->constrained('ads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('reserved');
            $table->string('idempotency_key', 100)->unique();
            $table->unsignedBigInteger('reserved_reward_minor');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['ad_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('ad_share_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_share_task_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 48);
            $table->string('tracking_token', 100)->unique();
            $table->string('external_reference', 191)->nullable();
            $table->string('status', 24)->default('pending_validation');
            $table->string('verification_method', 48)->nullable();
            $table->json('evidence')->nullable();
            $table->timestamp('shared_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
            $table->index(['ad_share_task_id', 'status']);
        });

        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_key', 64)->unique();
            $table->string('product_type', 32)->comment('marketplace_access or location_coverage');
            $table->string('name', 180);
            $table->string('tier_key', 64)->nullable();
            $table->string('billing_interval', 24);
            $table->unsignedSmallInteger('duration_count')->default(1);
            $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3)->default('USD');
            $table->unsignedInteger('max_review_tasks')->nullable();
            $table->unsignedInteger('max_ad_shares')->nullable();
            $table->boolean('global_listing_access')->default(false);
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['product_type', 'is_active']);
        });

        Schema::create('subscription_plan_features', function (Blueprint $table) {
            $table->id();
			$table->foreignId('subscription_plan_id')
				  ->constrained('subscription_plans')
				  ->onDelete('cascade')
				  ->index('rtv_subscription_rtv_id_foreign'); // Shorter identifier name
            $table->string('feature_key', 64);
            $table->string('label', 180);
            $table->json('value')->nullable();
            $table->timestamps();
			$table->unique(['subscription_plan_id', 'feature_key'], 'subscription_plan_feature_unique');
        });

        Schema::create('plan_location_coverage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 16);
            $table->string('target_code', 32);
            $table->timestamps();
            $table->unique(['subscription_plan_id', 'target_type', 'target_code'], 'plan_location_target_unique');
        });

        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 32);
            $table->string('source_type', 48)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('provider', 32);
            $table->string('provider_reference', 191)->nullable()->unique();
            $table->string('idempotency_key', 100)->unique();
            $table->string('status', 32)->default('pending');
            $table->json('provider_metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'purpose', 'status']);
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_intent_id')->constrained()->cascadeOnDelete();
            $table->string('provider_transaction_id', 191)->nullable()->unique();
            $table->string('transaction_type', 24);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 24);
            $table->json('provider_response')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
            $table->index(['payment_intent_id', 'status']);
        });

        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('provider_event_id', 191);
            $table->string('event_type', 100);
            $table->json('payload');
            $table->boolean('signature_valid')->default(false);
            $table->string('processing_status', 24)->default('received');
            $table->text('processing_error')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_event_id'], 'payment_webhook_provider_event_unique');
        });

        Schema::create('manual_deposit_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_intent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('bank_reference', 191)->nullable();
            $table->string('proof_storage_path', 512)->nullable();
            $table->string('status', 24)->default('submitted');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_intent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_type', 32);
            $table->string('status', 24)->default('pending');
            $table->string('provider_subscription_reference', 191)->nullable()->unique();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('plan_snapshot');
            $table->timestamps();
            $table->index(['user_id', 'product_type', 'status', 'ends_at'], 'user_subscription_access_idx');
        });

        Schema::create('user_location_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_subscription_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 16);
            $table->string('target_code', 32);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->index(['user_id', 'target_type', 'target_code', 'status', 'ends_at'], 'user_location_access_idx');
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('wallet_type', 24);
            $table->char('currency', 3)->default('USD');
            $table->string('status', 24)->default('active');
            $table->timestamps();
            $table->unique(['user_id', 'wallet_type', 'currency'], 'wallet_user_type_currency_unique');
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->string('entry_type', 40);
            $table->string('direction', 8);
            $table->unsignedBigInteger('amount_minor');
            $table->string('status', 24)->default('posted');
            $table->string('source_type', 48)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('idempotency_key', 120)->unique();
            $table->string('description', 255)->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->index(['wallet_id', 'status', 'created_at']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->foreignId('withdrawal_option_id')->constrained('withdrawal_options')->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('fee_minor')->default(0);
            $table->char('currency', 3);
            $table->json('payout_details');
            $table->string('status', 24)->default('pending');
            $table->string('idempotency_key', 100)->unique();
            $table->string('provider_reference', 191)->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('failure_reason')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('referral_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64)->unique();
            $table->string('status', 24)->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('referral_attributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_code_id')->constrained()->restrictOnDelete();
            $table->foreignId('referrer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('status', 24)->default('attributed');
            $table->timestamp('attributed_at');
            $table->timestamp('qualified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_attribution_id')->constrained()->cascadeOnDelete();
            $table->string('reward_type', 32)->default('subscription');
            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->foreignId('ledger_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 24)->default('pending');
            $table->timestamps();
            $table->unique(['referral_attribution_id', 'reward_type', 'source_type', 'source_id'], 'referral_reward_source_unique');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 64);
            $table->string('title', 180);
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at', 'created_at']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 24)->default('push');
            $table->string('provider', 32)->nullable();
            $table->string('status', 24)->default('queued');
            $table->string('provider_message_id', 191)->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['notification_id', 'status']);
        });

        Schema::create('risk_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_submission_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('ad_share_event_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('signal_type', 64);
            $table->string('severity', 24)->default('info');
            $table->string('source', 48);
            $table->string('rule_version', 48)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['review_submission_id', 'severity']);
            $table->index(['ad_share_event_id', 'severity']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
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
        });

        Schema::create('outbox_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 120)->unique();
            $table->string('event_type', 100);
            $table->string('aggregate_type', 80);
            $table->string('aggregate_id', 100);
            $table->json('payload');
            $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('available_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['status', 'available_at']);
        });

        Schema::create('exchange_rate_snapshots', function (Blueprint $table) {
            $table->id();
            $table->char('base_currency', 3);
            $table->char('quote_currency', 3);
            $table->decimal('rate', 24, 12);
            $table->string('source', 100);
            $table->boolean('is_fallback')->default(false);
            $table->timestamp('fetched_at');
            $table->timestamp('source_published_at')->nullable();
            $table->timestamps();
            $table->unique(['base_currency', 'quote_currency', 'fetched_at'], 'fx_pair_fetched_unique');
			$table->index(['base_currency', 'quote_currency', 'fetched_at'], 'fx_pair_fetched_idx');
        });

        Schema::table('ads', function (Blueprint $table) {
            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
        });

        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('exchange_rate_snapshots');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('risk_signals');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('referral_rewards');
        Schema::dropIfExists('referral_attributions');
        Schema::dropIfExists('referral_codes');
        Schema::dropIfExists('withdrawal_requests');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('user_location_entitlements');
        Schema::dropIfExists('user_subscriptions');
        Schema::dropIfExists('manual_deposit_submissions');
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payment_intents');
        Schema::dropIfExists('plan_location_coverage');
        Schema::dropIfExists('subscription_plan_features');
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('ad_share_events');
        Schema::dropIfExists('ad_share_tasks');
        Schema::dropIfExists('moderation_decisions');
        Schema::dropIfExists('review_answers');
        Schema::dropIfExists('review_submissions');
        Schema::dropIfExists('review_tasks');
        Schema::dropIfExists('review_template_version_questions');
        Schema::dropIfExists('review_template_versions');
        Schema::dropIfExists('review_templates');
        Schema::dropIfExists('app_review_campaigns');
        Schema::dropIfExists('app_store_listing_snapshots');
        Schema::dropIfExists('user_devices');
        Schema::dropIfExists('social_identities');
        Schema::dropIfExists('users');
        Schema::dropIfExists('region_countries');
        Schema::dropIfExists('regions');
        Schema::dropIfExists('countries');
    }
};
