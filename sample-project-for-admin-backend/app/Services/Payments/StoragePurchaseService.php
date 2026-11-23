<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\ChargePurpose;
use App\Enums\TransactionType;
use App\Models\CloudStoragePlan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CloudActivationService;
use App\Services\CurrencyService;
use App\Services\Payments\Concerns\SettlesGatewayCharge;
use App\Services\Payments\PaymentReferenceLedger;
use App\Services\Payments\DTO\ChargeRequest;
use App\Support\RegionPricing;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Direct (card) purchase of a Cloud storage plan — the customer pays the platform instead of buying
 * a token from a vendor. The vendor/token channel stays the fallback; both grant the plan through
 * CloudActivationService::grantPack() so the entitlement is identical either way.
 */
class StoragePurchaseService
{
    use SettlesGatewayCharge;

    public const MODE_ACTIVATE = 'activate';

    public const MODE_SWITCH = 'switch';

    public const MODE_RENEW = 'renew';

    public function __construct(
        private PaymentGatewayManager $gateways,
        private CurrencyService $currency,
        private CloudActivationService $activation,
        private PaymentReferenceLedger $ledger,
    ) {}

    /**
     * @return array{ok: bool, error?: string, checkout_url?: string, reference?: string}
     */
    public function start(User $user, CloudStoragePlan $plan, string $mode): array
    {
        $gateway = $this->gateways->for(ChargePurpose::STORAGE);

        if (! $gateway->configured()) {
            return ['ok' => false, 'error' => 'Card payment is not available right now.'];
        }

        if (! in_array($mode, [self::MODE_ACTIVATE, self::MODE_SWITCH, self::MODE_RENEW], true)) {
            return ['ok' => false, 'error' => 'Choose whether you are activating, switching or renewing a plan.'];
        }

        // Tier-tagged plans are SPECIAL coupons — coupon-only, never sold directly. Re-checked here
        // (not just in the UI) so a crafted request can't buy what vendors alone may mint.
        if (! $plan->is_active || $plan->tier_level !== null) {
            return ['ok' => false, 'error' => 'That storage plan is not available for direct purchase.'];
        }

        // Same gate as redeeming a token: storage slots only make sense once Cloud calling is on.
        if (! $user->canCloudCall()) {
            return ['ok' => false, 'error' => 'Upgrade to the Royal eSIM before adding storage plans.'];
        }

        // A renewal is priced differently, so it must really BE one: the plan the user is on, and
        // only inside the expiry window — same rules a renewal token follows.
        if ($mode === self::MODE_RENEW) {
            $current = $this->activation->currentPack($user);

            if ($current === null || $current->cloud_storage_plan_id !== $plan->id) {
                return ['ok' => false, 'error' => 'Renewals only apply to the plan you are on — switch to this plan instead.'];
            }

            if (! $this->activation->withinRenewalWindow($current)) {
                return ['ok' => false, 'error' => 'You can renew only within a day of expiry. Please try again closer to your expiry date.'];
            }
        }

        $priceUsd = $this->priceFor($user, $plan, $mode);

        if ($priceUsd <= 0) {
            return ['ok' => false, 'error' => 'That plan is not on sale right now.'];
        }

        // Serialize concurrent submits per user so a double-submit can't mint two payable charges;
        // the second caller blocks, then reuses the first's fresh checkout via openCheckoutFor().
        try {
            return Cache::lock("charge-start:storage:{$user->id}", 20)
                ->block(8, fn (): array => $this->createOrReuse($user, $plan, $mode, $priceUsd, $gateway));
        } catch (LockTimeoutException) {
            return ['ok' => false, 'error' => 'A payment is already being set up — please wait a moment and try again.'];
        }
    }

    /** The customer price for the mode: a switch/activation is a fresh pack, a renewal is discounted. */
    private function priceFor(User $user, CloudStoragePlan $plan, string $mode): float
    {
        $region = RegionPricing::forUser($user);

        return $mode === self::MODE_RENEW
            ? RegionPricing::renewalCustomer($plan, $region)
            : RegionPricing::customer($plan, $region);
    }

    /**
     * @return array{ok: bool, error?: string, checkout_url?: string, reference?: string}
     */
    private function createOrReuse(User $user, CloudStoragePlan $plan, string $mode, float $priceUsd, PaymentGateway $gateway): array
    {
        $currency = $gateway->chargeCurrency();
        $amountNgn = round($this->currency->toNgn($priceUsd), 2);

        if ($amountNgn <= 0) {
            return ['ok' => false, 'error' => 'That plan is not on sale right now.'];
        }

        // Reuse a still-open checkout for the same plan + mode + provider so a double-submit doesn't
        // mint a second payable charge.
        $existing = $this->openCheckoutFor($user, $plan, $mode, $amountNgn, $gateway->name());
        if ($existing !== null) {
            return ['ok' => true, 'checkout_url' => $existing['url'], 'reference' => $existing['reference']];
        }

        $reference = 'STP-'.strtoupper(Str::random(16));

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => TransactionType::STORAGE_PURCHASE,
            'direction' => 'debit',
            'amount' => $priceUsd,
            'wallet' => null,
            'status' => 'pending',
            'reference' => $reference,
            'provider' => $gateway->name(),
            'description' => 'Cloud storage — '.$plan->name,
            'meta' => [
                'gateway' => $gateway->name(),
                'plan_id' => $plan->id,
                'mode' => $mode,
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
            narration: 'Nextel Cloud storage — '.$plan->name,
            callbackUrl: route('cloud.storage.buy.callback', ['reference' => $reference]),
            notificationUrl: route('webhooks.payments', ['provider' => $gateway->name()]),
            metadata: ['reference' => $reference, 'user_id' => $user->id, 'kind' => 'storage_purchase'],
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

    /**
     * @return array{url: string, reference: string}|null
     */
    private function openCheckoutFor(User $user, CloudStoragePlan $plan, string $mode, float $amountNgn, string $provider): ?array
    {
        $match = Transaction::where('user_id', $user->id)
            ->where('type', TransactionType::STORAGE_PURCHASE->value)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(15))
            ->latest()
            ->get()
            ->first(fn (Transaction $t): bool => filled($t->meta['checkout_url'] ?? null)
                && (string) ($t->meta['gateway'] ?? '') === $provider
                && (string) ($t->meta['plan_id'] ?? '') === $plan->id
                && (string) ($t->meta['mode'] ?? '') === $mode
                && (float) ($t->meta['amount_ngn'] ?? 0) === $amountNgn);

        return $match === null ? null : ['url' => (string) $match->meta['checkout_url'], 'reference' => (string) $match->reference];
    }

    protected function transactionType(): TransactionType
    {
        return TransactionType::STORAGE_PURCHASE;
    }

    /**
     * Grant the pack through the SAME path a redeemed token uses. Runs inside the settlement
     * transaction, so the status flip and the entitlement are one atomic unit.
     */
    protected function applyCompletion(Transaction $transaction): void
    {
        $plan = CloudStoragePlan::query()->find($transaction->meta['plan_id'] ?? null);

        // The plan must still be purchasable at settle time — buying is re-validated, never trusted
        // from the pending row. Throwing here fails the charge and flags it (see the trait).
        if ($plan === null || ! $plan->is_active || $plan->tier_level !== null) {
            throw new RuntimeException('Storage plan is no longer available for direct purchase');
        }

        $user = User::whereKey($transaction->user_id)->lockForUpdate()->first();

        if ($user === null) {
            throw new RuntimeException('Storage purchase has no resolvable user');
        }

        if (! $user->canCloudCall()) {
            throw new RuntimeException('Storage purchase user is no longer eligible for Cloud calling');
        }

        $this->activation->grantPack($user, $plan);

        Log::info('Storage plan granted by direct purchase', [
            'reference' => $transaction->reference,
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);
    }
}
