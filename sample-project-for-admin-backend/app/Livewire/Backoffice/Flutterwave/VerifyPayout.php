<?php

namespace App\Livewire\Backoffice\Flutterwave;

use App\Livewire\Backoffice\PaymentManagers\VerifyPayout as BaseVerifyPayout;
use App\Models\FlutterwavePayout;
use App\Services\Payments\FlutterwaveMerchantService;
use App\Services\Payments\MerchantWalletService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/**
 * Backoffice → Flutterwave → Verify payout. The shared base owns the lookup and the local audit-row
 * reconciliation; this binds Flutterwave's merchant service, audit model and view, and labels
 * Flutterwave's own handle (the numeric transfer id) as the "Transfer ID".
 */
#[Layout('components.layouts.backoffice')]
#[Title('Flutterwave Verify Payout — Backoffice')]
class VerifyPayout extends BaseVerifyPayout
{
    protected function manager(): MerchantWalletService
    {
        return app(FlutterwaveMerchantService::class);
    }

    protected function payoutModel(): string
    {
        return FlutterwavePayout::class;
    }

    protected function label(): string
    {
        return 'Flutterwave';
    }

    protected function verifyRouteName(): string
    {
        return 'backoffice.flutterwave.verify';
    }

    protected function identifierLabel(): string
    {
        return 'Transfer ID';
    }

    protected function referencePlaceholder(): string
    {
        return 'FLW-… or a Flutterwave transfer id';
    }

    protected function referenceHint(): string
    {
        return 'Paste any payout reference or transfer id.';
    }

    protected function viewName(): string
    {
        return 'livewire.backoffice.flutterwave.verify-payout';
    }
}
