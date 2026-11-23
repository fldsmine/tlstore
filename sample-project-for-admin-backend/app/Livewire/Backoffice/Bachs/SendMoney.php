<?php

namespace App\Livewire\Backoffice\Bachs;

use App\Livewire\Backoffice\PaymentManagers\SendMoney as BaseSendMoney;
use App\Models\BachsPayout;
use App\Services\Payments\BachsMerchantService;
use App\Services\Payments\DTO\PayoutResult;
use App\Services\Payments\MerchantWalletService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/**
 * Backoffice → Bachs → Send money. All flow/guards live in the shared base; this only binds the
 * component to Bachs' audit model, merchant service and view.
 *
 * Bachs pays a registered payout DESTINATION, which the merchant service matches (or creates) from the
 * bank account on this form, so the form itself is the same as the other managers'. A same-currency
 * payout is funded from that currency's own balance.
 */
#[Layout('components.layouts.backoffice')]
#[Title('Bachs Send Money — Backoffice')]
class SendMoney extends BaseSendMoney
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
        return 'livewire.backoffice.bachs.send-money';
    }

    /**
     * Bachs' provider-specific audit fields: the payout destination the account resolved to (pd_…)
     * and Bachs' own sentence about the payout.
     *
     * @return array<string, mixed>
     */
    protected function auditExtras(PayoutResult $result): array
    {
        return [
            'destination_id' => $result->data['destination'] ?? null,
            'message' => $result->message,
        ];
    }
}
