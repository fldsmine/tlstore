<?php

namespace App\Services\Payments\Gateways;

use App\Contracts\PaymentGateway;
use App\Services\Payments\DTO\ChargeRequest;
use App\Services\Payments\DTO\InitiateResult;
use App\Services\Payments\DTO\VerifyResult;
use App\Services\Payments\DTO\WebhookEvent;
use Illuminate\Http\Request;

/**
 * Manual bank transfer: the buyer pays into the configured bank account and chats support with proof.
 * There is no hosted checkout — initiate() just opens the manual payment page; verify() never
 * auto-completes (support marks the charge paid by hand).
 */
class ManualBankGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'manual';
    }

    public function configured(): bool
    {
        return trim((string) config('settings.payment.manual_bank_account_number', '')) !== ''
            && trim((string) config('settings.payment.manual_bank_name', '')) !== ''
            && trim((string) config('settings.payment.manual_bank_account_name', '')) !== '';
    }

    public function chargeCurrency(): string
    {
        return strtoupper((string) config('product.currency', 'NGN'));
    }

    public function initiate(ChargeRequest $request): InitiateResult
    {
        if (! $this->configured()) {
            return InitiateResult::failure('Manual bank transfer is not configured right now.');
        }

        $url = route('manual-payment.show', ['reference' => $request->reference]);

        return InitiateResult::success($url, $request->reference);
    }

    public function verify(string $reference): VerifyResult
    {
        // Manual transfers are never auto-verified — support confirms them by hand.
        return new VerifyResult(true, VerifyResult::STATUS_PENDING, 0.0, $this->chargeCurrency(), $reference);
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        return false;
    }

    public function parseWebhook(Request $request): ?WebhookEvent
    {
        return null;
    }
}
