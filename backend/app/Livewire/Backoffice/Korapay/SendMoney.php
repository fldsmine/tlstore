<?php

namespace App\Livewire\Backoffice\Korapay;

use App\Livewire\Backoffice\PaymentManagers\SendMoney as BaseSendMoney;
use App\Models\KorapayPayout;
use App\Services\Payments\KorapayMerchantService;
use App\Services\Payments\MerchantWalletService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/**
 * Backoffice → Korapay → Send money. All flow/guards live in the shared base; this only binds the
 * component to Korapay's audit model, merchant service and view.
 */
#[Layout('components.layouts.backoffice')]
#[Title('Korapay Send Money — Backoffice')]
class SendMoney extends BaseSendMoney
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
        return 'livewire.backoffice.korapay.send-money';
    }
}
