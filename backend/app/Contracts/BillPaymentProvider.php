<?php

namespace App\Contracts;

use App\Models\VtuOrder;
use App\Services\Vas\DTO\BillFulfilmentResult;

/**
 * A bill-payment (VTU) fulfilment provider: airtime, data, cable TV, electricity and education.
 * The provider that fulfils an order is pinned on the row, so requery/webhook always route back to
 * the driver that placed it — even after an admin switches the configured provider. Each provider
 * owns its own catalogue (billers + plans) and identifier scheme.
 */
interface BillPaymentProvider
{
    /** Machine name — one of the App\Services\Vas\BillProviderManager driver keys (vtpass|flutterwave). */
    public function name(): string;

    /** True only when this provider's credentials are present. */
    public function configured(): bool;

    /**
     * The bill services this provider offers, keyed by service (airtime|data|tv|electricity|education),
     * each with its billers/networks, pricing mode, and whether the customer must be validated.
     *
     * @return array<string, array<string, mixed>>
     */
    public function services(): array;

    /**
     * Plans/packages for a service+biller (data bundles, TV packages, exam pins), normalised to
     * [{code, label, amount}]. Empty for amount-priced services (airtime, electricity).
     *
     * @return array<int, array{code: string, label: string, amount: float}>
     */
    public function variations(string $service, string $serviceId): array;

    /**
     * Validate a customer identifier (meter / smartcard / profile id) before charging.
     *
     * @return array{ok: bool, name?: string, message?: string, data?: array<string, mixed>}
     */
    public function validateCustomer(string $service, string $serviceId, string $customerId, ?string $type = null): array;

    /** Submit the order to the provider for fulfilment. */
    public function pay(VtuOrder $order): BillFulfilmentResult;

    /** Ask the provider for the current state of a previously-submitted order. */
    public function requery(VtuOrder $order): BillFulfilmentResult;
}
