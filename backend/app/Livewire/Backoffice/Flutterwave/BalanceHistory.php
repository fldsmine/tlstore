<?php

namespace App\Livewire\Backoffice\Flutterwave;

use App\Livewire\Backoffice\PaymentManagers\BalanceHistory as BaseBalanceHistory;
use App\Services\Payments\FlutterwaveMerchantService;
use App\Services\Payments\MerchantWalletService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/**
 * Backoffice → Flutterwave → Balance history (wallet statement). Flutterwave pages by page number
 * and has no page-size parameter, so the parent's cursor stack drives `page` and the per-page
 * selector stays hidden (supportsPageSize() = false).
 */
#[Layout('components.layouts.backoffice')]
#[Title('Flutterwave Balance History — Backoffice')]
class BalanceHistory extends BaseBalanceHistory
{
    protected function manager(): MerchantWalletService
    {
        return app(FlutterwaveMerchantService::class);
    }

    protected function supportsPageSize(): bool
    {
        return false;
    }

    protected function viewName(): string
    {
        return 'livewire.backoffice.flutterwave.balance-history';
    }
}
