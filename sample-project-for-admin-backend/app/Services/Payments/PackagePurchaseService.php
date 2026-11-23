<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\ChargePurpose;
use App\Enums\TransactionType;
use App\Models\Package;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\PackageActivationService;
use App\Services\ShareSubscriptionService;
use App\Services\Payments\Concerns\SettlesGatewayCharge;
use App\Services\Payments\PaymentReferenceLedger;
use App\Services\Payments\DTO\ChargeRequest;
use App\Support\RegionPricing;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Direct purchase of an eSIM package (activation/upgrade), or renewal of its
 * term-limited share entitlement, through the currently routed package payment channel.
 * Share renewals do not re-activate eSIM features.
 */
class PackagePurchaseService
{
    use SettlesGatewayCharge;

    public const MODE_ACTIVATE = 'activate';

    public const MODE_UPGRADE = 'upgrade';

    public const MODE_RENEW = 'renew';

    public function __construct(
        private PaymentGatewayManager $gateways,
        private CurrencyService $currency,
        private PackageActivationService $activation,
        private ShareSubscriptionService $shareSubscriptions,
        private PaymentReferenceLedger $ledger,
    ) {}

    /**
     * @return array{ok: bool, error?: string, checkout_url?: string, reference?: string}
     */
    public function start(User $user, Package $package, string $mode): array
    {
        try {
            $gateway = $this->gateways->for(ChargePurpose::PACKAGE);
        } catch (InvalidArgumentException) {
            return ['ok' => false, 'error' => 'The configured package payment channel is not available right now.'];
        }

        if (! $gateway->configured()) {
            return ['ok' => false, 'error' => 'Direct package payment is not available right now.'];
        }

        if (! in_array($mode, [self::MODE_ACTIVATE, self::MODE_UPGRADE, self::MODE_RENEW], true)) {
            return ['ok' => false, 'error' => 'Choose whether you are activating, upgrading, or renewing.'];
        }

        if (! $package->is_active) {
            return ['ok' => false, 'error' => 'That package is no longer available. Please choose another.'];
        }

        $user->loadMissing('package');

        if ($mode === self::MODE_ACTIVATE && $user->package_id !== null) {
            return ['ok' => false, 'error' => 'This account is already activated — choose a higher package to upgrade.'];
        }

        if ($mode === self::MODE_UPGRADE) {
            if ($user->package === null) {
                return ['ok' => false, 'error' => 'Activate a package before upgrading.'];
            }

            if ((int) $package->tier_level <= (int) $user->package->tier_level) {
                return ['ok' => false, 'error' => 'Pick a package higher than your current '.$user->package->name.' plan.'];
            }
        }

        if ($mode === self::MODE_RENEW && ! $this->shareSubscriptions->canRenew($user, $package)) {
            return ['ok' => false, 'error' => 'Your current share plan must expire before it can be renewed, and the package must have a configured quota and cap.'];
        }

        $priceUsd = $this->priceFor($user, $package, $mode);

        if ($priceUsd <= 0) {
            return ['ok' => false, 'error' => 'That package is not on sale right now.'];
        }

        // Serialize concurrent submits per user so a double-click can't mint two payable charges.
        try {
            return Cache::lock("charge-start:package:{$user->id}", 20)
                ->block(8, fn (): array => $this->createOrReuse($user, $package, $mode, $priceUsd, $gateway));
        } catch (LockTimeoutException) {
            return ['ok' => false, 'error' => 'A payment is already being set up — please wait a moment and try again.'];
        }
    }

    private function priceFor(User $user, Package $package, string $mode): float
    {
        $region = RegionPricing::forUser($user);

        return $mode === self::MODE_UPGRADE
            ? RegionPricing::upgradeCustomer($package, $region)
            : RegionPricing::customer($package, $region);
    }

    /**
     * @return array{ok: bool, error?: string, checkout_url?: string, reference?: string}
     */
    private function createOrReuse(User $user, Package $package, string $mode, float $priceUsd, PaymentGateway $gateway): array
    {
        $currency = $gateway->chargeCurrency();
        $amountNgn = round($this->currency->toNgn($priceUsd), 2);

        if ($amountNgn <= 0) {
            return ['ok' => false, 'error' => 'That package is not on sale right now.'];
        }

        // Reuse a still-open checkout for the same package + mode + provider so a double-click
        // doesn't mint a second payable charge.
        $existing = $this->openCheckoutFor($user, $package, $mode, $amountNgn, $gateway->name());
        if ($existing !== null) {
            return ['ok' => true, 'checkout_url' => $existing['url'], 'reference' => $existing['reference']];
        }

        $reference = 'PKG-'.strtoupper(Str::random(16));

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => TransactionType::PACKAGE_PURCHASE,
            'direction' => 'debit',
            'amount' => $priceUsd,
            'wallet' => null,
            'status' => 'pending',
            'reference' => $reference,
            'provider' => $gateway->name(),
            'description' => $mode === self::MODE_RENEW
                ? 'Share plan renewal — '.$package->name
                : 'eSIM package — '.$package->name,
            'meta' => [
                'gateway' => $gateway->name(),
                'package_id' => $package->id,
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
            narration: ($mode === self::MODE_RENEW ? 'Nextel shares — ' : 'Nextel eSIM — ').$package->name,
            callbackUrl: route('plans.buy.callback', ['reference' => $reference]),
            notificationUrl: route('webhooks.payments', ['provider' => $gateway->name()]),
            metadata: ['reference' => $reference, 'user_id' => $user->id, 'kind' => 'package_purchase'],
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
    private function openCheckoutFor(User $user, Package $package, string $mode, float $amountNgn, string $provider): ?array
    {
        $match = Transaction::where('user_id', $user->id)
            ->where('type', TransactionType::PACKAGE_PURCHASE->value)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(15))
            ->latest()
            ->get()
            ->first(fn (Transaction $t): bool => filled($t->meta['checkout_url'] ?? null)
                && (string) ($t->meta['gateway'] ?? '') === $provider
                && (string) ($t->meta['package_id'] ?? '') === $package->id
                && (string) ($t->meta['mode'] ?? '') === $mode
                && (float) ($t->meta['amount_ngn'] ?? 0) === $amountNgn);

        return $match === null ? null : ['url' => (string) $match->meta['checkout_url'], 'reference' => (string) $match->reference];
    }

    protected function transactionType(): TransactionType
    {
        return TransactionType::PACKAGE_PURCHASE;
    }

    /**
     * Apply the entitlement — or refuse loudly. The account state MUST be re-checked at settle time:
     * the user could have redeemed a token while this charge was open, and paying twice must never
     * activate twice. PackageActivationService throws in that case; the settlement trait then fails
     * the charge and flags it for manual reconciliation/refund instead of crediting.
     */
    protected function applyCompletion(Transaction $transaction): void
    {
        $package = Package::query()->find($transaction->meta['package_id'] ?? null);

        if ($package === null || ! $package->is_active) {
            throw new RuntimeException('Purchased package is no longer available');
        }

        $user = User::whereKey($transaction->user_id)->lockForUpdate()->first();

        if ($user === null) {
            throw new RuntimeException('Package purchase has no resolvable user');
        }

        $mode = (string) ($transaction->meta['mode'] ?? self::MODE_ACTIVATE);

        if ($mode === self::MODE_RENEW) {
            if (! $this->shareSubscriptions->canRenew($user, $package)) {
                throw new RuntimeException('Share plan renewal is no longer eligible for this account');
            }

            $this->activation->renewShares($user, $package);
        } elseif ($mode === self::MODE_UPGRADE) {
            // The tier guard too: activating/upgrading to a package at or below the one now held
            // would silently downgrade a user who moved up while the charge was pending.
            $user->loadMissing('package');

            if ($user->package_id === null) {
                throw new RuntimeException('Package upgrade purchase on an account that is no longer activated');
            }

            if ((int) $package->tier_level <= (int) $user->package->tier_level) {
                throw new RuntimeException('Package upgrade purchase is no longer higher than the package now held');
            }

            $this->activation->upgrade($user, $package);
        } else {
            // Throws when the account is already activated — the guard that must hold here.
            $this->activation->activate($user, $package);
        }

        Log::info($mode === self::MODE_RENEW ? 'share plan renewed by direct purchase' : 'eSIM package granted by direct purchase', [
            'reference' => $transaction->reference,
            'user_id' => $user->id,
            'package_id' => $package->id,
            'mode' => $mode,
        ]);
    }
}
