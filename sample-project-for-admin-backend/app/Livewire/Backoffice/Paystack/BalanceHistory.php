<?php

namespace App\Livewire\Backoffice\Paystack;

use App\Livewire\Backoffice\PaymentManagers\BalanceHistory as BaseBalanceHistory;
use App\Services\Payments\MerchantWalletService;
use App\Services\Payments\PaystackMerchantService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/**
 * Backoffice → Paystack → Balance history. The shared base owns the filters and cursor stack; this
 * binds Paystack's merchant service (page-number paging + a page size the API accepts).
 */
#[Layout('components.layouts.backoffice')]
#[Title('Paystack Balance History — Backoffice')]
class BalanceHistory extends BaseBalanceHistory
{
    protected function manager(): MerchantWalletService
    {
        return app(PaystackMerchantService::class);
    }

    /** The ledger endpoint takes perPage/page, so a page size can be chosen. */
    protected function supportsPageSize(): bool
    {
        return true;
    }

    protected function viewName(): string
    {
        return 'livewire.backoffice.paystack.balance-history';
    }
}
