<?php

namespace App\Livewire\Backoffice\Korapay;

use App\Livewire\Backoffice\PaymentManagers\VerifyPayout as BaseVerifyPayout;
use App\Models\KorapayPayout;
use App\Services\Payments\KorapayMerchantService;
use App\Services\Payments\MerchantWalletService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/**
 * Backoffice → Korapay → Verify payout. The shared base owns the lookup, the local audit-row
 * reconciliation and the wording; this binds Korapay's merchant service, audit model and view.
 */
#[Layout('components.layouts.backoffice')]
#[Title('Korapay Verify Payout — Backoffice')]
class VerifyPayout extends BaseVerifyPayout
{
    protected function manager(): MerchantWalletService
    {
        return app(KorapayMerchantService::class);
    }

    protected function payoutModel(): string
    {
        return KorapayPayout::class;
    }

    protected function label(): string
    {
        return 'Korapay';
    }

    protected function verifyRouteName(): string
    {
        return 'backoffice.korapay.verify';
    }

    protected function identifierLabel(): string
    {
        return 'Trace ID';
    }

    protected function referencePlaceholder(): string
    {
        return 'KPO-… or a Korapay reference';
    }

    protected function referenceHint(): string
    {
        return 'Paste any payout reference.';
    }

    protected function viewName(): string
    {
        return 'livewire.backoffice.korapay.verify-payout';
    }
}
