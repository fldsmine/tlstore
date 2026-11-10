<?php

namespace Tests\Feature;

use Database\Seeders\SubscriptionPlanSeeder;
use App\Application\Actions\Marketplace\ListingEligibility;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Application\Actions\Subscriptions\PurchaseSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPackageGroupsTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_catalog_returns_the_three_groups_and_their_packages(): void
    {
        $this->seed(SubscriptionPlanSeeder::class);

        $response = $this->getJson('/api/v1/subscription-plans');

        $response->assertOk()->assertJsonCount(3, 'groups');
        $groups = collect($response->json('groups'))->keyBy('group_key');
        $this->assertSame(['Lite', 'Premium', 'Gold'], collect($groups['group_a']['plans'])->pluck('name')->all());
        $this->assertSame(['Playstore review', 'Samsung store'], collect($groups['group_b']['plans'])->pluck('name')->all());
        $this->assertSame(['Apple store'], collect($groups['group_c']['plans'])->pluck('name')->all());
    }

    public function test_seeded_packages_match_task_store_and_withdrawal_rules(): void
    {
        $this->seed(SubscriptionPlanSeeder::class);

        $plans = \App\Models\SubscriptionPlan::query()->with('group')->get()->keyBy('plan_key');
        foreach (['demo-market-lite', 'demo-market-premium', 'demo-market-gold'] as $key) {
            $this->assertSame('group_a', $plans[$key]->group->group_key);
            $this->assertSame(0, $plans[$key]->max_review_tasks);
            $this->assertSame(['Monthly 28th'], $plans[$key]->withdrawal_days);
            $this->assertArrayNotHasKey('global_listing_access', $plans[$key]->getAttributes());
        }

        $this->assertSame(600000, $plans['demo-market-lite']->minimum_withdrawal_minor);
        $this->assertSame(1150000, $plans['demo-market-premium']->minimum_withdrawal_minor);
        $this->assertSame(1850000, $plans['demo-market-gold']->minimum_withdrawal_minor);
        foreach (['demo-market-lite', 'demo-market-premium', 'demo-market-gold'] as $key) {
            $this->assertNull($plans[$key]->task_reward_minor);
            $this->assertNull($plans[$key]->daily_task_limit);
            $this->assertNotNull($plans[$key]->daily_revenue_minor);
            $this->assertArrayNotHasKey('task_reward', $plans[$key]->features);
        }

        $this->assertSame('Google Play', $plans['review-google-play-4m']->eligible_app_store);
        $this->assertSame('Samsung Galaxy Store', $plans['review-samsung-store-4m']->eligible_app_store);
        $this->assertSame('Apple', $plans['review-apple-store-4m']->eligible_app_category);
        $this->assertSame(0, $plans['review-google-play-4m']->max_ad_shares);
    }

    public function test_user_can_keep_packages_active_across_groups_and_switch_within_a_group(): void
    {
        $this->seed(SubscriptionPlanSeeder::class);
        $user = User::query()->create([
            'full_name' => 'Switching Package User',
            'username' => 'switching-package-user',
            'email' => 'switching-package@example.test',
            'country_code' => null,
            'preferred_currency' => 'NGN',
            'role' => 'reviewer',
            'status' => 'active',
        ]);
        DB::table('wallets')->insert([
            'user_id' => $user->id,
            'wallet_type' => 'topup_fund',
            'currency' => 'NGN',
            'balance_minor' => 20000000,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $plans = SubscriptionPlan::query()->with('group')->orderBy('price_minor')->get();
        $purchasedGroups = [];
        foreach ($plans as $plan) {
            $subscription = app(PurchaseSubscription::class)->handle(
                $user,
                $plan,
                'switch-'.str_replace('_', '-', $plan->group->group_key).'-'.$plan->plan_key,
            );
            $this->assertSame('active', $subscription->status->value);
            $this->assertSame('marketplace_access', $subscription->product_type->value);
            $this->assertSame($plan->id, $subscription->subscription_plan_id);
            $purchasedGroups[] = $plan->group->group_key;
        }

        $this->assertSame(['group_a', 'group_a', 'group_a', 'group_b', 'group_b', 'group_c'], $purchasedGroups);
        $activeSubscriptions = UserSubscription::query()
            ->where('user_id', $user->id)
            ->where('product_type', 'marketplace_access')
            ->where('status', 'active')
            ->with('plan.group')
            ->get();
        $this->assertCount(3, $activeSubscriptions);
        $this->assertSame(['group_a', 'group_b', 'group_c'], $activeSubscriptions
            ->pluck('plan.group.group_key')->sort()->values()->all());

        $user->forceFill(['subscription_questionnaire_completed_groups' => ['group_b', 'group_c']])->save();
        $eligibility = app(ListingEligibility::class);
        $this->assertSame('eligible', $eligibility->taskAccessStatus($user, null, taskType: 'ad'));
        $this->assertSame('eligible', $eligibility->taskAccessStatus($user, null, null, 'Apple', 'review'));

        $lite = SubscriptionPlan::query()->where('plan_key', 'demo-market-lite')->firstOrFail();
        $replacement = app(PurchaseSubscription::class)->handle($user, $lite, 'switch-back-to-lite');
        $this->assertSame('active', $replacement->status->value);
        $this->assertSame(3, UserSubscription::query()
            ->where('user_id', $user->id)
            ->where('product_type', 'marketplace_access')
            ->where('status', 'active')
            ->count());
        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'subscription_plan_id' => SubscriptionPlan::query()->where('plan_key', 'review-samsung-store-4m')->value('id'),
            'status' => 'active',
        ]);
    }

    public function test_active_package_restricts_task_types_and_store_scope(): void
    {
        $this->seed(SubscriptionPlanSeeder::class);
        $user = User::query()->create([
            'full_name' => 'Package Test User',
            'username' => 'package-test-user',
            'email' => 'package-test@example.test',
            'country_code' => null,
            'preferred_currency' => 'USD',
            'role' => 'reviewer',
            'status' => 'active',
        ]);
        $lite = SubscriptionPlan::query()->where('plan_key', 'demo-market-lite')->firstOrFail();
        UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $lite->id,
            'product_type' => 'marketplace_access',
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'plan_snapshot' => [],
        ]);

        $eligibility = app(ListingEligibility::class);
        $this->assertSame('eligible', $eligibility->taskAccessStatus($user, null, taskType: 'ad'));
        $this->assertSame('review_package_required', $eligibility->taskAccessStatus($user, null, 'Google Play', null, 'review'));

        $reviewer = User::query()->create([
            'full_name' => 'Review Package User',
            'username' => 'review-package-user',
            'email' => 'review-package@example.test',
            'country_code' => null,
            'preferred_currency' => 'NGN',
            'subscription_questionnaire_completed_groups' => ['group_b'],
            'role' => 'reviewer',
            'status' => 'active',
        ]);
        $playStore = SubscriptionPlan::query()->where('plan_key', 'review-google-play-4m')->firstOrFail();
        UserSubscription::query()->create([
            'user_id' => $reviewer->id,
            'subscription_plan_id' => $playStore->id,
            'product_type' => 'marketplace_access',
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonths(4),
            'plan_snapshot' => [],
        ]);

        $this->assertTrue($eligibility->appAllowed($reviewer, null, 'Google Play', 'Finance'));
        $this->assertFalse($eligibility->appAllowed($reviewer, null, 'Samsung Galaxy Store', 'Finance'));
        $this->assertSame('share_package_required', $eligibility->taskAccessStatus($reviewer, null, taskType: 'ad'));
    }
}
