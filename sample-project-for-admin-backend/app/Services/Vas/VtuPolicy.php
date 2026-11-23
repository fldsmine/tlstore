<?php

namespace App\Services\Vas;

use App\Enums\VtuStatus;
use App\Models\User;

class VtuPolicy
{
    public function enabled(): bool
    {
        return (bool) config('settings.vtu.enabled', false);
    }

    public function walletConfig(string $wallet): array
    {
        $config = (array) config("settings.vtu.wallets.{$wallet}", []);

        return [
            'enabled' => (bool) ($config['enabled'] ?? false),
            'min_ngn' => (float) ($config['min_ngn'] ?? 0),
            'max_ngn' => (float) ($config['max_ngn'] ?? 0),
            'max_purchases' => (int) ($config['max_purchases'] ?? 0),
        ];
    }

    public function isWalletUsable(string $wallet): bool
    {
        return $this->enabled() && $this->walletConfig($wallet)['enabled'];
    }

    public function minNgn(string $wallet): float
    {
        return $this->walletConfig($wallet)['min_ngn'];
    }

    public function maxNgn(string $wallet): float
    {
        return $this->walletConfig($wallet)['max_ngn'];
    }

    public function committedPurchases(User $user, string $wallet): int
    {
        return $user->vtuOrders()
            ->where('wallet', $wallet)
            ->whereIn('status', [VtuStatus::DELIVERED, VtuStatus::PENDING])
            ->count();
    }

    public function remainingPurchases(User $user, string $wallet): ?int
    {
        $max = $this->walletConfig($wallet)['max_purchases'];

        if ($max <= 0) {
            return null;
        }

        return max(0, $max - $this->committedPurchases($user, $wallet));
    }

    public function check(User $user, string $wallet, float $ngn): array
    {
        if (! $this->enabled()) {
            return ['allowed' => false, 'reason' => 'Bill payments are temporarily unavailable.'];
        }

        if (! $this->walletConfig($wallet)['enabled']) {
            return ['allowed' => false, 'reason' => 'This wallet cannot be used for bill payments.'];
        }

        if ($ngn <= 0) {
            return ['allowed' => false, 'reason' => 'Enter a valid amount.'];
        }

        $min = $this->minNgn($wallet);
        if ($min > 0 && $ngn < $min) {
            return ['allowed' => false, 'reason' => 'The minimum you can spend from this wallet is ₦'.number_format($min, 0).'.'];
        }

        $max = $this->maxNgn($wallet);
        if ($max > 0 && $ngn > $max) {
            return ['allowed' => false, 'reason' => 'The maximum you can spend from this wallet is ₦'.number_format($max, 0).'.'];
        }

        $remaining = $this->remainingPurchases($user, $wallet);
        if ($remaining !== null && $remaining < 1) {
            return ['allowed' => false, 'reason' => 'You have reached the bill-payment limit for this wallet.'];
        }

        return ['allowed' => true, 'reason' => null];
    }
}
