<?php

namespace App\Application\Actions\Payments;

use App\Application\Money\CurrencyMinorUnits;
use App\Application\Payments\FincraApi;
use App\Application\Payments\PaymentIntentLifecycle;
use App\Enums\PaymentIntentStatus;
use App\Models\PaymentIntent;
use Illuminate\Support\Facades\Http;

final class VerifyGatewayTopup
{
    public function __construct(
        private readonly ProcessGatewayWebhook $processor,
        private readonly PaymentIntentLifecycle $lifecycle,
    ) {}

    public function handle(PaymentIntent $intent): PaymentIntent
    {
        if ($intent->status === PaymentIntentStatus::Paid || $intent->provider === 'manual') {
            return $intent;
        }

        $providerConfig = config('trustlens.payment_gateways.'.$intent->provider, []);
        $baseUrl = rtrim((string) ($providerConfig['base_url'] ?? ''), '/');
        $secret = (string) ($providerConfig['secret_key'] ?? '');
        $response = match ($intent->provider) {
            'paystack' => Http::acceptJson()->withToken($secret)
                ->get($baseUrl.'/transaction/verify/'.rawurlencode((string) $intent->provider_reference)),
            'flutterwave' => Http::acceptJson()->withToken($secret)
                ->get($baseUrl.'/transactions/verify_by_reference', ['tx_ref' => $intent->provider_reference]),
            'korapay' => Http::acceptJson()->withToken($secret)
                ->get($baseUrl.'/charges/'.rawurlencode((string) $intent->provider_reference)),
            'bachs' => Http::acceptJson()->withToken($secret)
                ->get($baseUrl.'/checkout-sessions/'.rawurlencode((string) ($intent->provider_metadata['checkout_id'] ?? ''))),
            'fincra' => FincraApi::client($providerConfig)
                ->get(FincraApi::baseUrl($providerConfig).'/checkout/payments/merchant-reference/'.rawurlencode((string) $intent->provider_reference)),
            default => null,
        };

        if ($response === null) {
            return $intent;
        }

        $response->throw();
        $body = $response->json();
        $data = $intent->provider === 'bachs'
            ? (is_array($body) ? $body : [])
            : (is_array($body) && is_array($body['data'] ?? null) ? $body['data'] : []);
        $providerSuccess = match ($intent->provider) {
            'paystack' => ($body['status'] ?? false) === true && ($data['status'] ?? null) === 'success',
            'flutterwave' => ($body['status'] ?? null) === 'success' && ($data['status'] ?? null) === 'successful',
            'korapay' => ($body['status'] ?? false) === true && ($data['status'] ?? null) === 'success',
            'bachs' => ($data['payment_status'] ?? null) === 'succeeded'
                && (($data['charge']['status'] ?? 'succeeded') === 'succeeded'),
            'fincra' => ($data['status'] ?? null) === 'success',
            default => false,
        };

        if (! $providerSuccess) {
            // Bachs reports the payment state in `payment_status`; the
            // envelope `status` there is the HTTP-level result.
            $providerStatus = strtolower(trim((string) (
                $intent->provider === 'bachs'
                    ? ($data['payment_status'] ?? $data['charge']['status'] ?? $data['status'] ?? '')
                    : ($data['status'] ?? '')
            )));
            $terminal = PaymentIntentLifecycle::terminalStatusFor($providerStatus);
            if ($terminal !== null) {
                $this->lifecycle->markTerminal($intent, $terminal, ['verified_by' => 'check-payment', 'provider_status' => $providerStatus]);
            }

            return $intent->fresh() ?? $intent;
        }

        $currency = strtoupper((string) ($data['currency'] ?? ''));
        $reference = match ($intent->provider) {
            'paystack' => $data['reference'] ?? null,
            'flutterwave' => $data['tx_ref'] ?? null,
            'korapay' => $data['reference'] ?? null,
            'bachs' => $data['reference'] ?? null,
            'fincra' => $data['merchantReference'] ?? null,
            default => null,
        };
        // Fincra (like the other non-Paystack gateways) reports major units.
        // `amountReceived` is what actually landed, so an underpayment cannot
        // settle the intent for more than the customer paid.
        $providerAmount = $intent->provider === 'fincra'
            ? ($data['amountReceived'] ?? $data['amount'] ?? 0)
            : ($data['amount'] ?? 0);
        $amount = $intent->provider === 'paystack'
            ? (int) $providerAmount
            : CurrencyMinorUnits::toMinor((float) $providerAmount, $currency);

        if ($reference !== $intent->provider_reference
            || $currency !== $intent->currency
            || $amount !== $intent->amount_minor) {
            throw new \RuntimeException('Verified payment details do not match the pending top-up.');
        }

        $transactionId = (string) ($data['id'] ?? $data['charge']['payment_id'] ?? $data['flw_ref'] ?? $intent->provider_reference);
        if ($intent->purpose === 'subscription') {
            $this->processor->completeVerifiedSubscription($intent, $transactionId, $data);
        } elseif ($intent->purpose === 'package_addon') {
            $this->processor->completeVerifiedPackageAddon($intent, $transactionId, $data);
        } else {
            $this->processor->creditVerifiedTopup($intent, $transactionId, $data);
        }

        return $intent->fresh() ?? $intent;
    }
}
