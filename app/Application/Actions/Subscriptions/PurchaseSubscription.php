<?php

namespace App\Application\Actions\Subscriptions;

use App\Models\LedgerEntry;
use App\Models\PaymentIntent;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PurchaseSubscription
{
    public function __construct(private readonly ActivateSubscription $activate) {}

    public function handle(User $user, SubscriptionPlan $plan, string $idempotencyKey): UserSubscription
    {
        return DB::transaction(function () use ($user, $plan, $idempotencyKey): UserSubscription {
            $plan = SubscriptionPlan::query()->lockForUpdate()->where('is_active', true)->findOrFail($plan->id);
            if ($plan->product_type->value !== 'marketplace_access') {
                throw ValidationException::withMessages(['subscription_plan_id' => 'Location coverage packages are no longer available.']);
            }
            $existingKey = 'subscription:'.$user->id.':'.hash('sha256', $idempotencyKey);
            $existingIntent = PaymentIntent::query()->where('idempotency_key', $existingKey)->first();
            if ($existingIntent) {
                if ((int) $existingIntent->source_id !== (int) $plan->id) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key was already used for a different plan.']);
                }
                return UserSubscription::query()->where('payment_intent_id', $existingIntent->id)->firstOrFail();
            }

            $activeSamePlan = UserSubscription::query()
                ->where('user_id', $user->id)
                ->where('subscription_plan_id', $plan->id)
                ->where('status', 'active')
                ->where('ends_at', '>', now())
                ->exists();
            if ($activeSamePlan) {
                throw ValidationException::withMessages(['subscription_plan_id' => 'This package is already active on your account.']);
            }

            $discountPercent = min(max((float) $plan->discount, 0), 100);
            $payable = (int) round($plan->price_minor * (100 - $discountPercent) / 100);
            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->where('wallet_type', 'topup_fund')
                ->where('currency', $plan->currency)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();
            if (! $wallet) {
                throw ValidationException::withMessages(['wallet' => 'Top-up funds are insufficient in '.$plan->currency.'. Use secure payment or fund your wallet first.']);
            }

            if ((int) $wallet->balance_minor < $payable) {
                throw ValidationException::withMessages(['wallet' => 'Top-up funds are insufficient for this package. Use secure payment or fund your wallet first.']);
            }

            $paymentIntent = PaymentIntent::query()->create([
                'user_id' => $user->id,
                'purpose' => 'subscription',
                'source_type' => 'subscription_plan',
                'source_id' => $plan->id,
                'amount_minor' => $payable,
                'currency' => $plan->currency,
                'provider' => 'wallet',
                'idempotency_key' => $existingKey,
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            LedgerEntry::query()->create([
                'wallet_id' => $wallet->id,
                'entry_type' => 'subscription_purchase',
                'direction' => 'debit',
                'amount_minor' => $payable,
                'status' => 'posted',
                'source_type' => 'payment_intent',
                'source_id' => $paymentIntent->id,
                'idempotency_key' => 'subscription-charge:'.$paymentIntent->id,
                'description' => 'Subscription purchase: '.$plan->name,
                'available_at' => now(),
                'posted_at' => now(),
            ]);

            return $this->activate->handle($user, $plan, $paymentIntent);
        });
    }
}
