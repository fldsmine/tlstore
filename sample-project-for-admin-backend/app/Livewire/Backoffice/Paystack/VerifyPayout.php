<?php

namespace App\Livewire\Backoffice\Paystack;

use App\Livewire\Backoffice\PaymentManagers\VerifyPayout as BaseVerifyPayout;
use App\Models\PaystackPayout;
use App\Services\Payments\MerchantWalletService;
use App\Services\Payments\PaystackMerchantService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/**
 * Backoffice → Paystack → Verify payout. The shared base owns the lookup, the local audit-row
 * reconciliation and the wording; this binds Paystack's merchant service, audit model and view.
 */
#[Layout('components.layouts.backoffice')]
#[Title('Paystack Verify Payout — Backoffice')]
class VerifyPayout extends BaseVerifyPayout
{
    protected function manager(): MerchantWalletService
    {
        return app(PaystackMerchantService::class);
    }

    protected function payoutModel(): string
    {
        return PaystackPayout::class;
    }

    protected function label(): string
    {
        return 'Paystack';
    }

    protected function verifyRouteName(): string
    {
        return 'backoffice.paystack.verify';
    }

    protected function identifierLabel(): string
    {
        return 'Transfer code';
    }

    protected function referencePlaceholder(): string
    {
        return 'PSK-… or a Paystack TRF_… code';
    }

    protected function referenceHint(): string
    {
        return 'Paste any payout reference or Paystack transfer code.';
    }

    protected function viewName(): string
    {
        return 'livewire.backoffice.paystack.verify-payout';
    }
}
