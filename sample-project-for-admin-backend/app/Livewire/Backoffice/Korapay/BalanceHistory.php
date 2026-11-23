<?php

namespace App\Livewire\Backoffice\Korapay;

use App\Livewire\Backoffice\PaymentManagers\BalanceHistory as BaseBalanceHistory;
use App\Services\Payments\KorapayMerchantService;
use App\Services\Payments\MerchantWalletService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/**
 * Backoffice → Korapay → Balance history. The shared base owns the filters and cursor stack; this
 * binds Korapay's merchant service (opaque pointers + a page size the API accepts).
 */
#[Layout('components.layouts.backoffice')]
#[Title('Korapay Balance History — Backoffice')]
class BalanceHistory extends BaseBalanceHistory
{
    protected function manager(): MerchantWalletService
    {
        return app(KorapayMerchantService::class);
    }

    protected function viewName(): string
    {
        return 'livewire.backoffice.korapay.balance-history';
    }
}
