<?php

namespace Tests\Feature;

use App\Application\Actions\Ads\ClaimAdShareTask;
use App\Application\Actions\Marketplace\DailyShareRevenue;
use App\Models\Ad;
use App\Models\ExchangeRateSnapshot;
use App\Models\LedgerEntry;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GroupADailyShareRevenueTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_revenue_counts_only_approved_share_rewards_posted_today(): void
    {
        $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);
        $user = $this->user();
        $wallet = $this->wallet($user, 'NGN');
        $this->reward($wallet->id, 15000, now()->subDay());
        $this->assertSame(0, app(DailyShareRevenue::class)->earnedTodayMinor($user, 'NGN'));

        $this->reward($wallet->id, 7000, now());
        $this->assertSame(7000, app(DailyShareRevenue::class)->earnedTodayMinor($user, 'NGN'));
    }

    public function test_daily_revenue_converts_share_rewards_to_plan_currency(): void
    {
        $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);
        $user = $this->user();
        $wallet = $this->wallet($user, 'USD');
        $this->reward($wallet->id, 100, now());
        ExchangeRateSnapshot::query()->create([
            'base_currency' => 'USD',
            'quote_currency' => 'NGN',
            'rate' => 1500,
            'source' => 'test',
            'is_fallback' => false,
            'fetched_at' => now(),
        ]);

        $this->assertSame(150000, app(DailyShareRevenue::class)->earnedTodayMinor($user, 'NGN'));
    }

    public function test_submitted_share_proofs_reserve_daily_revenue_capacity(): void
    {
        $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);
        $user = $this->user();
        $ad = $this->ad('pending-cap-ad');
        DB::table('ad_share_tasks')->insert([
            'ad_id' => $ad->id,
            'user_id' => $user->id,
            'status' => 'submitted',
            'idempotency_key' => 'pending-cap-proof',
            'reserved_reward_minor' => 7000,
            'claimed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $revenue = app(DailyShareRevenue::class);
        $this->assertSame(7000, $revenue->pendingMinor($user, 'NGN'));
        $this->assertSame(7000, $revenue->usedTodayMinor($user, 'NGN'));
    }

    public function test_group_a_blocks_new_ad_claims_once_daily_revenue_cap_is_reached(): void
    {
        $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);
        $user = $this->user();
        $plan = SubscriptionPlan::query()->where('plan_key', 'market-lite')->firstOrFail();
        UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'product_type' => 'marketplace_access',
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'plan_snapshot' => [],
        ]);
        $wallet = $this->wallet($user, 'NGN');
        $this->reward($wallet->id, (int) $plan->daily_revenue_minor, now());
        $ad = $this->ad('daily-cap-ad');

        try {
            app(ClaimAdShareTask::class)->handle($user, $ad, 'daily-cap-at-limit');
            $this->fail('An ad task should be blocked after the daily earnings cap is reached.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'daily earnings limit',
                strtolower($exception->errors()['daily_revenue'][0]),
            );
        }
        $this->assertDatabaseMissing('ad_share_tasks', ['idempotency_key' => 'daily-cap-at-limit']);

        $token = $user->createToken('daily-cap-test')->plainTextToken;
        $this->withToken($token)
            ->getJson('/api/v1/me/daily-tasks/ads')
            ->assertOk()
            ->assertJsonPath('meta.reason', 'daily_revenue_limit_reached')
            ->assertJsonPath('meta.daily_revenue_earned_minor', $plan->daily_revenue_minor);
    }

    public function test_group_a_allows_ads_when_earnings_are_below_the_daily_cap(): void
    {
        $this->seed(\Database\Seeders\SubscriptionPlanSeeder::class);
        $user = $this->user();
        $plan = SubscriptionPlan::query()->where('plan_key', 'market-lite')->firstOrFail();
        $plan->daily_task_limit = 1;
        $plan->save();
        UserSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'product_type' => 'marketplace_access',
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'plan_snapshot' => [],
        ]);

        $claim = app(ClaimAdShareTask::class);
        $task = $claim->handle($user, $this->ad('below-cap-ad'), 'daily-cap-below');
        $secondTask = $claim->handle($user, $this->ad('below-count-limit-ad'), 'daily-cap-second');

        $this->assertSame('reserved', $task->status->value);
        $this->assertSame('reserved', $secondTask->status->value);
    }

    private function user(): User
    {
        static $next = 0;
        $next++;

        return User::query()->create([
            'full_name' => 'Daily Revenue User '.$next,
            'username' => 'daily-revenue-'.$next,
            'email' => 'daily-revenue-'.$next.'@example.test',
            'country_code' => null,
            'preferred_currency' => 'NGN',
            'role' => 'reviewer',
            'status' => 'active',
        ]);
    }

    private function wallet(User $user, string $currency): object
    {
        $id = DB::table('wallets')->insertGetId([
            'user_id' => $user->id,
            'wallet_type' => 'share',
            'currency' => $currency,
            'balance_minor' => 0,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('wallets')->where('id', $id)->first();
    }

    private function reward(int $walletId, int $amountMinor, $postedAt): void
    {
        LedgerEntry::query()->create([
            'wallet_id' => $walletId,
            'entry_type' => 'ad_share_reward',
            'direction' => 'credit',
            'amount_minor' => $amountMinor,
            'status' => 'posted',
            'source_type' => 'ad_share_event',
            'source_id' => null,
            'idempotency_key' => 'test-share-reward-'.bin2hex(random_bytes(6)),
            'description' => 'Approved ad-share reward',
            'available_at' => $postedAt,
            'posted_at' => $postedAt,
        ]);
    }

    private function ad(string $campaignKey): Ad
    {
        return Ad::query()->create([
            'campaign_key' => $campaignKey,
            'title' => 'Daily cap test ad',
            'type' => 'others',
            'description' => 'Demo campaign',
            'share_message' => 'Share this campaign',
            'destination_url' => 'https://example.com/campaign',
            'paid_amount' => 1000,
            'currency' => 'NGN',
            'share_reward' => 100,
            'maximum_share' => 10,
            'status' => 'active',
        ]);
    }
}
