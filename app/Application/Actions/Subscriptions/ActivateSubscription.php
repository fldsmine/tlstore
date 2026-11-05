<?php

namespace App\Application\Actions\Subscriptions;

use App\Application\Actions\Referrals\CreditReferralReward;
use App\Application\Money\CurrencyMinorUnits;
use App\Models\LedgerEntry;
use App\Models\PaymentIntent;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserLocationEntitlement;
use App\Models\UserSubscription;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

final class ActivateSubscription
{
    public function handle(User $user, SubscriptionPlan $plan, PaymentIntent $intent): UserSubscription
    {
        return DB::transaction(function () use ($user, $plan, $intent): UserSubscription {
            $existing = UserSubscription::query()->where('payment_intent_id', $intent->id)->first();
            if ($existing) {
                return $existing->load(['plan.features', 'locationEntitlements']);
            }

            $plan = SubscriptionPlan::query()->with(['features', 'locationCoverage'])->lockForUpdate()->findOrFail($plan->id);
            $now = $intent->paid_at ?? now();
            $startsAt = $now;
            $endsAt = match ($plan->billing_interval->value) {
                'weekly' => $startsAt->copy()->addWeeks($plan->duration_count),
                'monthly' => $startsAt->copy()->addMonthsNoOverflow($plan->duration_count),
                'quarterly' => $startsAt->copy()->addMonthsNoOverflow(3 * $plan->duration_count),
                default => throw new \RuntimeException('Unsupported subscription duration.'),
            };
            $targets = $plan->locationCoverage;

            if ($plan->product_type->value === 'location_coverage' && $targets->isEmpty()) {
                throw new \RuntimeException('The selected location plan has no configured coverage.');
            }

            // Switching marketplace packages replaces only the marketplace plan.
            // Active location add-ons and their entitlements remain independent.
            if ($plan->product_type->value === 'marketplace_access') {
                UserSubscription::query()
                    ->where('user_id', $user->id)
                    ->where('product_type', 'marketplace_access')
                    ->where('status', 'active')
                    ->where('ends_at', '>', $now)
                    ->update(['status' => 'cancelled', 'cancelled_at' => $now, 'updated_at' => $now]);
            }

            $discountPercent = min(max((float) $plan->discount, 0), 100);
            $subscription = UserSubscription::query()->create([
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'payment_intent_id' => $intent->id,
                'product_type' => $plan->product_type->value,
                'status' => 'active',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'plan_snapshot' => [
                    'plan_key' => $plan->plan_key,
                    'name' => $plan->name,
                    'currency' => $plan->currency,
                    'listed_price_minor' => $plan->price_minor,
                    'discount_percent' => $discountPercent,
                    'paid_amount_minor' => $intent->amount_minor,
                    'paid_currency' => $intent->currency,
                    'bonus' => $plan->bonus,
                    'max_review_tasks' => $plan->max_review_tasks,
                    'max_ad_shares' => $plan->max_ad_shares,
                    'global_listing_access' => $plan->global_listing_access,
                    'coverage' => $targets->map(fn ($target) => ['type' => $target->target_type, 'code' => $target->target_code])->all(),
                ],
            ]);

            if ($plan->product_type->value === 'location_coverage') {
                foreach ($targets as $target) {
                    UserLocationEntitlement::query()->create([
                        'user_id' => $user->id,
                        'user_subscription_id' => $subscription->id,
                        'target_type' => $target->target_type,
                        'target_code' => $target->target_code,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'status' => 'active',
                    ]);
                }
            }

            $bonusMinor = CurrencyMinorUnits::toMinor($plan->bonus, $plan->currency);
            if ($bonusMinor > 0 && $this->rewardSettingEnabled('rewards.subscription_bonus_active')) {
                $bonusWallet = Wallet::query()->firstOrCreate(
                    ['user_id' => $user->id, 'wallet_type' => 'bonus', 'currency' => $plan->currency],
                    ['status' => 'active'],
                );
                LedgerEntry::query()->firstOrCreate(['idempotency_key' => 'subscription-bonus:'.$intent->id], [
                    'wallet_id' => $bonusWallet->id,
                    'entry_type' => 'subscription_bonus',
                    'direction' => 'credit',
                    'amount_minor' => $bonusMinor,
                    'status' => 'posted',
                    'source_type' => 'user_subscription',
                    'source_id' => $subscription->id,
                    'description' => 'Plan welcome bonus',
                    'available_at' => $now,
                    'posted_at' => $now,
                ]);
            }

            app(CreditReferralReward::class)->handle(
                $user,
                $intent->amount_minor,
                $plan->currency,
                'subscription',
                'user_subscription',
                $subscription->id,
                'referral-subscription:'.$intent->id,
                'Referral subscription reward: '.$plan->name,
                $plan,
            );

            return $subscription->load(['plan.features', 'locationEntitlements']);
        });
    }

    private function rewardSettingEnabled(string $key): bool
    {
        $value = SystemSetting::query()->where('setting_key', $key)->value('value');
        if ($value === null) return true;

        return filter_var(is_array($value) ? ($value['enabled'] ?? true) : $value, FILTER_VALIDATE_BOOLEAN);
    }
}
