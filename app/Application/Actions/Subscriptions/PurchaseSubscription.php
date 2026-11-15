<?php

namespace App\Application\Actions\Subscriptions;

use App\Models\LedgerEntry;
use App\Models\PaymentIntent;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\Wallet;
use App\Application\Money\CurrencyConverter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PurchaseSubscription
{
    public function __construct(
        private readonly ActivateSubscription $activate,
        private readonly CurrencyConverter $currencyConverter,
    ) {}

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
            $wallets = Wallet::query()
                ->where('user_id', $user->id)
                ->where('wallet_type', 'topup_fund')
                ->where('status', 'active')
                ->orderByRaw('CASE WHEN currency = ? THEN 0 ELSE 1 END', [strtoupper($plan->currency)])
                ->lockForUpdate()
                ->get();
            $wallet = null;
            $walletPayable = null;
            $walletExchangeRate = null;
            foreach ($wallets as $candidate) {
                try {
                    $converted = $this->currencyConverter->convertMinor($payable, $plan->currency, $candidate->currency);
                } catch (ValidationException) {
                    continue;
                }
                if ((int) $candidate->balance_minor >= $converted['amount_minor']) {
                    $wallet = $candidate;
                    $walletPayable = $converted['amount_minor'];
                    $walletExchangeRate = $converted['rate'];
                    break;
                }
            }
            if (! $wallet || $walletPayable === null) {
                throw ValidationException::withMessages(['wallet' => 'No top-up wallet has enough funds for this package after currency conversion. Use Secure payment or fund your wallet first.']);
            }

            $paymentIntent = PaymentIntent::query()->create([
                'user_id' => $user->id,
                'purpose' => 'subscription',
                'source_type' => 'subscription_plan',
                'source_id' => $plan->id,
                'amount_minor' => $walletPayable,
                'currency' => $wallet->currency,
                'provider' => 'wallet',
                'idempotency_key' => $existingKey,
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            LedgerEntry::query()->create([
                'wallet_id' => $wallet->id,
                'entry_type' => 'subscription_purchase',
                'direction' => 'debit',
                'amount_minor' => $walletPayable,
                'status' => 'posted',
                'source_type' => 'payment_intent',
                'source_id' => $paymentIntent->id,
                'idempotency_key' => 'subscription-charge:'.$paymentIntent->id,
                'description' => 'Subscription purchase: '.$plan->name.' ('.$plan->currency.' price, paid from '.$wallet->currency.' funds)',
                'metadata' => [
                    'package_price_minor' => $payable,
                    'package_currency' => strtoupper($plan->currency),
                    'wallet_debit_minor' => $walletPayable,
                    'wallet_currency' => strtoupper($wallet->currency),
                    'exchange_rate' => $walletExchangeRate,
                    'discount_percent' => $discountPercent,
                ],
                'available_at' => now(),
                'posted_at' => now(),
            ]);

            return $this->activate->handle($user, $plan, $paymentIntent);
        });
    }
}
