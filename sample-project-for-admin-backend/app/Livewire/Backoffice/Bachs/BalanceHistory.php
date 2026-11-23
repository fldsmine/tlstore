<?php

namespace App\Livewire\Backoffice\Bachs;

use App\Livewire\Backoffice\PaymentManagers\BalanceHistory as BaseBalanceHistory;
use App\Services\Payments\BachsMerchantService;
use App\Services\Payments\MerchantWalletService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/**
 * Backoffice → Bachs → Balance history. The shared base owns the filters and cursor stack; this binds
 * Bachs' merchant service. Bachs pages with limit/offset, so a page size can be chosen.
 */
#[Layout('components.layouts.backoffice')]
#[Title('Bachs Balance History — Backoffice')]
class BalanceHistory extends BaseBalanceHistory
{
    protected function manager(): MerchantWalletService
    {
        return app(BachsMerchantService::class);
    }

    /** `GET /v1/payments` and `/v1/payouts` both take limit/offset, so a page size can be chosen. */
    protected function supportsPageSize(): bool
    {
        return true;
    }

    protected function viewName(): string
    {
        return 'livewire.backoffice.bachs.balance-history';
    }
}
