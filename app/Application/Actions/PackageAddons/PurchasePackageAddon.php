<?php

namespace App\Application\Actions\PackageAddons;

use App\Models\LedgerEntry;
use App\Models\PackageAddon;
use App\Models\PaymentIntent;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserPackageAddon;
use App\Models\Wallet;
use App\Application\Money\CurrencyMinorUnits;
use App\Application\Support\PackageAddonPriceQuote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PurchasePackageAddon
{
    public function __construct(
        private readonly ActivatePackageAddon $activate,
        private readonly PackageAddonPriceQuote $priceQuote,
    ) {}

    public function withFunds(User $user, PackageAddon $addon, string $idempotencyKey): UserPackageAddon
    {
        return DB::transaction(function () use ($user, $addon, $idempotencyKey): UserPackageAddon {
            $addon = PackageAddon::query()->with('plan')->where('is_active', true)->lockForUpdate()->findOrFail($addon->id);
            $this->assertEligible($user, $addon);
            $intentKey = 'addon-wallet:'.$user->id.':'.hash('sha256', $idempotencyKey);
            $existing = PaymentIntent::query()->where('idempotency_key', $intentKey)->first();
            if ($existing) return UserPackageAddon::query()->where('payment_intent_id', $existing->id)->firstOrFail();

            $wallets = Wallet::query()->where('user_id', $user->id)->where('wallet_type', 'topup_fund')
                ->where('status', 'active')
                ->orderByRaw('CASE WHEN currency = ? THEN 0 ELSE 1 END', [strtoupper($addon->currency)])
                ->lockForUpdate()->get();
            $wallet = null;
            $payableMinor = null;
            $exchangeRate = null;
            foreach ($wallets as $candidate) {
                try {
                    $converted = $this->priceQuote->quote($addon, $candidate->currency);
                } catch (ValidationException) {
                    continue;
                }
                if ((int) $candidate->balance_minor >= $converted['amount_minor']) {
                    $wallet = $candidate;
                    $payableMinor = $converted['amount_minor'];
                    $exchangeRate = $converted['rate'];
                    break;
                }
            }
            if (! $wallet || $payableMinor === null) {
                throw ValidationException::withMessages(['wallet' => 'No top-up wallet has enough funds for this add-on after currency conversion. Add funds or use Secure payment.']);
            }
            $intent = PaymentIntent::query()->create([
                'user_id' => $user->id, 'purpose' => 'package_addon', 'source_type' => 'package_addon',
                'source_id' => $addon->id, 'amount_minor' => $payableMinor, 'currency' => $wallet->currency,
                'provider' => 'wallet', 'idempotency_key' => $intentKey, 'status' => 'paid', 'paid_at' => now(),
            ]);
            LedgerEntry::query()->create([
                'wallet_id' => $wallet->id, 'entry_type' => 'package_addon_purchase', 'direction' => 'debit',
                'amount_minor' => $payableMinor, 'status' => 'posted', 'source_type' => 'payment_intent',
                'source_id' => $intent->id, 'idempotency_key' => 'package-addon-charge:'.$intent->id,
                'description' => 'Package add-on: '.$addon->name.' ('.$addon->currency.' price, paid from '.$wallet->currency.' funds)',
                'metadata' => [
                    'addon_price_minor' => (int) round($addon->price_minor * (100 - min(max((float) $addon->discount, 0), 100)) / 100),
                    'addon_currency' => strtoupper($addon->currency),
                    'wallet_debit_minor' => $payableMinor,
                    'wallet_currency' => strtoupper($wallet->currency),
                    'exchange_rate' => $exchangeRate,
                    'discount_percent' => (float) $addon->discount,
                ],
                'available_at' => now(), 'posted_at' => now(),
            ]);
            Transaction::query()->updateOrCreate(['reference' => 'payment-intent:'.$intent->id], [
                'user_id' => $user->id, 'type' => 'package_addon', 'direction' => 'debit',
                'amount' => CurrencyMinorUnits::fromMinor($payableMinor, $wallet->currency), 'fee' => 0,
                'wallet' => 'topup_fund', 'status' => 'completed', 'description' => 'Package add-on: '.$addon->name,
                'meta' => ['payment_intent_id' => $intent->id, 'addon_id' => $addon->id, 'currency' => $wallet->currency, 'amount_minor' => $payableMinor, 'listed_amount_minor' => $addon->price_minor, 'listed_currency' => $addon->currency, 'discount_percent' => (float) $addon->discount],
            ]);

            return $this->activate->handle($intent);
        });
    }

    public function assertEligible(User $user, PackageAddon $addon): void
    {
        if (! $addon->plan?->addons || ! $user->subscriptions()->where('subscription_plan_id', $addon->subscription_plan_id)
            ->where('product_type', 'marketplace_access')->where('status', 'active')
            ->where('starts_at', '<=', now())->where('ends_at', '>', now())->exists()) {
            throw ValidationException::withMessages(['package_addon' => 'An active matching review package is required to purchase this add-on.']);
        }
    }
}
