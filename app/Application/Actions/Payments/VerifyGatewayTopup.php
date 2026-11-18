<?php

namespace App\Application\Actions\Payments;

use App\Application\Money\CurrencyMinorUnits;
use App\Enums\PaymentIntentStatus;
use App\Models\PaymentIntent;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;

final class VerifyGatewayTopup
{
    public function __construct(private readonly ProcessGatewayWebhook $processor) {}

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
            default => false,
        };

        if (! $providerSuccess) {
            $providerStatus = strtolower((string) ($intent->provider === 'bachs' ? ($data['status'] ?? '') : ($data['status'] ?? '')));
            if (in_array($providerStatus, ['failed', 'failure', 'cancelled', 'canceled', 'abandoned'], true)
                && $intent->status === PaymentIntentStatus::Pending) {
                $intent->forceFill(['status' => 'failed'])->save();
                Transaction::query()->where('reference', 'payment-intent:'.$intent->id)
                    ->update(['status' => 'failed', 'updated_at' => now()]);
            }

            return $intent->fresh() ?? $intent;
        }

        $currency = strtoupper((string) ($data['currency'] ?? ''));
        $reference = match ($intent->provider) {
            'paystack' => $data['reference'] ?? null,
            'flutterwave' => $data['tx_ref'] ?? null,
            'korapay' => $data['reference'] ?? null,
            'bachs' => $data['reference'] ?? null,
            default => null,
        };
        $amount = $intent->provider === 'paystack'
            ? (int) ($data['amount'] ?? 0)
            : CurrencyMinorUnits::toMinor((float) ($data['amount'] ?? 0), $currency);

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
