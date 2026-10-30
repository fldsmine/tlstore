<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\ChargePurpose;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\Payments\Concerns\SettlesGatewayCharge;
use App\Services\Payments\PaymentReferenceLedger;
use App\Services\Payments\DTO\ChargeRequest;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WebsiteRenewalService
{
    use SettlesGatewayCharge;

    public function __construct(
        private PaymentGatewayManager $gateways,
        private CurrencyService $currency,
        private PaymentReferenceLedger $ledger,
    ) {}

    /**
     * @return array{ok: bool, error?: string, checkout_url?: string, reference?: string}
     */
    public function start(User $user): array
    {
        $gateway = $this->gateways->for(ChargePurpose::RENEWAL);

        if (! $gateway->configured()) {
            return ['ok' => false, 'error' => 'Website renewal is not available right now.'];
        }

        $priceUsd = round((float) config('settings.website.renewal_price'), 6);
        if ($priceUsd <= 0) {
            return ['ok' => false, 'error' => 'Website renewal is not available right now.'];
        }

        // Serialize concurrent submits per user so a double-click can't mint two payable charges.
        try {
            return Cache::lock("charge-start:renewal:{$user->id}", 20)->block(8, fn (): array => $this->createOrReuse($user, $priceUsd, $gateway));
        } catch (LockTimeoutException) {
            return ['ok' => false, 'error' => 'A payment is already being set up — please wait a moment and try again.'];
        }
    }

    /**
     * @return array{ok: bool, error?: string, checkout_url?: string, reference?: string}
     */
    private function createOrReuse(User $user, float $priceUsd, PaymentGateway $gateway): array
    {
        // Priced in USD like other tokens; converted to the gateway's charge currency at renewal time.
        $priceNgn = round($this->currency->toNgn($priceUsd), 2);
        $currency = $gateway->chargeCurrency();

        // Reuse a still-open checkout so a double-click doesn't mint a second payable charge.
        $existing = $this->openCheckoutFor($user, $gateway->name());
        if ($existing !== null) {
            return ['ok' => true, 'checkout_url' => $existing['url'], 'reference' => $existing['reference']];
        }

        $reference = 'WRN-'.strtoupper(Str::random(16));
        $days = (int) config('settings.website.duration_days');

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => TransactionType::WEBSITE_RENEWAL,
            'direction' => 'debit',
            'amount' => $priceUsd,
            'wallet' => null,
            'status' => 'pending',
            'reference' => $reference,
            'provider' => $gateway->name(),
            'description' => 'Personalized website renewal',
            'meta' => ['gateway' => $gateway->name(), 'amount_usd' => $priceUsd, 'amount_ngn' => $priceNgn, 'currency' => $currency, 'days' => $days],
        ]);

        $result = $gateway->initiate(new ChargeRequest(
            reference: $reference,
            amount: $priceNgn,
            currency: $currency,
            email: $user->email,
            customerName: $user->first_name ?: $user->username,
            narration: 'Nextel website renewal',
            callbackUrl: route('website.renew.callback', ['reference' => $reference]),
            notificationUrl: route('webhooks.payments', ['provider' => $gateway->name()]),
            metadata: ['reference' => $reference, 'user_id' => $user->id, 'kind' => 'website_renewal'],
        ));

        if (! $result->ok) {
            $transaction->update(['status' => 'failed', 'meta' => array_merge((array) $transaction->meta, ['error' => $result->message ?? 'init failed'])]);

            return ['ok' => false, 'error' => $result->message ?? 'Could not start the payment.'];
        }

        $transaction->update([
            'gateway_reference' => $result->gatewayReference,
            'meta' => array_merge((array) $transaction->meta, ['checkout_url' => $result->checkoutUrl]),
        ]);

        return ['ok' => true, 'checkout_url' => (string) $result->checkoutUrl, 'reference' => $reference];
    }

    /**
     * @return array{url: string, reference: string}|null
     */
    private function openCheckoutFor(User $user, string $provider): ?array
    {
        $match = Transaction::where('user_id', $user->id)
            ->where('type', TransactionType::WEBSITE_RENEWAL->value)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(15))
            ->latest()
            ->get()
            ->first(fn (Transaction $t): bool => filled($t->meta['checkout_url'] ?? null)
                && (string) ($t->meta['gateway'] ?? '') === $provider);

        return $match === null ? null : ['url' => (string) $match->meta['checkout_url'], 'reference' => (string) $match->reference];
    }

    protected function transactionType(): TransactionType
    {
        return TransactionType::WEBSITE_RENEWAL;
    }

    protected function applyCompletion(Transaction $transaction): void
    {
        $user = User::whereKey($transaction->user_id)->lockForUpdate()->first();
        $days = (int) ($transaction->meta['days'] ?? config('settings.website.duration_days'));
        $user->extendWebsite($days);
    }
}
