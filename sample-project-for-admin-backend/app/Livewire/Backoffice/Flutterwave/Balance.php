<?php

namespace App\Livewire\Backoffice\Flutterwave;

use App\Livewire\Backoffice\PaymentManagers\Balance as BaseBalance;
use App\Models\FlutterwavePayout;
use App\Services\Payments\FlutterwaveMerchantService;
use App\Services\Payments\MerchantWalletService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/** Backoffice → Flutterwave → Balance. Read-only view of the Flutterwave merchant wallet. */
#[Layout('components.layouts.backoffice')]
#[Title('Flutterwave Balance — Backoffice')]
class Balance extends BaseBalance
{
    protected function manager(): MerchantWalletService
    {
        return app(FlutterwaveMerchantService::class);
    }

    protected function payoutModel(): string
    {
        return FlutterwavePayout::class;
    }

    protected function viewName(): string
    {
        return 'livewire.backoffice.flutterwave.show-balance';
    }
}
