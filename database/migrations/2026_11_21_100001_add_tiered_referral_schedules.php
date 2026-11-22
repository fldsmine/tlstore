<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->json('subscription_referral_tiers')->nullable()->after('subscription_referral_percent');
            $table->json('task_referral_tiers')->nullable()->after('task_referral_percent');
        });

        Schema::table('review_tasks', function (Blueprint $table): void {
            $table->foreignId('subscription_plan_id')->nullable()->after('reviewer_id')->constrained('subscription_plans')->nullOnDelete();
        });

        $subscriptionTiers = json_encode(['1' => 10, '2' => 4, '3' => 1], JSON_THROW_ON_ERROR);
        $taskTiers = json_encode(['1' => 10, '2' => 5, '3' => 2], JSON_THROW_ON_ERROR);
        DB::table('subscription_plans')->update([
            'subscription_referral_tiers' => $subscriptionTiers,
            'task_referral_tiers' => $taskTiers,
            'subscription_referral_percent' => 10,
            'task_referral_percent' => 10,
        ]);

        Schema::table('referral_rewards', function (Blueprint $table): void {
            $table->foreignId('beneficiary_user_id')->nullable()->after('referral_attribution_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('referral_level')->default(1)->after('reward_type');
            $table->foreignId('subscription_plan_id')->nullable()->after('referral_level')->constrained('subscription_plans')->nullOnDelete();
            $table->decimal('reward_percent', 5, 2)->nullable()->after('subscription_plan_id');
            $table->unsignedBigInteger('base_amount_minor')->nullable()->after('reward_percent');
            $table->char('base_currency', 3)->nullable()->after('base_amount_minor');
        });

        DB::table('referral_rewards')
            ->whereIn('referral_attribution_id', DB::table('referral_attributions')->select('id'))
            ->update([
                'beneficiary_user_id' => DB::raw('(SELECT referrer_user_id FROM referral_attributions WHERE referral_attributions.id = referral_rewards.referral_attribution_id)'),
            ]);

        Schema::table('referral_rewards', function (Blueprint $table): void {
            //$table->dropUnique('referral_reward_source_unique');
            $table->unique(
                ['referral_attribution_id', 'beneficiary_user_id', 'referral_level', 'reward_type', 'source_type', 'source_id'],
                'referral_reward_tier_source_unique',
            );
            $table->index(['beneficiary_user_id', 'status', 'created_at'], 'referral_reward_beneficiary_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('referral_rewards', function (Blueprint $table): void {
            $table->dropUnique('referral_reward_tier_source_unique');
            $table->dropIndex('referral_reward_beneficiary_status_idx');
            $table->dropConstrainedForeignId('beneficiary_user_id');
            $table->dropColumn('referral_level');
            $table->dropConstrainedForeignId('subscription_plan_id');
            $table->dropColumn(['reward_percent', 'base_amount_minor', 'base_currency']);
            $table->unique(
                ['referral_attribution_id', 'reward_type', 'source_type', 'source_id'],
                'referral_reward_source_unique',
            );
        });

        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->dropColumn(['subscription_referral_tiers', 'task_referral_tiers']);
        });

        Schema::table('review_tasks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('subscription_plan_id');
        });
    }
};
