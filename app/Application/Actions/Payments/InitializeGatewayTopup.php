<?php

namespace App\Application\Actions\Payments;

use App\Application\Money\CurrencyMinorUnits;
use App\Application\Payments\PaymentIntentLifecycle;
use App\Application\Support\PaymentGatewaySettings;
use App\Enums\TransactionType;
use App\Models\PaymentIntent;
use App\Models\SubscriptionPlan;
use App\Models\PackageAddon;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class InitializeGatewayTopup
{
    public function __construct(private readonly PaymentIntentLifecycle $lifecycle) {}

    /** @return array{intent_id:int,reference:string,provider:string,checkout_url:string,status:string} */
    public function handle(
        User $user,
        int $amountMinor,
        string $currency,
        string $gateway,
        string $idempotencyKey,
        string $purpose = 'topup',
        ?SubscriptionPlan $subscriptionPlan = null,
        ?string $customerEmail = null,
        ?PackageAddon $packageAddon = null,
    ): array
    {
        $gateway = strtolower($gateway);
        $currency = strtoupper($currency);
        if (! in_array($gateway, config('trustlens.implemented_payment_gateways', []), true) || $gateway === 'manual') {
            throw ValidationException::withMessages(['gateway' => 'This online payment gateway is not configured.']);
        }
        if (! PaymentGatewaySettings::supports($gateway, $currency)) {
            throw ValidationException::withMessages(['currency' => 'This payment gateway does not accept the selected currency. Choose one of its supported currencies.']);
        }

        $email = trim($customerEmail ?: (string) $user->email);
        $emailValidator = Validator::make(['email' => $email], [
            'email' => ['required', 'string', 'max:191', 'email:rfc'],
        ]);
        if ($emailValidator->fails()) {
            throw ValidationException::withMessages([
                'customer_email' => 'Enter a valid email address for the payment receipt.',
            ]);
        }

        $providerConfig = config('trustlens.payment_gateways.'.$gateway, []);
        $webhookBaseUrl = rtrim((string) config('trustlens.payment_webhook_base_url'), '/');
        if (! str_starts_with($webhookBaseUrl, 'https://')) {
            throw ValidationException::withMessages(['gateway' => 'Online payments are not configured for a public HTTPS webhook yet.']);
        }
        $returnBaseUrl = rtrim((string) config('trustlens.payment_return_url'), '?');
        if (! str_starts_with($returnBaseUrl, 'https://')) {
            throw ValidationException::withMessages(['gateway' => 'Online payments require a public HTTPS payment return URL.']);
        }
        $reference = 'tls-'.$user->id.'-'.Str::lower(Str::random(24));
        if (! in_array($purpose, ['topup', 'subscription', 'package_addon'], true)
            || ($purpose === 'subscription' && $subscriptionPlan === null)
            || ($purpose === 'package_addon' && $packageAddon === null)) {
            throw ValidationException::withMessages(['payment' => 'Invalid payment purpose.']);
        }
        $intentKey = $purpose === 'topup'
            ? $gateway.'-topup:'.$user->id.':'.hash('sha256', $idempotencyKey)
            : $gateway.'-'.($purpose === 'subscription' ? 'sub' : 'addon').':'.hash('sha256', $user->id.':'.$idempotencyKey);
        $intent = PaymentIntent::query()->where('idempotency_key', $intentKey)->first();
        if ($intent) {
            if ($intent->amount_minor !== $amountMinor || $intent->currency !== $currency
                || $intent->purpose !== $purpose
                || ($purpose === 'subscription' && (int) $intent->source_id !== (int) $subscriptionPlan?->id)
                || ($purpose === 'package_addon' && (int) $intent->source_id !== (int) $packageAddon?->id)) {
                throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key was already used for a different top-up.']);
            }
            $url = $intent->provider_metadata['checkout_url'] ?? null;
            if (is_string($url) && $url !== '') {
                return ['intent_id' => $intent->id, 'reference' => (string) $intent->provider_reference, 'provider' => $gateway, 'checkout_url' => $url, 'status' => $intent->status->value];
            }
            throw ValidationException::withMessages(['payment' => 'This payment request is already being processed. Refresh your top-up history shortly.']);
        }

        $intent = DB::transaction(fn () => PaymentIntent::query()->create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'source_type' => match ($purpose) { 'subscription' => 'subscription_plan', 'package_addon' => 'package_addon', default => null },
            'source_id' => $subscriptionPlan?->id ?? $packageAddon?->id,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'provider' => $gateway,
            'provider_reference' => $reference,
            'idempotency_key' => $intentKey,
            'status' => 'pending',
            'expires_at' => now()->addHours(2),
        ]));

        // `transactions.type` is a TransactionType, not the plan's product
        // type: writing 'marketplace_access' here fataled every gateway
        // subscription checkout with a ValueError.
        $transactionType = match ($purpose) {
            'subscription' => TransactionType::SUBSCRIPTION_PLAN->value,
            'package_addon' => TransactionType::PACKAGE_ADDON->value,
            default => TransactionType::TOP_UP_FUND->value,
        };
        Transaction::query()->updateOrCreate(['reference' => 'payment-intent:'.$intent->id], [
            'user_id' => $user->id,
            'type' => $transactionType,
            'direction' => $purpose === 'topup' ? 'credit' : 'debit',
            'amount' => CurrencyMinorUnits::fromMinor($amountMinor, $currency),
            'fee' => 0,
            'wallet' => null,
            'status' => 'pending',
            'description' => match ($purpose) {
                'subscription' => 'Subscription package purchase: '.$subscriptionPlan?->name,
                'package_addon' => 'Package add-on purchase: '.$packageAddon?->name,
                default => 'Wallet top-up',
            },
            'meta' => [
                'payment_intent_id' => $intent->id,
                'currency' => $currency,
                'currency_minor_units' => CurrencyMinorUnits::precision($currency),
                'amount_minor' => $amountMinor,
                'provider' => $gateway,
                'purpose' => $purpose,
            ],
        ]);

        $amount = CurrencyMinorUnits::fromMinor($amountMinor, $currency);
        $name = trim((string) $user->full_name) ?: $user->username;
        $notificationUrl = $webhookBaseUrl.'/api/v1/webhooks/'.$gateway;
        $returnUrl = $returnBaseUrl;
        $returnUrl .= (str_contains($returnUrl, '?') ? '&' : '?').'intent_id='.$intent->id;

        try {
            $response = match ($gateway) {
                'paystack' => Http::acceptJson()->withToken($providerConfig['secret_key'])->post(rtrim($providerConfig['base_url'], '/').'/transaction/initialize', [
                    'email' => $email,
                    'amount' => (string) $amountMinor,
                    'currency' => $currency,
                    'reference' => $reference,
                    'callback_url' => $returnUrl,
                    'metadata' => ['user_id' => $user->id, 'payment_intent_id' => $intent->id],
                ]),
                'flutterwave' => Http::acceptJson()->withToken($providerConfig['secret_key'])->post(rtrim($providerConfig['base_url'], '/').'/payments', [
                    'tx_ref' => $reference,
                    'amount' => number_format($amount, CurrencyMinorUnits::precision($currency), '.', ''),
                    'currency' => $currency,
                    'redirect_url' => $returnUrl,
                    'customer' => ['email' => $email, 'name' => $name, 'phonenumber' => $user->phone],
                    'customizations' => [
                        'title' => in_array($purpose, ['subscription', 'package_addon'], true) ? 'TrustLens package' : 'TrustLens wallet top-up',
                        'description' => $purpose === 'subscription' ? 'Purchase '.$subscriptionPlan?->name.' package' : ($purpose === 'package_addon' ? 'Purchase '.$packageAddon?->name.' add-on' : 'Add funds to your TrustLens top-up wallet'),
                    ],
                    'meta' => ['user_id' => $user->id, 'payment_intent_id' => $intent->id],
                ]),
                'korapay' => Http::acceptJson()->withToken($providerConfig['secret_key'])->post(rtrim($providerConfig['base_url'], '/').'/charges/initialize', [
                    'amount' => number_format($amount, CurrencyMinorUnits::precision($currency), '.', ''),
                    'currency' => $currency,
                    'reference' => $reference,
                    'redirect_url' => $returnUrl,
                    'notification_url' => $notificationUrl,
                    'narration' => $purpose === 'subscription' ? 'TrustLens '.$subscriptionPlan?->name.' package' : ($purpose === 'package_addon' ? 'TrustLens '.$packageAddon?->name.' add-on' : 'TrustLens wallet top-up'),
                    'customer' => ['email' => $email, 'name' => $name],
                    'metadata' => ['user_id' => (string) $user->id, 'intent-id' => (string) $intent->id],
                ]),
                'bachs' => Http::acceptJson()->withToken($providerConfig['secret_key'])
                    ->withHeaders(['Idempotency-Key' => 'trustlens-'.$purpose.'-'.$intent->id])
                    ->post(rtrim($providerConfig['base_url'], '/').'/checkout-sessions', [
                        'pricing' => ['currency' => $currency, 'amount' => number_format($amount, CurrencyMinorUnits::precision($currency), '.', '')],
                        'billing_currency' => $currency,
                        'customer' => ['email' => $email, 'name' => $name],
                        'success_url' => $returnUrl,
                        'cancel_url' => $returnUrl,
                        'reference' => $reference,
                        'metadata' => ['user_id' => (string) $user->id, 'payment_intent_id' => (string) $intent->id],
                        'expires_in_minutes' => 120,
                    ]),
                default => throw ValidationException::withMessages(['gateway' => 'Unsupported payment gateway.']),
            };
            $response->throw();
            $body = $response->json();
            $checkoutUrl = match ($gateway) {
                'paystack' => data_get($body, 'data.authorization_url'),
                'flutterwave' => data_get($body, 'data.link'),
                'korapay' => data_get($body, 'data.checkout_url'),
                'bachs' => data_get($body, 'checkout_url'),
            };
            if (! is_string($checkoutUrl) || ! filter_var($checkoutUrl, FILTER_VALIDATE_URL)) {
                throw new \RuntimeException('The gateway did not return a valid checkout link.');
            }
            $checkoutId = $gateway === 'bachs' ? data_get($body, 'checkout_id') : null;
            if ($gateway === 'bachs' && (! is_string($checkoutId) || $checkoutId === '')) {
                throw new \RuntimeException('BACHS PAY did not return a checkout session ID.');
            }
            $intent->forceFill(['provider_metadata' => ['checkout_url' => $checkoutUrl, 'checkout_id' => $checkoutId]])->save();

            return ['intent_id' => $intent->id, 'reference' => $reference, 'provider' => $gateway, 'checkout_url' => $checkoutUrl, 'status' => 'pending'];
        } catch (\Throwable $exception) {
            // A checkout that could not be initialised is terminal: close the
            // intent and its projection through the single lifecycle writer.
            $this->lifecycle->markTerminal($intent, \App\Enums\PaymentIntentStatus::Failed, [
                'initialization_error' => 'Gateway checkout could not be initialized.',
            ]);
            if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                Log::warning('Payment gateway rejected checkout initialization.', [
                    'provider' => $gateway,
                    'intent_id' => $intent->id,
                    'status' => $exception->response?->status(),
                ]);

                throw ValidationException::withMessages([
                    'customer_email' => 'The payment provider rejected the checkout details. Check the payment email and try again.',
                ]);
            }
            report($exception);
            throw ValidationException::withMessages(['gateway' => 'Could not start checkout with the selected provider. Please try again.']);
        }
    }
}
