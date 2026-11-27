<?php

namespace App\Application\Actions\Payments;

use App\Application\Actions\Subscriptions\ActivateSubscription;
use App\Application\Payments\PaymentIntentLifecycle;
use App\Enums\PaymentIntentStatus;
use App\Application\Actions\PackageAddons\ActivatePackageAddon;
use App\Application\Money\CurrencyMinorUnits;
use App\Models\LedgerEntry;
use App\Models\PaymentIntent;
use App\Models\Notification;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhookEvent;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ProcessGatewayWebhook
{
    public function __construct(private readonly PaymentIntentLifecycle $lifecycle) {}

    public function handle(string $provider, Request $request): void
    {
        $payload = $request->all();
        $data = $payload['data'] ?? [];
        if (! is_array($data)) {
            throw new HttpException(400, 'Invalid payment notification.');
        }
        $eventType = (string) ($payload['event'] ?? $payload['event.type'] ?? 'unknown');
        $eventId = (string) ($payload['id'] ?? $data['id'] ?? $data['reference'] ?? hash('sha256', $request->getContent()));
        $event = PaymentWebhookEvent::query()->firstOrCreate(
            ['provider' => $provider, 'provider_event_id' => $eventId],
            ['event_type' => $eventType, 'payload' => $payload, 'received_at' => now(), 'processing_status' => 'received'],
        );
        if ($event->processing_status === 'processed') {
            return;
        }

        if (! $this->validSignature($provider, $request, $data)) {
            $event->forceFill(['signature_valid' => false, 'processing_status' => 'rejected', 'processing_error' => 'Signature verification failed.', 'processed_at' => now()])->save();
            throw new HttpException(401, 'Invalid payment notification signature.');
        }
        $event->forceFill(['signature_valid' => true])->save();

        if ($provider === 'bachs' && ! is_string($data['checkout_id'] ?? null)) {
            $event->forceFill(['processing_status' => 'ignored', 'processed_at' => now()])->save();
            return;
        }

        try {
            $verified = $this->verifyWithProvider($provider, $data);
            $reference = match ($provider) {
                'paystack' => $verified['reference'] ?? null,
                'flutterwave' => $verified['tx_ref'] ?? null,
                'korapay' => $data['payment_reference'] ?? $data['reference'] ?? null,
                'bachs' => $verified['reference'] ?? $data['reference'] ?? null,
            };
            $intentQuery = PaymentIntent::query()->where('provider', $provider);
            $intent = $reference ? (clone $intentQuery)->where('provider_reference', $reference)->first() : null;
            if (! $intent && $provider === 'bachs' && is_string($data['checkout_id'] ?? null)) {
                $intent = $intentQuery->where('provider_metadata->checkout_id', $data['checkout_id'])->first();
            }
            if (! $intent) {
                throw new \RuntimeException('No matching payment intent was found.');
            }
            $status = match ($provider) {
                'paystack' => ($verified['status'] ?? null) === 'success',
                'flutterwave' => ($verified['status'] ?? null) === 'successful',
                'korapay' => ($verified['status'] ?? null) === 'success' && ($data['transaction_status'] ?? 'success') === 'success',
                'bachs' => ($verified['payment_status'] ?? null) === 'succeeded'
                    && (($verified['charge']['status'] ?? 'succeeded') === 'succeeded'),
            };
            if (! $status) {
                // A provider that reports failure/cancellation is a terminal
                // event, not a no-op: close the intent *and* its projection so
                // the user never sees a phantom pending top-up.
                $providerStatus = $this->providerStatus($provider, $verified);
                $terminal = PaymentIntentLifecycle::terminalStatusFor($providerStatus);

                if ($terminal !== null) {
                    $this->lifecycle->markTerminal($intent, $terminal, [
                        'webhook_event_id' => $event->id,
                        'provider_status' => $providerStatus,
                    ]);
                    $event->forceFill(['processing_status' => 'processed', 'processed_at' => now(), 'processing_error' => null])->save();
                    return;
                }

                $event->forceFill(['processing_status' => 'ignored', 'processing_error' => 'Provider reports a non-final status: '.$providerStatus, 'processed_at' => now()])->save();
                return;
            }

            $currency = strtoupper((string) ($verified['currency'] ?? $data['currency'] ?? ''));
            $amount = match ($provider) {
                'paystack' => (int) ($verified['amount'] ?? 0),
                'flutterwave', 'korapay', 'bachs' => CurrencyMinorUnits::toMinor((float) ($verified['amount'] ?? $data['amount'] ?? 0), $currency),
            };
            if ($currency !== $intent->currency || $amount !== $intent->amount_minor) {
                throw new \RuntimeException('Verified payment amount or currency does not match the top-up.');
            }
            $transactionId = (string) ($verified['id'] ?? $verified['charge']['payment_id'] ?? $data['charge_id'] ?? $verified['flw_ref'] ?? $data['reference'] ?? $intent->provider_reference);
            if ($intent->purpose === 'subscription') {
                $this->completeSubscription($intent, $transactionId, $verified);
            } elseif ($intent->purpose === 'package_addon') {
                $this->completePackageAddon($intent, $transactionId, $verified);
            } else {
                $this->creditTopup($intent, $transactionId, $verified);
            }
            $event->forceFill(['processing_status' => 'processed', 'processed_at' => now(), 'processing_error' => null])->save();
        } catch (\Throwable $exception) {
            $event->forceFill(['processing_status' => 'failed', 'processing_error' => mb_substr($exception->getMessage(), 0, 4000), 'processed_at' => now()])->save();
            report($exception);
            throw new HttpException(500, 'Payment notification could not be processed.');
        }
    }

    /**
     * The provider's own status string, normalised to lower case.
     *
     * Bachs reports `payment_status` (and sometimes only `charge.status`);
     * the other gateways use `status` on the verified payload.
     */
    private function providerStatus(string $provider, array $verified): string
    {
        $status = match ($provider) {
            'bachs' => $verified['payment_status']
                ?? $verified['charge']['status']
                ?? $verified['status']
                ?? null,
            default => $verified['status'] ?? null,
        };

        return is_string($status) ? strtolower(trim($status)) : '';
    }

    private function validSignature(string $provider, Request $request, array $data): bool
    {
        $config = config('trustlens.payment_gateways.'.$provider, []);
        return match ($provider) {
            'paystack' => is_string($config['secret_key'] ?? null)
                && hash_equals(hash_hmac('sha512', $request->getContent(), $config['secret_key']), (string) $request->header('x-paystack-signature')),
            'flutterwave' => is_string($config['webhook_secret'] ?? null)
                && hash_equals($config['webhook_secret'], (string) $request->header('verif-hash')),
            'korapay' => is_string($config['secret_key'] ?? null)
                && hash_equals(hash_hmac('sha256', json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) ?: '', $config['secret_key']), (string) $request->header('x-korapay-signature')),
            'bachs' => $this->validBachsSignature($request, (string) ($config['webhook_secret'] ?? '')),
            default => false,
        };
    }

    private function verifyWithProvider(string $provider, array $data): array
    {
        $config = config('trustlens.payment_gateways.'.$provider, []);
        $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
        $secret = (string) ($config['secret_key'] ?? '');
        $response = match ($provider) {
            'paystack' => Http::acceptJson()->withToken($secret)->get($baseUrl.'/transaction/verify/'.rawurlencode((string) ($data['reference'] ?? ''))),
            'flutterwave' => Http::acceptJson()->withToken($secret)->get($baseUrl.'/transactions/'.rawurlencode((string) ($data['id'] ?? '')).'/verify'),
            'korapay' => Http::acceptJson()->withToken($secret)->get($baseUrl.'/charges/'.rawurlencode((string) ($data['reference'] ?? ''))),
            'bachs' => Http::acceptJson()->withToken($secret)->get($baseUrl.'/checkout-sessions/'.rawurlencode((string) ($data['checkout_id'] ?? ''))),
            default => throw new \RuntimeException('Unsupported payment gateway.'),
        };
        $response->throw();
        $body = $response->json();
        if ($provider === 'bachs') {
            if (! is_array($body)) {
                throw new \RuntimeException('Payment provider could not verify the checkout session.');
            }
            return $body;
        }
        if (! is_array($body) || ($body['status'] ?? null) !== true && ($body['status'] ?? null) !== 'success') {
            throw new \RuntimeException('Payment provider could not verify the charge.');
        }
        return is_array($body['data'] ?? null) ? $body['data'] : [];
    }

    private function validBachsSignature(Request $request, string $secret): bool
    {
        if ($secret === '') {
            return false;
        }
        $timestamp = (string) $request->header('x-bachs-timestamp', '');
        $v2 = (string) $request->header('x-bachs-signature-v2', '');
        $legacy = (string) $request->header('x-bachs-signature', '');
        if ($v2 !== '') {
            $parts = array_map('trim', explode(',', $v2));
            foreach ($parts as $part) {
                if (str_starts_with($part, 't=')) {
                    $timestamp = substr($part, 2);
                }
            }
            $signatures = [];
            foreach ($parts as $part) {
                if (str_starts_with($part, 'v1=')) {
                    $signatures[] = substr($part, 3);
                }
            }
        } else {
            $signatures = $legacy === '' ? [] : [$legacy];
        }
        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300 || $signatures === []) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }
        return false;
    }

    private function creditTopup(PaymentIntent $intent, string $providerTransactionId, array $providerResponse): void
    {
        DB::transaction(function () use ($intent, $providerTransactionId, $providerResponse): void {
            $intent = PaymentIntent::query()->lockForUpdate()->findOrFail($intent->id);
            if ($intent->status->value === 'paid') {
                return;
            }
            $now = now();
            $intent->forceFill(['status' => 'paid', 'paid_at' => $now, 'provider_metadata' => array_merge($intent->provider_metadata ?? [], ['verified_transaction' => $providerResponse])])->save();
            $wallet = Wallet::query()->firstOrCreate(
                ['user_id' => $intent->user_id, 'wallet_type' => 'topup_fund', 'currency' => $intent->currency],
                ['status' => 'active'],
            );
            PaymentTransaction::query()->firstOrCreate(
                ['payment_intent_id' => $intent->id],
                ['provider_transaction_id' => $providerTransactionId, 'transaction_type' => 'topup', 'amount_minor' => $intent->amount_minor,
                    'currency' => $intent->currency, 'status' => 'succeeded', 'provider_response' => $providerResponse, 'occurred_at' => $now],
            );
            LedgerEntry::query()->firstOrCreate(
                ['idempotency_key' => 'gateway-topup:'.$intent->id],
                ['wallet_id' => $wallet->id, 'entry_type' => 'gateway_topup', 'direction' => 'credit', 'amount_minor' => $intent->amount_minor,
                    'status' => 'posted', 'source_type' => 'payment_intent', 'source_id' => $intent->id,
                    'description' => ucfirst($intent->provider).' top-up', 'available_at' => $now, 'posted_at' => $now],
            );
            $currency = strtoupper($intent->currency);
            $amount = number_format(
                CurrencyMinorUnits::fromMinor((int) $intent->amount_minor, $currency),
                CurrencyMinorUnits::precision($currency),
            );
            Notification::query()->firstOrCreate(
                ['notification_key' => 'topup-completed-'.$intent->id],
                [
                    'user_id' => $intent->user_id,
                    'type' => 'topup_completed',
                    'title' => 'Top-up received',
                    'body' => 'Your top-up of '.$amount.' '.$currency.' has been added to your Top-up Funds wallet.',
                    'data' => ['payment_intent_id' => $intent->id, 'amount_minor' => $intent->amount_minor, 'amount' => $amount, 'currency' => $currency, 'reference' => $providerTransactionId, 'status' => 'paid'],
                ],
            );
        });
    }

    public function creditVerifiedTopup(PaymentIntent $intent, string $providerTransactionId, array $providerResponse): void
    {
        $this->creditTopup($intent, $providerTransactionId, $providerResponse);
    }

    public function completeVerifiedSubscription(PaymentIntent $intent, string $providerTransactionId, array $providerResponse): void
    {
        $this->completeSubscription($intent, $providerTransactionId, $providerResponse);
    }

    public function completeVerifiedPackageAddon(PaymentIntent $intent, string $providerTransactionId, array $providerResponse): void
    {
        $this->completePackageAddon($intent, $providerTransactionId, $providerResponse);
    }

    private function completePackageAddon(PaymentIntent $intent, string $providerTransactionId, array $providerResponse): void
    {
        DB::transaction(function () use ($intent, $providerTransactionId, $providerResponse): void {
            $intent = PaymentIntent::query()->lockForUpdate()->findOrFail($intent->id);
            if ($intent->status->value === 'paid') return;
            $now = now();
            $intent->forceFill(['status' => 'paid', 'paid_at' => $now,
                'provider_metadata' => array_merge($intent->provider_metadata ?? [], ['verified_transaction' => $providerResponse])])->save();
            PaymentTransaction::query()->firstOrCreate(['payment_intent_id' => $intent->id], [
                'provider_transaction_id' => $providerTransactionId, 'transaction_type' => 'package_addon',
                'amount_minor' => $intent->amount_minor, 'currency' => $intent->currency,
                'status' => 'succeeded', 'provider_response' => $providerResponse, 'occurred_at' => $now,
            ]);
            $addon = \App\Models\PackageAddon::query()->findOrFail($intent->source_id);
            Transaction::query()->updateOrCreate(['reference' => PaymentIntentLifecycle::transactionReference($intent)], [
                'user_id' => $intent->user_id, 'type' => 'package_addon', 'direction' => 'debit',
                'amount' => CurrencyMinorUnits::fromMinor($intent->amount_minor, $intent->currency), 'fee' => 0,
                'wallet' => null, 'status' => 'completed', 'description' => 'Package add-on purchase: '.$addon->name,
                'meta' => ['payment_intent_id' => $intent->id, 'addon_id' => $addon->id, 'currency' => $intent->currency, 'amount_minor' => $intent->amount_minor, 'provider' => $intent->provider],
            ]);
            app(ActivatePackageAddon::class)->handle($intent);
        });
    }

    private function completeSubscription(PaymentIntent $intent, string $providerTransactionId, array $providerResponse): void
    {
        DB::transaction(function () use ($intent, $providerTransactionId, $providerResponse): void {
            $intent = PaymentIntent::query()->lockForUpdate()->findOrFail($intent->id);
            if ($intent->status->value === 'paid') return;

            $now = now();
            $intent->forceFill([
                'status' => 'paid',
                'paid_at' => $now,
                'provider_metadata' => array_merge($intent->provider_metadata ?? [], ['verified_transaction' => $providerResponse]),
            ])->save();

            PaymentTransaction::query()->firstOrCreate(
                ['payment_intent_id' => $intent->id],
                [
                    'provider_transaction_id' => $providerTransactionId,
                    'transaction_type' => 'subscription',
                    'amount_minor' => $intent->amount_minor,
                    'currency' => $intent->currency,
                    'status' => 'succeeded',
                    'provider_response' => $providerResponse,
                    'occurred_at' => $now,
                ],
            );

            $plan = SubscriptionPlan::query()->findOrFail($intent->source_id);

            Transaction::query()->updateOrCreate(
                ['reference' => PaymentIntentLifecycle::transactionReference($intent)],
                [
                    'user_id' => $intent->user_id,
                    'type' => $plan->product_type->value,
                    'direction' => 'debit',
                    'amount' => CurrencyMinorUnits::fromMinor($intent->amount_minor, $intent->currency),
                    'fee' => 0,
                    'wallet' => null,
                    'status' => 'completed',
                    'description' => 'Subscription package purchase: '.$plan->name,
                    'meta' => [
                        'payment_intent_id' => $intent->id,
                        'plan_id' => $intent->source_id,
                        'currency' => $intent->currency,
                        'currency_minor_units' => CurrencyMinorUnits::precision($intent->currency),
                        'amount_minor' => $intent->amount_minor,
                        'provider' => $intent->provider,
                    ],
                ],
            );

            app(ActivateSubscription::class)->handle($intent->user, $plan, $intent);
        });
    }
}
