<?php

use App\Enums\TransactionType;
use App\Livewire\Backoffice\Payments\ManagePaymentRequests;
use App\Mail\ProductOrderConfirmation;
use App\Models\Admin;
use App\Models\PaymentReference;
use App\Models\PaymentResolutionLog;
use App\Models\ProductOrder;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Payments\PaymentResolutionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.korapay.base_url' => 'https://api.korapay.com',
        'services.korapay.secret_key' => 'sk_test_korapay',
        'services.korapay.currency' => 'NGN',
        'settings.contact.email' => '',
    ]);
});

function paymentRequestCharge(string $provider = 'manual', string $status = 'pending', array $meta = []): Transaction
{
    $user = User::factory()->create(['bills_balance' => 0]);
    $reference = 'BTU-'.strtoupper(Str::random(14));

    return Transaction::query()->create([
        'user_id' => $user->id,
        'type' => TransactionType::BILLS_TOPUP,
        'direction' => 'credit',
        'amount' => 25,
        'fee' => 0,
        'wallet' => 'bills',
        'status' => $status,
        'reference' => $reference,
        'provider' => $provider,
        'gateway_reference' => $provider === 'manual' ? $reference : 'checkout-'.$reference,
        'description' => 'Bills wallet top-up',
        'meta' => array_merge([
            'gateway' => $provider,
            'amount_ngn' => 5000,
            'currency' => 'NGN',
            'checkout_url' => 'https://checkout.example.test/'.$reference,
        ], $meta),
    ]);
}

function paymentRequestGuestOrder(string $provider = 'manual'): ProductOrder
{
    $reference = 'PRD-'.strtoupper(Str::random(12));

    return ProductOrder::query()->create([
        'reference' => $reference,
        'package_id' => null,
        'product_id' => null,
        'product_slug' => 'royal-esim',
        'product_name' => 'Royal eSIM',
        'source' => ProductOrder::SOURCE_PACKAGE,
        'full_name' => 'Guest Buyer',
        'phone' => '08012345678',
        'email' => 'guest@example.test',
        'amount_usd' => 10,
        'amount_ngn' => 15000,
        'currency' => 'NGN',
        'provider' => $provider,
        'gateway_reference' => $reference,
        'checkout_url' => 'https://checkout.example.test/'.$reference,
        'status' => ProductOrder::STATUS_PENDING,
        'meta' => [],
    ]);
}

function fakePaidKorapayForPaymentRequest(float $amount = 5000): void
{
    Http::fake(function (ClientRequest $request) use ($amount) {
        $reference = basename((string) parse_url($request->url(), PHP_URL_PATH));

        return Http::response([
            'status' => true,
            'data' => [
                'status' => 'success',
                'amount' => $amount,
                'currency' => 'NGN',
                'reference' => $reference,
                'transaction_reference' => 'KPY-'.strtoupper($reference),
            ],
        ]);
    });
}

test('payment request page is available to backoffice admins and lists all provider channels', function () {
    $charge = paymentRequestCharge('manual');

    $this->actingAs(Admin::factory()->regular()->create(), 'admin')
        ->get(route('backoffice.payment-requests.index'))
        ->assertOk()
        ->assertSee('Payment requests')
        ->assertSee('Account payments')
        ->assertSee('Guest storefront orders')
        ->assertSee('Korapay')
        ->assertSee('Paystack')
        ->assertSee('Flutterwave')
        ->assertSee('Bachs')
        ->assertSee('Manual bank transfer')
        ->assertSee($charge->reference);

    $this->get(route('backoffice.payment-requests.index'))->assertRedirect(route('backoffice.login'));
});

test('a provider recheck completes a previously failed request only after a verified gateway success', function () {
    fakePaidKorapayForPaymentRequest();
    $charge = paymentRequestCharge('korapay', 'failed', ['manual_review' => ['reason' => 'callback_unverified']]);
    $admin = Admin::factory()->regular()->create();

    $this->actingAs($admin, 'admin');

    Livewire::test(ManagePaymentRequests::class)
        ->call('openRequest', 'transaction', $charge->id)
        ->call('recheckSelected')
        ->assertHasNoErrors();

    $resolutionLog = PaymentResolutionLog::query()->where('reference', $charge->reference)->where('action', 'provider_recheck')->firstOrFail();

    expect($charge->fresh()->status)->toBe('completed')
        ->and((float) $charge->user->fresh()->bills_balance)->toBe(25.0)
        ->and(PaymentReference::query()->where('reference', $charge->reference)->count())->toBe(1)
        ->and($resolutionLog->admin_id)->toBe($admin->id)
        ->and($resolutionLog->details['outcome'] ?? null)->toBe('completed');

    Http::assertSent(fn (ClientRequest $request): bool => str_contains($request->url(), '/merchant/api/v1/charges/'.$charge->reference));
});

test('manual confirmation uses the normal ledger and settlement flow and cannot credit twice', function () {
    $charge = paymentRequestCharge('manual');
    $user = $charge->user;
    $admin = Admin::factory()->super()->create();
    $this->actingAs($admin, 'admin');

    Livewire::test(ManagePaymentRequests::class)
        ->call('openRequest', 'transaction', $charge->id)
        ->set('manualAmount', '5000.00')
        ->set('manualCurrency', 'NGN')
        ->set('manualGatewayReference', 'BANK-TRANSFER-20261019-001')
        ->set('verificationSource', 'bank_statement')
        ->set('evidenceReference', 'STATEMENT-LINE-8842')
        ->set('manualNote', 'Matched bank credit against reference and exact requested amount.')
        ->set('manualAttestation', true)
        ->call('confirmManually')
        ->assertHasNoErrors();

    $ledgerEntry = PaymentReference::query()->where('reference', $charge->reference)->firstOrFail();
    $resolutionLog = PaymentResolutionLog::query()->where('reference', $charge->reference)->where('action', 'manual_confirm')->firstOrFail();

    expect($charge->fresh()->status)->toBe('completed')
        ->and((float) $user->fresh()->bills_balance)->toBe(25.0)
        ->and($ledgerEntry->gateway_reference)->toBe('BANK-TRANSFER-20261019-001')
        ->and($ledgerEntry->meta['backoffice_resolution']['action'] ?? null)->toBe('manual_confirm')
        ->and($resolutionLog->admin_id)->toBe($admin->id)
        ->and($resolutionLog->details['admin_attested'] ?? false)->toBeTrue();

    expect(app(\App\Services\Payments\ChargeSettlement::class)->finalize($charge->reference))->toBe('completed')
        ->and((float) $user->fresh()->bills_balance)->toBe(25.0)
        ->and(PaymentReference::query()->where('reference', $charge->reference)->count())->toBe(1);
});

test('a provider payment reference can be manually settled only once across different requests', function () {
    $first = paymentRequestCharge('manual');
    $second = paymentRequestCharge('manual');
    $admin = Admin::factory()->super()->create();
    $this->actingAs($admin, 'admin');

    $verification = [
        'manualAmount' => '5000.00',
        'manualCurrency' => 'NGN',
        'manualGatewayReference' => 'BANK-REUSED-TRANSFER-909',
        'verificationSource' => 'bank_statement',
        'evidenceReference' => 'STATEMENT-REUSED-909',
        'manualNote' => 'Confirmed this transfer reference against the official statement.',
        'manualAttestation' => true,
    ];

    Livewire::test(ManagePaymentRequests::class)
        ->call('openRequest', 'transaction', $first->id)
        ->set('manualAmount', $verification['manualAmount'])
        ->set('manualCurrency', $verification['manualCurrency'])
        ->set('manualGatewayReference', $verification['manualGatewayReference'])
        ->set('verificationSource', $verification['verificationSource'])
        ->set('evidenceReference', $verification['evidenceReference'])
        ->set('manualNote', $verification['manualNote'])
        ->set('manualAttestation', $verification['manualAttestation'])
        ->call('confirmManually')
        ->assertHasNoErrors();

    Livewire::test(ManagePaymentRequests::class)
        ->call('openRequest', 'transaction', $second->id)
        ->set('manualAmount', $verification['manualAmount'])
        ->set('manualCurrency', $verification['manualCurrency'])
        ->set('manualGatewayReference', $verification['manualGatewayReference'])
        ->set('verificationSource', $verification['verificationSource'])
        ->set('evidenceReference', $verification['evidenceReference'])
        ->set('manualNote', $verification['manualNote'])
        ->set('manualAttestation', $verification['manualAttestation'])
        ->call('confirmManually')
        ->assertHasErrors(['manualGatewayReference']);

    expect($first->fresh()->status)->toBe('completed')
        ->and($second->fresh()->status)->toBe('pending')
        ->and((float) $first->user->fresh()->bills_balance)->toBe(25.0)
        ->and((float) $second->user->fresh()->bills_balance)->toBe(0.0)
        ->and(PaymentReference::query()->where('provider', 'manual')->where('gateway_reference', $verification['manualGatewayReference'])->count())->toBe(1);
});

test('manual approval requires a provider reference distinct from the internal request reference', function () {
    $charge = paymentRequestCharge('manual');
    $this->actingAs(Admin::factory()->super()->create(), 'admin');

    Livewire::test(ManagePaymentRequests::class)
        ->call('openRequest', 'transaction', $charge->id)
        ->set('manualAmount', '5000.00')
        ->set('manualCurrency', 'NGN')
        ->set('manualGatewayReference', strtolower($charge->reference))
        ->set('verificationSource', 'bank_statement')
        ->set('evidenceReference', 'STATEMENT-REFERENCE-CHECK')
        ->set('manualNote', 'Checked bank evidence against the requested payment amount.')
        ->set('manualAttestation', true)
        ->call('confirmManually')
        ->assertHasErrors(['manualGatewayReference']);

    expect($charge->fresh()->status)->toBe('pending')
        ->and(PaymentReference::query()->where('reference', $charge->reference)->exists())->toBeFalse();
});

test('manual approval rejects an underpayment without changing the pending request', function () {
    $charge = paymentRequestCharge('manual');
    $this->actingAs(Admin::factory()->super()->create(), 'admin');

    Livewire::test(ManagePaymentRequests::class)
        ->call('openRequest', 'transaction', $charge->id)
        ->set('manualAmount', '4999.00')
        ->set('manualCurrency', 'NGN')
        ->set('manualGatewayReference', 'BANK-UNDERPAID-55')
        ->set('verificationSource', 'bank_statement')
        ->set('evidenceReference', 'STATEMENT-55')
        ->set('manualNote', 'Bank statement entry was below the requested total.')
        ->set('manualAttestation', true)
        ->call('confirmManually')
        ->assertHasErrors(['manualAmount']);

    expect($charge->fresh()->status)->toBe('pending')
        ->and(PaymentReference::query()->where('reference', $charge->reference)->exists())->toBeFalse()
        ->and((float) $charge->user->fresh()->bills_balance)->toBe(0.0);
});

test('only a super admin can manually confirm or close a payment request', function () {
    $charge = paymentRequestCharge('manual');
    $regular = Admin::factory()->regular()->create();

    expect(fn () => app(PaymentResolutionService::class)->confirmManually('transaction', $charge->id, $regular, [
        'amount' => 5000,
        'currency' => 'NGN',
        'gateway_reference' => 'BANK-TRANSFER-REGULAR-1',
        'verification_source' => 'bank_statement',
        'evidence_reference' => 'STATEMENT-REGULAR-1',
        'note' => 'Verified payment on the bank statement.',
        'attested' => true,
    ]))->toThrow(AuthorizationException::class);

    expect($charge->fresh()->status)->toBe('pending')
        ->and(PaymentReference::query()->count())->toBe(0);
});

test('a provider recheck that still reports pending leaves the request and entitlement untouched', function () {
    $charge = paymentRequestCharge('manual');
    $admin = Admin::factory()->regular()->create();
    $this->actingAs($admin, 'admin');

    Livewire::test(ManagePaymentRequests::class)
        ->call('openRequest', 'transaction', $charge->id)
        ->call('recheckSelected')
        ->assertHasNoErrors();

    $resolutionLog = PaymentResolutionLog::query()->where('reference', $charge->reference)->firstOrFail();

    expect($charge->fresh()->status)->toBe('pending')
        ->and((float) $charge->user->fresh()->bills_balance)->toBe(0.0)
        ->and(PaymentReference::query()->count())->toBe(0)
        ->and($resolutionLog->details['outcome'] ?? null)->toBe('pending');
});

test('a super admin can close a pending request as unpaid with an audit note', function () {
    $charge = paymentRequestCharge('manual');
    $admin = Admin::factory()->super()->create();
    $this->actingAs($admin, 'admin');

    Livewire::test(ManagePaymentRequests::class)
        ->call('openRequest', 'transaction', $charge->id)
        ->set('closeNote', 'Checked bank statement; no matching successful transfer was received.')
        ->set('closeAttestation', true)
        ->call('closeAsUnpaid')
        ->assertHasNoErrors();

    expect($charge->fresh()->status)->toBe('failed')
        ->and(PaymentReference::query()->where('reference', $charge->reference)->exists())->toBeFalse()
        ->and(PaymentResolutionLog::query()->where('reference', $charge->reference)->where('action', 'closed_unpaid')->value('admin_id'))->toBe($admin->id)
        ->and(PaymentResolutionLog::query()->where('reference', $charge->reference)->value('note'))->toContain('no matching successful transfer');
});

test('manual confirmation can settle guest storefront orders and records the decision', function () {
    Mail::fake();
    $order = paymentRequestGuestOrder('manual');
    $admin = Admin::factory()->super()->create();
    $this->actingAs($admin, 'admin');

    Livewire::test(ManagePaymentRequests::class)
        ->set('requestGroup', 'product_orders')
        ->call('openRequest', 'product_order', $order->id)
        ->set('manualAmount', '15000.00')
        ->set('manualCurrency', 'NGN')
        ->set('manualGatewayReference', 'BANK-GUEST-ORDER-509')
        ->set('verificationSource', 'bank_statement')
        ->set('evidenceReference', 'STATEMENT-GUEST-509')
        ->set('manualNote', 'Confirmed the guest order payment in the bank statement.')
        ->set('manualAttestation', true)
        ->call('confirmManually')
        ->assertHasNoErrors();

    expect($order->fresh()->status)->toBe(ProductOrder::STATUS_PAID)
        ->and(PaymentReference::query()->where('reference', $order->reference)->whereNull('transaction_id')->count())->toBe(1)
        ->and(PaymentResolutionLog::query()->where('reference', $order->reference)->where('product_order_id', $order->id)->value('admin_id'))->toBe($admin->id);

    Mail::assertSent(ProductOrderConfirmation::class);
});
