<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\ChargePurpose;
use App\Enums\TransactionType;
use App\Models\CallType;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\ShareSubscriptionService;
use App\Services\Payments\Concerns\SettlesGatewayCharge;
use App\Services\Payments\DTO\ChargeRequest;
use App\Support\RegionPricing;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/** Direct purchase of a standalone share term for exactly one CallType. */
class ShareAccessPurchaseService
{
    use SettlesGatewayCharge;

    public function __construct(
        private PaymentGatewayManager $gateways,
        private CurrencyService $currency,
        private ShareSubscriptionService $shareSubscriptions,
        private PaymentReferenceLedger $ledger,
    ) {}

    /** @return array{ok: bool, error?: string, checkout_url?: string, reference?: string} */
    public function start(User $user, CallType $callType): array
    {
        $region = RegionPricing::forUser($user);
        if (! $this->shareSubscriptions->callTypeIsDirectlyPurchasable($callType, $region)) {
            return ['ok' => false, 'error' => 'That share product is not configured for direct purchase.'];
        }

        if (! $this->shareSubscriptions->canPurchaseCallType($user, $callType)) {
            return ['ok' => false, 'error' => 'An active share term is already in place. Share products do not stack.'];
        }

        try {
            $gateway = $this->gateways->for(ChargePurpose::PACKAGE);
        } catch (InvalidArgumentException) {
            return ['ok' => false, 'error' => 'The configured share payment channel is not available right now.'];
        }

        if (! $gateway->configured()) {
            return ['ok' => false, 'error' => 'Direct share purchase is not available right now.'];
        }

        $priceUsd = RegionPricing::customer($callType, $region);
        if ($priceUsd <= 0) {
            return ['ok' => false, 'error' => 'That share product is not on sale right now.'];
        }

        try {
            return Cache::lock("charge-start:share:{$user->id}", 20)
                ->block(8, fn (): array => $this->createOrReuse($user, $callType, $region, $priceUsd, $gateway));
        } catch (LockTimeoutException) {
            return ['ok' => false, 'error' => 'A share payment is already being set up — please wait and try again.'];
        }
    }

    /** @return array{ok: bool, error?: string, checkout_url?: string, reference?: string} */
    private function createOrReuse(User $user, CallType $callType, string $region, float $priceUsd, PaymentGateway $gateway): array
    {
        $currency = $gateway->chargeCurrency();
        $amountNgn = round($this->currency->toNgn($priceUsd), 2);
        if ($amountNgn <= 0) {
            return ['ok' => false, 'error' => 'That share product is not on sale right now.'];
        }

        $existing = Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', TransactionType::SHARE_ACCESS_PURCHASE->value)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(15))
            ->latest()
            ->get()
            ->first(fn (Transaction $transaction): bool => filled($transaction->meta['checkout_url'] ?? null)
                && (string) ($transaction->meta['gateway'] ?? '') === $gateway->name()
                && (string) ($transaction->meta['call_type_id'] ?? '') === $callType->id
                && (string) ($transaction->meta['region'] ?? '') === $region
                && (float) ($transaction->meta['amount_ngn'] ?? 0) === $amountNgn);

        if ($existing !== null) {
            return ['ok' => true, 'checkout_url' => (string) $existing->meta['checkout_url'], 'reference' => (string) $existing->reference];
        }

        $pending = Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', TransactionType::SHARE_ACCESS_PURCHASE->value)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($pending !== null) {
            return ['ok' => false, 'error' => 'Another share purchase is awaiting payment verification. Complete it or wait for its status before starting a different share product.'];
        }

        $reference = 'SHR-'.Str::upper(Str::random(16));
        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'type' => TransactionType::SHARE_ACCESS_PURCHASE,
            'direction' => 'debit',
            'amount' => $priceUsd,
            'wallet' => null,
            'status' => 'pending',
            'reference' => $reference,
            'provider' => $gateway->name(),
            'description' => 'Share access — '.$callType->name,
            'meta' => [
                'gateway' => $gateway->name(),
                'call_type_id' => $callType->id,
                'region' => $region,
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
            narration: 'Nextel share access — '.$callType->name,
            callbackUrl: route('share-access.buy.callback', ['reference' => $reference]),
            notificationUrl: route('webhooks.payments', ['provider' => $gateway->name()]),
            metadata: ['reference' => $reference, 'user_id' => $user->id, 'kind' => 'share_access_purchase'],
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
        return TransactionType::SHARE_ACCESS_PURCHASE;
    }

    /** Grant only after server-side payment verification and a fresh eligibility check. */
    protected function applyCompletion(Transaction $transaction): void
    {
        $callType = CallType::query()->find($transaction->meta['call_type_id'] ?? null);
        if ($callType === null || ! $this->shareSubscriptions->callTypeIsConfigured($callType)) {
            throw new RuntimeException('Purchased share product is no longer available');
        }

        $user = User::query()->whereKey($transaction->user_id)->lockForUpdate()->first();
        if ($user === null) {
            throw new RuntimeException('Share purchase has no resolvable user');
        }

        if (! $this->shareSubscriptions->canPurchaseCallType($user, $callType)) {
            throw new RuntimeException('Share access is no longer eligible because another share term is active');
        }

        $region = (string) ($transaction->meta['region'] ?? RegionPricing::forUser($user));
        $this->shareSubscriptions->startForCallType($user, $callType, $region);

        Log::info('Standalone share term granted by verified direct purchase', [
            'reference' => $transaction->reference,
            'user_id' => $user->id,
            'call_type_id' => $callType->id,
        ]);
    }
}
