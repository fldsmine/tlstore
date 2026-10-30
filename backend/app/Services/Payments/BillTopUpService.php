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

class BillTopUpService
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
    public function start(User $user, float $amountNgn): array
    {
        $gateway = $this->gateways->for(ChargePurpose::BILL);

        if (! $gateway->configured()) {
            return ['ok' => false, 'error' => 'Card top-up is not available right now.'];
        }

        if ($amountNgn <= 0) {
            return ['ok' => false, 'error' => 'Enter an amount to add.'];
        }

        $amountNgn = round($amountNgn, 2);

        // Serialize concurrent submits per user so a double-submit can't mint two payable charges;
        // the second caller blocks, then reuses the first's fresh checkout via openCheckoutFor().
        try {
            return Cache::lock("charge-start:bill:{$user->id}", 20)->block(8, fn (): array => $this->createOrReuse($user, $amountNgn, $gateway));
        } catch (LockTimeoutException) {
            return ['ok' => false, 'error' => 'A payment is already being set up — please wait a moment and try again.'];
        }
    }

    /**
     * @return array{ok: bool, error?: string, checkout_url?: string, reference?: string}
     */
    private function createOrReuse(User $user, float $amountNgn, PaymentGateway $gateway): array
    {
        $currency = $gateway->chargeCurrency();

        // Reuse a still-open checkout for the same amount + provider so a double-submit doesn't
        // mint a second payable charge.
        $existing = $this->openCheckoutFor($user, $amountNgn, $gateway->name());
        if ($existing !== null) {
            return ['ok' => true, 'checkout_url' => $existing['url'], 'reference' => $existing['reference']];
        }

        $reference = 'BTU-'.strtoupper(Str::random(16));
        $usd = round($this->currency->toUsd($amountNgn, 'NG'), 6);

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => TransactionType::BILLS_TOPUP,
            'direction' => 'credit',
            'amount' => $usd,
            'wallet' => 'bills',
            'status' => 'pending',
            'reference' => $reference,
            'provider' => $gateway->name(),
            'description' => 'Bills wallet top-up',
            'meta' => ['gateway' => $gateway->name(), 'amount_ngn' => $amountNgn, 'currency' => $currency],
        ]);

        $result = $gateway->initiate(new ChargeRequest(
            reference: $reference,
            amount: $amountNgn,
            currency: $currency,
            email: $user->email,
            customerName: $user->first_name ?: $user->username,
            narration: 'Nextel bills wallet top-up',
            callbackUrl: route('bills.topup.callback', ['reference' => $reference]),
            notificationUrl: route('webhooks.payments', ['provider' => $gateway->name()]),
            metadata: ['reference' => $reference, 'user_id' => $user->id, 'kind' => 'bills_topup'],
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
    private function openCheckoutFor(User $user, float $amountNgn, string $provider): ?array
    {
        $match = Transaction::where('user_id', $user->id)
            ->where('type', TransactionType::BILLS_TOPUP->value)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(15))
            ->latest()
            ->get()
            ->first(fn (Transaction $t): bool => filled($t->meta['checkout_url'] ?? null)
                && (string) ($t->meta['gateway'] ?? '') === $provider
                && (float) ($t->meta['amount_ngn'] ?? 0) === $amountNgn);

        return $match === null ? null : ['url' => (string) $match->meta['checkout_url'], 'reference' => (string) $match->reference];
    }

    protected function transactionType(): TransactionType
    {
        return TransactionType::BILLS_TOPUP;
    }

    protected function applyCompletion(Transaction $transaction): void
    {
        $user = User::whereKey($transaction->user_id)->lockForUpdate()->first();
        $user->increment('bills_balance', (float) $transaction->amount);
    }
}
