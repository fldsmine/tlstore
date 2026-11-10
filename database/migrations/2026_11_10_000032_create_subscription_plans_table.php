<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * subscription_plans
 *
 * Purchasable packages.
 *
 * Relationships:
 *   - subscription_group_id -> subscription_groups.id (null on delete)
 *   - has many subscription_plan_features, plan_location_coverage, user_subscriptions
 *
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_key', 64)->unique();
            $table->foreignId('subscription_group_id')->nullable()->constrained('subscription_groups')->nullOnDelete();
            $table->string('product_type', 32)->comment('marketplace_access or location_coverage');
            $table->string('name', 180);
            $table->string('tier_key', 64)->nullable();
            $table->string('billing_interval', 24);
            $table->unsignedSmallInteger('duration_count')->default(1);
            $table->unsignedBigInteger('price_minor');
            $table->char('currency', 3)->default('USD');
            $table->double('discount')->default(0)->comment('Discount if the package has one');
            $table->double('bonus')->default(0)->comment('Welcome bonus awarded for purchasing the package');
            $table->integer('is_special')->default(0)->comment('Whether the package is featured: 0 or 1');
            $table->unsignedInteger('max_review_tasks')->nullable();
            $table->unsignedInteger('max_ad_shares')->nullable();
            $table->string('eligible_app_store', 100)->nullable();
            $table->string('eligible_app_category', 100)->nullable();
            $table->unsignedBigInteger('minimum_withdrawal_minor')->nullable();
            $table->json('withdrawal_days')->nullable();
            $table->unsignedBigInteger('daily_revenue_minor')->nullable();
            $table->unsignedBigInteger('task_reward_minor')->nullable();
            $table->unsignedSmallInteger('daily_task_limit')->nullable();
            $table->decimal('subscription_referral_percent', 5, 2)->default(5)->comment('Referral reward percentage of the subscription amount paid');
            $table->decimal('task_referral_percent', 5, 2)->default(3)->comment('Referral reward percentage of an approved task reward');
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['product_type', 'is_active']);
            $table->index(['subscription_group_id', 'is_active'], 'subscription_group_active_idx');
            $table->index(['eligible_app_store', 'eligible_app_category'], 'subscription_plan_app_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
