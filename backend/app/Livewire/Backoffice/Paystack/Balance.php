<?php

namespace App\Livewire\Backoffice\Paystack;

use App\Livewire\Backoffice\PaymentManagers\Balance as BaseBalance;
use App\Models\PaystackPayout;
use App\Services\Payments\MerchantWalletService;
use App\Services\Payments\PaystackMerchantService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/** Backoffice → Paystack → Balance. Read-only view of the Paystack balance. */
#[Layout('components.layouts.backoffice')]
#[Title('Paystack Balance — Backoffice')]
class Balance extends BaseBalance
{
    protected function manager(): MerchantWalletService
    {
        return app(PaystackMerchantService::class);
    }

    protected function payoutModel(): string
    {
        return PaystackPayout::class;
    }

    protected function viewName(): string
    {
        return 'livewire.backoffice.paystack.show-balance';
    }
}
