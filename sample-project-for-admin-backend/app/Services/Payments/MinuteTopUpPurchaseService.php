<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\ChargePurpose;
use App\Enums\TransactionType;
use App\Models\AdditionalMinutePlan;
use App\Models\ShareSubscription;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\ShareSubscriptionService;
use App\Services\PackageActivationService;
use App\Services\Payments\Concerns\SettlesGatewayCharge;
use App\Services\Payments\DTO\ChargeRequest;
use App\Support\RegionPricing;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/** Direct, verified purchase of additional share minutes for a live share term. */
class MinuteTopUpPurchaseService
{
    use SettlesGatewayCharge;

    public function __construct(
        private PaymentGatewayManager $gateways,
        private CurrencyService $currency,
        private PackageActivationService $activation,
        private ShareSubscriptionService $shareSubscriptions,
        private PaymentReferenceLedger $ledger,
    ) {}

    /** @return array{ok: bool, error?: string, checkout_url?: string, reference?: string} */
    public function start(User $user, AdditionalMinutePlan $plan): array
    {
        if (! (bool) config('settings.minutes.enabled', false)) {
            return ['ok' => false, 'error' => 'Share-minute top-ups are currently unavailable.'];
        }

        if (! $plan->is_active || (float) $plan->share_minutes <= 0 || (int) $plan->duration_days < 1) {
            return ['ok' => false, 'error' => 'That share-minute top-up is not available right now.'];
        }

        $subscription = $this->shareSubscriptions->current($user);
        if ($subscription === null) {
            return ['ok' => false, 'error' => 'Start a share term before buying share-minute top-ups.'];
        }

        // A share-only account must not pay for eSIM minutes or Call IDs it cannot use.
        $shareOnly = $user->package_id === null;
        if ($shareOnly && ((float) $plan->daily_call_minutes > 0 || (int) $plan->call_id_bonus > 0)) {
            return ['ok' => false, 'error' => 'Choose a share-only top-up; this plan also includes eSIM allowances.'];
        }

        try {
            $gateway = $this->gateways->for(ChargePurpose::PACKAGE);
        } catch (InvalidArgumentException) {
            return ['ok' => false, 'error' => 'The configured top-up payment channel is not available right now.'];
        }

        if (! $gateway->configured()) {
            return ['ok' => false, 'error' => 'Direct share-minute top-ups are not available right now.'];
        }

        $region = RegionPricing::forUser($user);
        $priceUsd = RegionPricing::customer($plan, $region);
        if ($priceUsd <= 0) {
            return ['ok' => false, 'error' => 'That top-up is not on sale right now.'];
        }

        try {
            return Cache::lock("charge-start:share-topup:{$user->id}", 20)
                ->block(8, fn (): array => $this->createOrReuse($user, $plan, $subscription, $region, $priceUsd, $shareOnly, $gateway));
        } catch (LockTimeoutException) {
            return ['ok' => false, 'error' => 'A top-up payment is already being set up — please wait and try again.'];
        }
    }

    /** @return array{ok: bool, error?: string, checkout_url?: string, reference?: string} */
    private function createOrReuse(
        User $user,
        AdditionalMinutePlan $plan,
        ShareSubscription $subscription,
        string $region,
        float $priceUsd,
        bool $shareOnly,
        PaymentGateway $gateway,
    ): array {
        $currency = $gateway->chargeCurrency();
        $amountNgn = round($this->currency->toNgn($priceUsd), 2);
        if ($amountNgn <= 0) {
            return ['ok' => false, 'error' => 'That top-up is not on sale right now.'];
        }

        $existing = Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', TransactionType::MINUTES_TOPUP->value)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(15))
            ->latest()
            ->get()
            ->first(fn (Transaction $transaction): bool => filled($transaction->meta['checkout_url'] ?? null)
                && (string) ($transaction->meta['gateway'] ?? '') === $gateway->name()
                && (string) ($transaction->meta['plan_id'] ?? '') === $plan->id
                && (string) ($transaction->meta['share_subscription_id'] ?? '') === $subscription->id
                && (string) ($transaction->meta['region'] ?? '') === $region
                && (float) ($transaction->meta['amount_ngn'] ?? 0) === $amountNgn);

        if ($existing !== null) {
            return ['ok' => true, 'checkout_url' => (string) $existing->meta['checkout_url'], 'reference' => (string) $existing->reference];
        }

        $reference = 'MIN-'.Str::upper(Str::random(16));
        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'type' => TransactionType::MINUTES_TOPUP,
            'direction' => 'debit',
            'amount' => $priceUsd,
            'wallet' => null,
            'status' => 'pending',
            'reference' => $reference,
            'provider' => $gateway->name(),
            'description' => 'Share-minute top-up — '.$plan->name,
            'meta' => [
                'gateway' => $gateway->name(),
                'plan_id' => $plan->id,
                'share_subscription_id' => $subscription->id,
                'region' => $region,
                'share_only' => $shareOnly,
                'amount_ngn' => $amountNgn,
                'currency' => $currency,
            ],
        ]);

        $result = $gateway->initiate(new ChargeRequest(
            reference: $reference,
            amount: $amountNgn,
            currency: $currency,
            email: $user->email,
            customerName: $user->first_name ?: $user->username,
            narration: 'Nextel share-minute top-up — '.$plan->name,
            callbackUrl: route('minutes.topup.buy.callback', ['reference' => $reference]),
            notificationUrl: route('webhooks.payments', ['provider' => $gateway->name()]),
            metadata: ['reference' => $reference, 'user_id' => $user->id, 'kind' => 'share_minute_topup'],
        ));

        if (! $result->ok) {
            $transaction->update([
                'status' => 'failed',
                'meta' => array_merge((array) $transaction->meta, ['error' => $result->message ?? 'init failed']),
            ]);

            return ['ok' => false, 'error' => $result->message ?? 'Could not start the payment.'];
        }

        $transaction->update([
            'gateway_reference' => $result->gatewayReference,
            'meta' => array_merge((array) $transaction->meta, ['checkout_url' => $result->checkoutUrl]),
        ]);

        return ['ok' => true, 'checkout_url' => (string) $result->checkoutUrl, 'reference' => $reference];
    }

    protected function transactionType(): TransactionType
    {
        return TransactionType::MINUTES_TOPUP;
    }

    /** No minutes are granted until the shared settlement trait verifies amount and currency. */
    protected function applyCompletion(Transaction $transaction): void
    {
        if (! (bool) config('settings.minutes.enabled', false)) {
            throw new RuntimeException('Share-minute top-ups were disabled before settlement');
        }

        $plan = AdditionalMinutePlan::query()->active()->find($transaction->meta['plan_id'] ?? null);
        if ($plan === null || (float) $plan->share_minutes <= 0 || (int) $plan->duration_days < 1) {
            throw new RuntimeException('Purchased share-minute top-up is no longer available');
        }

        $user = User::query()->whereKey($transaction->user_id)->lockForUpdate()->first();
        if ($user === null) {
            throw new RuntimeException('Share-minute top-up has no resolvable user');
        }

        $expectedSubscriptionId = (string) ($transaction->meta['share_subscription_id'] ?? '');
        $subscription = $this->shareSubscriptions->current($user, lock: true);
        if ($expectedSubscriptionId === '' || $subscription?->id !== $expectedSubscriptionId) {
            throw new RuntimeException('The share term changed before the top-up was settled');
        }

        $shareOnly = (bool) ($transaction->meta['share_only'] ?? false);
        if ($shareOnly && ((float) $plan->daily_call_minutes > 0 || (int) $plan->call_id_bonus > 0)) {
            throw new RuntimeException('This share-only top-up includes eSIM allowances and cannot be granted');
        }

        $this->activation->addMinutes(
            $user,
            $plan,
            shareOnly: $shareOnly,
            expectedShareSubscriptionId: $expectedSubscriptionId,
        );

        Log::info('Share-minute top-up granted by verified direct purchase', [
            'reference' => $transaction->reference,
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'share_subscription_id' => $expectedSubscriptionId,
        ]);
    }
}
