<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\ChargePurpose;
use App\Enums\TransactionType;
use App\Models\CloudStoragePlan;
use App\Models\Coupon;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\Payments\Concerns\SettlesGatewayCharge;
use App\Services\Payments\DTO\ChargeRequest;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Card purchase of a REVENUE AUTHENTICATION code from the platform itself — the "Pay directly with
 * card" option on /dashboard/special-coupon.
 *
 * This is what makes a special (tier) storage code buyable without a vendor: the customer pays the
 * platform, the charge settles like every other gateway charge, and settlement issues the code
 * through RevenueCodeService — bound to the buyer with the storage bonus already applied, so the
 * moment the user sees it, it can authorise their N-Cloud withdrawal.
 *
 * Everything money-related is inherited from SettlesGatewayCharge (server-side re-verification,
 * row-locked idempotency, mandatory amount/currency/reference reconciliation, single-use ledger
 * claim). This class only says WHAT the money buys.
 */
class SpecialCouponPurchaseService
{
    use SettlesGatewayCharge;

    public function __construct(
        private PaymentGatewayManager $gateways,
        private CurrencyService $currency,
        private RevenueCodeService $codes,
        private PaymentReferenceLedger $ledger,
    ) {}

    /**
     * Whether the card option can be offered right now — and when it can't, why not, so the page can
     * say what to do instead (upgrade, wait for the plan to be published, source a vendor coupon).
     *
     * @return array{available: bool, plan: ?CloudStoragePlan, price: float, reason: ?string}
     */
    public function availability(User $user, ?CloudStoragePlan $plan = null): array
    {
        try {
            $gateway = $this->gateways->for(ChargePurpose::SPECIAL_COUPON);
        } catch (\InvalidArgumentException) {
            return ['available' => false, 'plan' => null, 'price' => 0.0, 'reason' => 'Card payment is not available right now.'];
        }

        if (! $gateway->configured()) {
            return ['available' => false, 'plan' => null, 'price' => 0.0, 'reason' => 'Card payment is not available right now.'];
        }

        // Cloud calling has to be on for the storage the code grants to mean anything.
        if (! $user->canCloudCall()) {
            return ['available' => false, 'plan' => null, 'price' => 0.0, 'reason' => 'Upgrade to the Royal eSIM before buying a revenue code.'];
        }

        $plan ??= $this->codes->planFor($user);

        if ($plan === null || ! $plan->is_active || $plan->tier_level === null) {
            return ['available' => false, 'plan' => null, 'price' => 0.0, 'reason' => 'No revenue code is on sale for your package tier right now.'];
        }

        // A crafted request must not be able to buy a plan published for another tier.
        if ((int) $plan->tier_level !== (int) $user->package?->tier_level) {
            return ['available' => false, 'plan' => null, 'price' => 0.0, 'reason' => 'That revenue code is not for your package tier.'];
        }

        $priceUsd = $this->codes->priceUsd($user, $plan);

        if ($priceUsd <= 0 || $this->currency->toNgn($priceUsd) <= 0) {
            return ['available' => false, 'plan' => $plan, 'price' => 0.0, 'reason' => 'That revenue code is not on sale right now.'];
        }

        return ['available' => true, 'plan' => $plan, 'price' => $priceUsd, 'reason' => null];
    }

    /**
     * Start (or reuse) a payable charge for a revenue code. Re-validates everything server-side; the
     * page's button state is never the authority.
     *
     * @return array{ok: bool, error?: string, checkout_url?: string, reference?: string}
     */
    public function start(User $user, ?CloudStoragePlan $plan = null): array
    {
        $availability = $this->availability($user, $plan);

        if (! $availability['available'] || $availability['plan'] === null) {
            return ['ok' => false, 'error' => $availability['reason'] ?? 'Card payment is not available right now.'];
        }

        $gateway = $this->gateways->for(ChargePurpose::SPECIAL_COUPON);

        // Serialize concurrent submits per user so a double-tap can't mint two payable charges; the
        // second caller blocks, then reuses the first's still-open checkout.
        try {
            return Cache::lock("charge-start:special-coupon:{$user->id}", 20)
                ->block(8, fn (): array => $this->createOrReuse($user, $availability['plan'], $availability['price'], $gateway));
        } catch (LockTimeoutException) {
            return ['ok' => false, 'error' => 'A payment is already being set up — please wait a moment and try again.'];
        }
    }

    /**
     * The caller's still-open checkout, if any — so a user who closed the gateway tab can pick the
     * same payment back up instead of starting (and paying) a second one.
     *
     * @return array{url: string, reference: string}|null
     */
    public function openCheckout(User $user): ?array
    {
        $match = Transaction::where('user_id', $user->id)
            ->where('type', TransactionType::SPECIAL_COUPON_PURCHASE->value)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(15))
            ->latest()
            ->get()
            ->first(fn (Transaction $t): bool => filled($t->meta['checkout_url'] ?? null));

        return $match === null ? null : ['url' => (string) $match->meta['checkout_url'], 'reference' => (string) $match->reference];
    }

    /**
     * @return array{ok: bool, error?: string, checkout_url?: string, reference?: string}
     */
    private function createOrReuse(User $user, CloudStoragePlan $plan, float $priceUsd, PaymentGateway $gateway): array
    {
        $currency = $gateway->chargeCurrency();
        $amountNgn = round($this->currency->toNgn($priceUsd), 2);

        $existing = $this->openCheckoutFor($user, $plan, $amountNgn, $gateway->name());

        if ($existing !== null) {
            return ['ok' => true, 'checkout_url' => $existing['url'], 'reference' => $existing['reference']];
        }

        $reference = 'SPC-'.strtoupper(Str::random(16));

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => TransactionType::SPECIAL_COUPON_PURCHASE,
            'direction' => 'debit',
            'amount' => $priceUsd,
            'wallet' => null,
            'status' => 'pending',
            'reference' => $reference,
            'provider' => $gateway->name(),
            'description' => 'Revenue code — '.$plan->name,
            'meta' => [
                'gateway' => $gateway->name(),
                'plan_id' => $plan->id,
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
            narration: 'Nextel revenue code — '.$plan->name,
            callbackUrl: route('special-coupon.buy.callback', ['reference' => $reference]),
            notificationUrl: route('webhooks.payments', ['provider' => $gateway->name()]),
            metadata: ['reference' => $reference, 'user_id' => $user->id, 'kind' => 'special_coupon'],
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
    private function openCheckoutFor(User $user, CloudStoragePlan $plan, float $amountNgn, string $provider): ?array
    {
        $match = Transaction::where('user_id', $user->id)
            ->where('type', TransactionType::SPECIAL_COUPON_PURCHASE->value)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(15))
            ->latest()
            ->get()
            ->first(fn (Transaction $t): bool => filled($t->meta['checkout_url'] ?? null)
                && (string) ($t->meta['gateway'] ?? '') === $provider
                && (string) ($t->meta['plan_id'] ?? '') === $plan->id
                && (float) ($t->meta['amount_ngn'] ?? 0) === $amountNgn);

        return $match === null ? null : ['url' => (string) $match->meta['checkout_url'], 'reference' => (string) $match->reference];
    }

    protected function transactionType(): TransactionType
    {
        return TransactionType::SPECIAL_COUPON_PURCHASE;
    }

    /**
     * The code is issued right here, inside the settlement transaction — so a completed charge and
     * the code it bought are one atomic unit. If issuing throws (plan unpublished, tier changed,
     * eligibility lost), the trait fails the charge and flags it for manual reconciliation rather
     * than hand out a code the user cannot hold.
     */
    protected function applyCompletion(Transaction $transaction): void
    {
        $user = User::whereKey($transaction->user_id)->lockForUpdate()->first();

        if ($user === null) {
            throw new RuntimeException('Revenue code purchase has no resolvable user');
        }

        if (! $user->canCloudCall()) {
            throw new RuntimeException('Revenue code buyer is no longer eligible for Cloud calling');
        }

        // Re-resolve the plan at settle time and re-check the tier: paying minutes after an upgrade or
        // after the plan was unpublished must not mint a code nobody can use.
        $availability = $this->availability($user, CloudStoragePlan::query()->find($transaction->meta['plan_id'] ?? null));

        if (! $availability['available'] || $availability['plan'] === null) {
            throw new RuntimeException($availability['reason'] ?? 'Revenue code plan is no longer available');
        }

        $issued = $this->codes->issueFor(
            $user,
            $availability['plan'],
            Coupon::SOURCE_DIRECT,
            (string) $transaction->reference,
        );

        // The charge points at the coupon it bought; the raw code stays on the coupon row, where the
        // buyer reads it back on their own page. Nothing secret is duplicated into transaction meta.
        $transaction->update([
            'meta' => array_merge((array) $transaction->meta, [
                'coupon_id' => $issued['coupon']->id,
                'plan_id' => $availability['plan']->id,
            ]),
        ]);

        Log::info('Revenue code issued by direct purchase', [
            'reference' => $transaction->reference,
            'user_id' => $user->id,
            'plan_id' => $availability['plan']->id,
            'coupon_id' => $issued['coupon']->id,
        ]);
    }
}
