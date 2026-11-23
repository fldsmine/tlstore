<?php

namespace App\Livewire\Backoffice\Korapay;

use App\Livewire\Backoffice\PaymentManagers\Balance as BaseBalance;
use App\Models\KorapayPayout;
use App\Services\Payments\KorapayMerchantService;
use App\Services\Payments\MerchantWalletService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/** Backoffice → Korapay → Balance. Read-only view of the Korapay merchant wallet. */
#[Layout('components.layouts.backoffice')]
#[Title('Korapay Balance — Backoffice')]
class Balance extends BaseBalance
{
    protected function manager(): MerchantWalletService
    {
        return app(KorapayMerchantService::class);
    }

    protected function payoutModel(): string
    {
        return KorapayPayout::class;
    }

    protected function viewName(): string
    {
        return 'livewire.backoffice.korapay.show-balance';
    }
}
