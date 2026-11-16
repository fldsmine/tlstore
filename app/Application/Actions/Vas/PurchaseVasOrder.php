<?php

namespace App\Application\Actions\Vas;

use App\Application\Money\CurrencyConverter;
use App\Application\Money\CurrencyMinorUnits;
use App\Application\Security\SecurityPinHasher;
use App\Enums\VtuOrderStatus;
use App\Enums\WalletType;
use App\Features\Vas\Application\VasCatalogService;
use App\Features\Vas\Infrastructure\VtpassClient;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\VtuOrder;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PurchaseVasOrder
{
    public function __construct(
        private readonly VasCatalogService $catalog,
        private readonly VtpassClient $vtpass,
        private readonly CurrencyConverter $currencyConverter,
        private readonly SecurityPinHasher $pinHasher,
        private readonly SettleVasOrder $settleOrder,
    ) {}

    /** @param array<string,mixed> $input @return array{order:VtuOrder,created:bool} */
    public function handle(User $user, array $input): array
    {
        $normalized = $this->normalize($user, $input);
        $idempotencyKey = hash('sha256', $user->id.':'.$normalized['idempotency_key']);
        $payloadHash = hash('sha256', json_encode($normalized['payload'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        // A retry of a completed or pending request returns that order without calling VTpass or
        // debiting again. The same key cannot be reused for a different purchase payload.
        $existing = VtuOrder::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return ['order' => $this->assertSamePayload($existing, $payloadHash), 'created' => false];
        }

        if (filled($user->security_pin) && ! $this->pinHasher->check((string) ($input['security_pin'] ?? ''), $user->security_pin)) {
            throw ValidationException::withMessages(['security_pin' => 'The security PIN is incorrect.']);
        }

        $serviceDefinition = $this->catalog->service($normalized['service']);
        $provider = $this->provider($serviceDefinition, $normalized['service_id']);
        $customerId = $normalized['customer_id'];
        $phone = $normalized['phone'];
        $variationCode = $normalized['variation_code'];
        $amountNgnMinor = $this->amountMinor($normalized, $serviceDefinition);
        $customerName = null;

        if ((bool) ($provider['requires_verification'] ?? $serviceDefinition['requires_verification'] ?? false)) {
            $type = match ($normalized['service']) {
                'electricity' => $normalized['meter_type'],
                'education' => $variationCode,
                default => null,
            };
            $verification = $this->catalog->verify(
                $normalized['service'],
                $normalized['service_id'],
                (string) $customerId,
                $type,
            );
            if (! $verification['ok'] || blank($verification['customer_name'])) {
                throw ValidationException::withMessages(['customer_id' => $verification['message'] ?: 'Verify the recipient before continuing.']);
            }
            $customerName = $verification['customer_name'];
        }

        if ($amountNgnMinor < 1) {
            throw ValidationException::withMessages(['amount_minor' => 'The amount must be greater than zero.']);
        }
        if ($phone !== null && ! $this->validNigerianPhone($phone)) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid Nigerian phone number.']);
        }

        $requestId = now('Africa/Lagos')->format('YmdHi').Str::lower(Str::random(12));
        $order = DB::transaction(function () use (
            $user, $normalized, $idempotencyKey, $payloadHash, $requestId,
            $serviceDefinition, $provider, $variationCode, $customerId, $phone,
            $amountNgnMinor, $customerName,
        ): array {
            $walletCurrency = strtoupper((string) ($user->wallet_currency ?: $user->preferred_currency ?: 'USD'));
            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->where('wallet_type', WalletType::TopupFund->value)
                ->where('currency', $walletCurrency)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();
            if (! $wallet) {
                throw ValidationException::withMessages(['wallet' => 'Your top-up fund wallet is unavailable.']);
            }

            // Re-check after taking the wallet lock to serialize same-user purchase retries.
            $existing = VtuOrder::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return ['order' => $this->assertSamePayload($existing, $payloadHash), 'created' => false];
            }

            $quote = $this->currencyConverter->convertMinor($amountNgnMinor, 'NGN', $wallet->currency);
            $chargeMinor = (int) $quote['amount_minor'];
            if ($chargeMinor < 1) {
                throw ValidationException::withMessages(['amount_minor' => 'The converted wallet charge is too small.']);
            }
            if ((int) $wallet->balance_minor < $chargeMinor) {
                throw ValidationException::withMessages(['wallet' => 'Your top-up fund wallet has insufficient funds.']);
            }

            $serviceLabel = (string) ($serviceDefinition['label'] ?? ucfirst($normalized['service']));
            $providerLabel = (string) ($provider['label'] ?? $normalized['service_id']);
            $plan = $this->selectedPlan($normalized, $serviceDefinition);
            $meta = array_filter([
                'service_label' => $serviceLabel,
                'provider_label' => $providerLabel,
                'plan_label' => $plan['name'] ?? null,
                'meter_type' => $normalized['meter_type'],
                'subscription_action' => $normalized['subscription_action'],
                'currency' => strtoupper($wallet->currency),
                'currency_minor_units' => CurrencyMinorUnits::precision($wallet->currency),
                'amount_ngn_minor' => $amountNgnMinor,
                'charged_amount_minor' => $chargeMinor,
                'exchange_rate_ngn_to_wallet' => $quote['rate'],
                'request_id' => $requestId,
            ], static fn ($value): bool => $value !== null);

            $order = VtuOrder::query()->create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'service' => $normalized['service'],
                'service_id' => $normalized['service_id'],
                'provider' => 'vtpass',
                'variation_code' => $variationCode,
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'phone' => $phone,
                'amount_ngn_minor' => $amountNgnMinor,
                'charged_amount_minor' => $chargeMinor,
                'currency' => strtoupper($wallet->currency),
                'exchange_rate' => $quote['rate'],
                'fee_minor' => 0,
                'status' => VtuOrderStatus::Pending,
                'request_id' => $requestId,
                'idempotency_key' => $idempotencyKey,
                'payload_hash' => $payloadHash,
                'refunded' => false,
                'meta' => $meta,
            ]);

            $ledger = LedgerEntry::query()->create([
                'wallet_id' => $wallet->id,
                'entry_type' => 'vtu_purchase',
                'direction' => 'debit',
                'amount_minor' => $chargeMinor,
                'status' => LedgerEntryStatus::Reserved,
                'source_type' => 'vtu_order',
                'source_id' => $order->id,
                'idempotency_key' => 'vas-purchase:'.$idempotencyKey,
                'description' => $serviceLabel.' purchase — '.$providerLabel,
                'metadata' => [
                    'vtu_order_id' => $order->id,
                    'request_id' => $requestId,
                    'service' => $normalized['service'],
                    'service_id' => $normalized['service_id'],
                    'currency' => strtoupper($wallet->currency),
                    'currency_minor_units' => CurrencyMinorUnits::precision($wallet->currency),
                    'amount_ngn_minor' => $amountNgnMinor,
                    'exchange_rate_ngn_to_wallet' => $quote['rate'],
                    'recipient_last4' => substr((string) $customerId, -4),
                ],
            ]);
            $order->forceFill(['debit_ledger_entry_id' => $ledger->id])->save();

            return ['order' => $order, 'created' => true];
        });

        if (! $order['created']) {
            return ['order' => $order['order'], 'created' => false];
        }

        $freshOrder = $order['order']->refresh();
        $providerResult = $this->vtpass->purchase($this->providerPayload(
            $freshOrder,
            $normalized,
            $amountNgnMinor,
        ));

        return [
            'order' => $this->settleOrder->handle($freshOrder, $providerResult),
            'created' => true,
        ];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function normalize(User $user, array $input): array
    {
        $service = strtolower(trim((string) ($input['service'] ?? '')));
        $serviceId = strtolower(trim((string) ($input['service_id'] ?? '')));
        $definition = $this->catalog->definition($service);
        $provider = $this->provider($definition, $serviceId);
        $isPhoneRecipient = str_contains(strtolower((string) ($provider['recipient_label'] ?? $definition['recipient_label'] ?? '')), 'phone');
        $customerId = trim((string) ($isPhoneRecipient
            ? ($input['phone'] ?? $input['customer_id'] ?? '')
            : ($input['customer_id'] ?? '')));
        $phone = $isPhoneRecipient
            ? $customerId
            : trim((string) ($input['phone'] ?? $user->phone ?? ''));

        if ($customerId === '') {
            throw ValidationException::withMessages(['customer_id' => 'Enter the '.strtolower((string) ($provider['recipient_label'] ?? $definition['recipient_label'] ?? 'recipient')).'.']);
        }
        if ($isPhoneRecipient && ! $this->validNigerianPhone($customerId)) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid Nigerian phone number.']);
        }
        if (! $isPhoneRecipient && $phone === '') {
            throw ValidationException::withMessages(['phone' => 'Enter a contact phone number for provider notifications.']);
        }

        $variationCode = filled($input['variation_code'] ?? null) ? trim((string) $input['variation_code']) : null;
        $meterType = filled($input['meter_type'] ?? null) ? strtolower(trim((string) $input['meter_type'])) : null;
        $subscriptionAction = filled($input['subscription_action'] ?? null)
            ? strtolower(trim((string) $input['subscription_action']))
            : null;

        if (($definition['pricing'] ?? null) === 'plan' && $variationCode === null) {
            throw ValidationException::withMessages(['variation_code' => 'Choose a plan.']);
        }
        if (($definition['pricing'] ?? null) === 'amount' && (! is_numeric($input['amount_minor'] ?? null) || (int) $input['amount_minor'] < 1)) {
            throw ValidationException::withMessages(['amount_minor' => 'Enter a valid amount.']);
        }
        if ($service === 'electricity' && ! in_array($meterType, ['prepaid', 'postpaid'], true)) {
            throw ValidationException::withMessages(['meter_type' => 'Select prepaid or postpaid meter type.']);
        }
        if ($service === 'tv' && ! in_array($subscriptionAction, ['renew', 'change'], true)) {
            throw ValidationException::withMessages(['subscription_action' => 'Select renew or change plan.']);
        }

        $payload = [
            'service' => $service,
            'service_id' => $serviceId,
            'customer_id' => $customerId,
            'phone' => $phone,
            'amount_minor' => isset($input['amount_minor']) ? (int) $input['amount_minor'] : null,
            'variation_code' => $variationCode,
            'meter_type' => $meterType,
            'subscription_action' => $subscriptionAction,
        ];

        return [
            ...$payload,
            'payload' => $payload,
            'idempotency_key' => trim((string) ($input['idempotency_key'] ?? '')),
        ];
    }

    /** @param array<string,mixed> $normalized @param array<string,mixed> $serviceDefinition */
    private function amountMinor(array $normalized, array $serviceDefinition): int
    {
        if (($serviceDefinition['pricing'] ?? null) === 'amount') {
            return (int) $normalized['amount_minor'];
        }

        return (int) ($this->selectedPlan($normalized, $serviceDefinition)['amount_minor'] ?? 0);
    }

    /** @param array<string,mixed> $normalized @param array<string,mixed> $serviceDefinition @return array<string,mixed>|null */
    private function selectedPlan(array $normalized, array $serviceDefinition): ?array
    {
        if (($serviceDefinition['pricing'] ?? null) !== 'plan') {
            return null;
        }

        foreach ($this->catalog->plans($normalized['service'], $normalized['service_id']) as $plan) {
            if (($plan['code'] ?? null) === $normalized['variation_code']) {
                return $plan;
            }
        }

        throw ValidationException::withMessages(['variation_code' => 'That plan is no longer available. Refresh the catalog and choose another plan.']);
    }

    /** @param array<string,mixed> $definition @return array<string,mixed> */
    private function provider(array $definition, string $serviceId): array
    {
        foreach ($definition['providers'] ?? [] as $provider) {
            if (($provider['id'] ?? null) === $serviceId) {
                return $provider;
            }
        }

        throw ValidationException::withMessages(['service_id' => 'Choose a valid provider for this service.']);
    }

    private function assertSamePayload(VtuOrder $existing, string $payloadHash): VtuOrder
    {
        if (! hash_equals($existing->payload_hash, $payloadHash)) {
            throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key was already used for a different purchase.']);
        }

        return $existing;
    }

    private function validNigerianPhone(string $phone): bool
    {
        return preg_match('/^(?:0[0-9]{10}|\+234[0-9]{10})$/', $phone) === 1;
    }

    /** @param array<string,mixed> $normalized @return array<string,mixed> */
    private function providerPayload(VtuOrder $order, array $normalized, int $amountNgnMinor): array
    {
        $payload = [
            'request_id' => $order->request_id,
            'serviceID' => $order->service_id,
            'phone' => $order->phone,
            'amount' => CurrencyMinorUnits::fromMinor($amountNgnMinor, 'NGN'),
        ];

        if (in_array($order->service, ['data', 'tv', 'education'], true) || $order->service === 'electricity') {
            $payload['billersCode'] = $order->customer_id;
        }
        if ($normalized['variation_code'] !== null) {
            $payload['variation_code'] = $normalized['variation_code'];
        }
        if ($order->service === 'electricity') {
            $payload['variation_code'] = $normalized['meter_type'];
        }
        if ($order->service === 'tv') {
            $payload['subscription_type'] = $normalized['subscription_action'];
        }

        return $payload;
    }

}
