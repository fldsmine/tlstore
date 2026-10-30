<?php

namespace App\Livewire\Backoffice\Bachs;

use App\Livewire\Backoffice\PaymentManagers\VerifyPayout as BaseVerifyPayout;
use App\Models\BachsPayout;
use App\Services\Payments\BachsMerchantService;
use App\Services\Payments\MerchantWalletService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/**
 * Backoffice → Bachs → Verify payout. The shared base owns the lookup, the local audit-row
 * reconciliation and the wording; this binds Bachs' merchant service, audit model and view.
 */
#[Layout('components.layouts.backoffice')]
#[Title('Bachs Verify Payout — Backoffice')]
class VerifyPayout extends BaseVerifyPayout
{
    protected function manager(): MerchantWalletService
    {
        return app(BachsMerchantService::class);
    }

    protected function payoutModel(): string
    {
        return BachsPayout::class;
    }

    protected function label(): string
    {
        return 'Bachs';
    }

    protected function verifyRouteName(): string
    {
        return 'backoffice.bachs.verify';
    }

    protected function identifierLabel(): string
    {
        return 'Payout ID';
    }

    protected function referencePlaceholder(): string
    {
        return 'BCH-… or a Bachs pay_… id';
    }

    protected function referenceHint(): string
    {
        return 'Paste any payout reference or Bachs payout id.';
    }

    protected function viewName(): string
    {
        return 'livewire.backoffice.bachs.verify-payout';
    }
}
