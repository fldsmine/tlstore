<?php

namespace App\Livewire\Backoffice\Paystack;

use App\Livewire\Backoffice\PaymentManagers\SendMoney as BaseSendMoney;
use App\Models\PaystackPayout;
use App\Services\Payments\DTO\PayoutResult;
use App\Services\Payments\MerchantWalletService;
use App\Services\Payments\PaystackMerchantService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/**
 * Backoffice → Paystack → Send money. All flow/guards live in the shared base; this only binds the
 * component to Paystack's audit model, merchant service and view.
 *
 * Paystack pays a transfer recipient, which the merchant service creates (or matches) from the bank
 * account on this form, so the form itself is the same as the other managers'. There is no debit
 * wallet to choose — transfers always come out of the single `balance`.
 */
#[Layout('components.layouts.backoffice')]
#[Title('Paystack Send Money — Backoffice')]
class SendMoney extends BaseSendMoney
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
        return 'livewire.backoffice.paystack.send-money';
    }

    /**
     * Paystack's two provider-specific audit fields: the transfer recipient the account resolved to
     * (RCP_…) and Paystack's own sentence about the transfer.
     *
     * @return array<string, mixed>
     */
    protected function auditExtras(PayoutResult $result): array
    {
        return [
            'recipient_code' => $result->data['recipient'] ?? null,
            'message' => $result->message,
        ];
    }
}
