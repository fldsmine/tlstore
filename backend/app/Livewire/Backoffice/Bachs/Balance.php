<?php

namespace App\Livewire\Backoffice\Bachs;

use App\Livewire\Backoffice\PaymentManagers\Balance as BaseBalance;
use App\Models\BachsPayout;
use App\Services\Payments\BachsMerchantService;
use App\Services\Payments\MerchantWalletService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/** Backoffice → Bachs → Balance. Read-only view of the Bachs balances (available + pending). */
#[Layout('components.layouts.backoffice')]
#[Title('Bachs Balance — Backoffice')]
class Balance extends BaseBalance
{
    protected function manager(): MerchantWalletService
    {
        return app(BachsMerchantService::class);
    }

    protected function payoutModel(): string
    {
        return BachsPayout::class;
    }

    protected function viewName(): string
    {
        return 'livewire.backoffice.bachs.show-balance';
    }
}
