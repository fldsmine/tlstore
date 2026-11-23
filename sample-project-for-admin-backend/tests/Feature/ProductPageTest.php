<?php

use App\Enums\AdminRole;
use App\Livewire\Backoffice\Products\OrderShow;
use App\Livewire\Backoffice\Products\Orders;
use App\Mail\ProductDelivery;
use App\Mail\ProductOrderConfirmation;
use App\Mail\ProductOrderNotice;
use App\Models\Admin;
use App\Models\Country;
use App\Models\Currency;
use App\Models\PaymentReference;
use App\Models\ProductOrder;
use App\Models\Transaction;
use App\Services\Payments\ChargeSettlement;
use App\Services\Payments\ProductOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * The guest storefront on product.n-calls.com: two eSIMs, a checkout that takes a name, phone and
 * email, a Paystack payment link, and a receipt that promises the product by email.
 *
 * Only Paystack's HTTP is faked; the real order path (controller → ProductOrderService → gateway
 * verify → reconciliation → single-use ledger → fulfilment queue) runs underneath, because the
 * money is just as real here as it is for a signed-in charge.
 */
beforeEach(function () {
    $this->withoutVite();

    // ₦1,500 / $1 — the storefront price for a product is its package price in NGN.
    Currency::create(['code' => 'NGN', 'name' => 'Nigerian Naira', 'symbol' => '₦', 'usd_exchange_rate' => 1500, 'is_active' => true]);
    Country::create(['name' => 'Nigeria', 'code' => 'NG', 'currency_code' => 'NGN', 'is_active' => true]);

    makeProductPackage('Diamond eSIM', 1, [
        'description' => 'Ideal for beginners.',
        'price' => 10.5,
        'cloud_calls' => false,
        'cloud_sms' => true,
        'cloud_contact_limit' => 0,
        'cloud_sms_limit' => 10,
        'daily_call_minutes' => 30,
        'call_id_limit' => 8,
        'features' => ['Access to allocated phone numbers', 'Earn per minute of call activity'],
        'is_featured' => false,
    ]);

    makeProductPackage('Royal eSIM', 2, [
        'description' => 'For users ready to maximize earnings.',
        'price' => 17.5,
        'cloud_calls' => true,
        'cloud_sms' => true,
        'cloud_contact_limit' => 10,
        'cloud_sms_limit' => 20,
        'daily_call_minutes' => 90,
        'call_id_limit' => 15,
        'features' => ['More allocated phone numbers', 'Full access to earning features'],
        'is_featured' => true,
    ]);

    config([
        'product.provider' => 'paystack',
        'product.region' => 'nigeria',
        'product.products' => ['diamond-esim', 'royal-esim'],
        'product.ops_email' => 'ops@nextel.test',
        'services.paystack.base_url' => 'https://api.paystack.co',
        'services.paystack.secret_key' => 'sk_test_paystack_product',
        'services.paystack.currency' => 'NGN',
        'settings.contact.email' => 'support@nextel.test',
    ]);
});

/** Create one on-sale package (the storefront reads its price, copy and specs). */
function makeProductPackage(string $name, int $tier, array $attributes = []): \App\Models\Package
{
    return \App\Models\Package::create(array_merge([
        'name' => $name,
        'tier_level' => $tier,
        'price' => 10.0,
        'is_active' => true,
        'enable_cloud' => true,
    ], $attributes));
}

/**
 * Fake Paystack for the product flow: initialize returns a payment link (remembering what each
 * reference was asked for, in kobo) and verify echoes it back as paid unless `$verify()` overrides.
 *
 * ONE stateful closure — Laravel merges stacked Http::fake callbacks, so a second fake() would
 * silently lose branches.
 *
 * @param  callable(string, float): array<string, mixed>|null  $verify
 * @param  array<string, mixed>|null  $captured  receives the initialize payload
 */
function fakeProductGateway(?callable $verify = null, ?array &$captured = null): void
{
    $expected = [];

    Http::fake(function (ClientRequest $request) use (&$expected, $verify, &$captured) {
        $url = $request->url();

        if (str_contains($url, '/transaction/initialize')) {
            $body = $request->data();
            $reference = (string) ($body['reference'] ?? '');
            $expected[$reference] = (int) ($body['amount'] ?? 0);
            $captured = $body;

            return Http::response([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/'.$reference,
                    'access_code' => 'acc_'.$reference,
                    'reference' => $reference,
                ],
            ]);
        }

        if (str_contains($url, '/transaction/verify/')) {
            $reference = urldecode((string) substr($url, (int) strrpos($url, '/') + 1));
            $overrides = $verify !== null ? ($verify($reference, (float) ($expected[$reference] ?? 0) / 100) ?? []) : [];

            if (($overrides['throw'] ?? false) === true) {
                throw new ConnectionException('cURL error 28: Operation timed out');
            }

            if (isset($overrides['http'])) {
                return Http::response(['status' => false, 'message' => 'Server error'], (int) $overrides['http']);
            }

            return Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'status' => $overrides['dataStatus'] ?? 'success',
                    'amount' => $overrides['amount'] ?? ($expected[$reference] ?? 0),
                    'currency' => $overrides['currency'] ?? 'NGN',
                    'reference' => $overrides['reference'] ?? $reference,
                    'id' => 'ps_'.substr(md5($reference), 0, 10),
                ],
            ]);
        }

        return Http::response(['status' => false, 'message' => 'unexpected '.$request->method().' '.$url], 500);
    });
}

/** Start a guest checkout the way the form does, and return the pending order. */
function startProductOrder(\Tests\TestCase $case, string $slug = 'diamond-esim', array $buyer = []): ProductOrder
{
    $payload = array_merge([
        'full_name' => 'Chinedu Okeke',
        'phone' => '0803 123 4567',
        'email' => 'Chinedu@Example.com',
    ], $buyer);

    $case->post(route('product.start', $slug), $payload)->assertRedirect();

    // Resolved by the email the buyer just used, so a second order in the same second can never be
    // mistaken for this one (created_at only has second precision).
    return ProductOrder::query()->where('email', strtolower($payload['email']))->sole();
}

/* ------------------------------------------------------------------ storefront */

test('a guest sees both eSIMs, priced in naira, without signing in', function () {
    $this->get(route('product.index'))
        ->assertOk()
        ->assertSee('Diamond eSIM')
        ->assertSee('Royal eSIM')
        // $10.50 and $17.50 at ₦1,500/$1 — the same conversion the charge uses.
        ->assertSee('₦15,750.00')
        ->assertSee('₦26,250.00')
        ->assertSee('Buy Diamond eSIM')
        ->assertSee('Buy Royal eSIM');
});

test('the product host serves the same storefront at its root', function () {
    $host = trim((string) config('product.host'));

    if ($host === '') {
        $this->markTestSkipped('No product host configured.');
    }

    $this->get("http://{$host}/")
        ->assertOk()
        ->assertSee('Diamond eSIM');
});

test('the checkout page shows the product, its details and the full cost', function () {
    $page = $this->get(route('product.checkout', 'royal-esim'))
        ->assertOk()
        ->assertSee('Royal eSIM')
        ->assertSee('Cost details')
        ->assertSee('Total to pay')
        ->assertSee('Delivery')
        ->assertSee('Full name')
        ->assertSee('Phone number')
        ->assertSee('Email address')
        ->assertSee('Paystack')        // the provider the buyer is about to be handed to
        ->assertSee('type="submit"', false);

    // The price appears as the line item AND as the total to pay — the amount the buyer sees is the
    // amount the charge asks for (asserted structurally, not by matching rendered copy).
    expect(substr_count($page->getContent(), '₦26,250.00'))->toBeGreaterThanOrEqual(2);
});

test('the checkout page does not offer a product that is not on sale', function () {
    $this->get(route('product.checkout', 'diamond-esim'))->assertOk();
    $this->get(route('product.checkout', 'platinum-esim'))->assertNotFound();

    // …and an inactive package disappears from the storefront even though it is listed.
    \App\Models\Package::where('name', 'Diamond eSIM')->update(['is_active' => false]);

    $this->get(route('product.index'))->assertOk()->assertDontSee('Diamond eSIM');
    $this->get(route('product.checkout', 'diamond-esim'))->assertNotFound();
});

/* ------------------------------------------------------------------ checkout */

test('a guest checkout creates a pending order and hands back a Paystack link', function () {
    $captured = null;
    fakeProductGateway(captured: $captured);

    $this->post(route('product.start', 'diamond-esim'), [
        'full_name' => '  Chinedu   Okeke  ',
        'phone' => '0803 123 4567',
        'email' => 'Chinedu@Example.com',
    ])->assertRedirect();

    $order = ProductOrder::query()->sole();

    // What the buyer typed, normalised — and nothing more (no account is created).
    expect($order->full_name)->toBe('Chinedu Okeke')
        ->and($order->email)->toBe('chinedu@example.com')
        ->and($order->phone)->toBe('0803 123 4567')
        ->and($order->product_slug)->toBe('diamond-esim')
        ->and($order->product_name)->toBe('Diamond eSIM')
        ->and($order->status)->toBe(ProductOrder::STATUS_PENDING)
        ->and($order->provider)->toBe('paystack')
        ->and((float) $order->amount_usd)->toBe(10.5)
        ->and((float) $order->amount_ngn)->toBe(15750.0)
        ->and($order->reference)->toStartWith('PRD-')
        ->and($order->checkout_url)->toBe('https://checkout.paystack.com/'.$order->reference)
        ->and($order->package_id)->not->toBeNull();

    expect(\App\Models\User::query()->count())->toBe(0)
        ->and(Transaction::query()->count())->toBe(0);

    // Paystack was asked in kobo, for our reference, with the callback pointing back at this flow.
    expect($captured['amount'] ?? null)->toBe(1575000)
        ->and($captured['currency'] ?? null)->toBe('NGN')
        ->and($captured['reference'] ?? null)->toBe($order->reference)
        ->and($captured['email'] ?? null)->toBe('chinedu@example.com')
        ->and($captured['callback_url'] ?? null)->toBe(route('product.callback', $order->reference));
});

test('a guest who pays for the more expensive eSIM is charged its own price', function () {
    $captured = null;
    fakeProductGateway(captured: $captured);

    $order = startProductOrder($this, 'royal-esim');

    expect((float) $order->amount_ngn)->toBe(26250.0)
        ->and($captured['amount'] ?? null)->toBe(2625000);
});

test('incomplete buyer details never reach the payment provider', function () {
    Http::fake();

    foreach ([
        ['full_name' => '', 'reason' => 'full_name'],
        ['full_name' => 'A', 'reason' => 'full_name'],
        ['phone' => 'not-a-phone', 'reason' => 'phone'],
        ['email' => 'not-an-email', 'reason' => 'email'],
    ] as $case) {
        $payload = array_merge([
            'full_name' => 'Chinedu Okeke',
            'phone' => '08031234567',
            'email' => 'chinedu@example.com',
        ], collect($case)->except('reason')->all());

        $this->post(route('product.start', 'diamond-esim'), $payload)
            ->assertSessionHasErrors($case['reason']);
    }

    expect(ProductOrder::query()->count())->toBe(0);

    Http::assertNothingSent();
});

test('a checkout that cannot get a payment link fails closed', function () {
    Http::fake(['*' => Http::response(['status' => false, 'message' => 'Invalid key'], 401)]);

    $this->post(route('product.start', 'diamond-esim'), [
        'full_name' => 'Chinedu Okeke',
        'phone' => '08031234567',
        'email' => 'chinedu@example.com',
    ])->assertRedirect();

    $order = ProductOrder::query()->sole();

    expect($order->status)->toBe(ProductOrder::STATUS_FAILED)
        ->and($order->checkout_url)->toBeNull();
});

/* ------------------------------------------------------------------ settlement */

test('a verified payment marks the order paid, claims the reference and tells both sides', function () {
    Mail::fake();
    fakeProductGateway();

    $order = startProductOrder($this);

    $this->get(route('product.callback', $order->reference))
        ->assertRedirect(route('product.success', $order->reference));

    $order->refresh();

    expect($order->status)->toBe(ProductOrder::STATUS_PAID)
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->confirmation_sent_at)->not->toBeNull()
        ->and($order->ops_notified_at)->not->toBeNull()
        ->and($order->awaitsFulfilment())->toBeTrue();

    // The money is claimed in the shared single-use ledger, against no account, exactly once.
    $entry = PaymentReference::query()->sole();

    expect($entry->reference)->toBe($order->reference)
        ->and($entry->provider)->toBe('paystack')
        ->and($entry->user_id)->toBeNull()
        ->and($entry->transaction_id)->toBeNull()
        ->and((float) $entry->amount_ngn)->toBe(15750.0)
        ->and($entry->type)->toBe('package_purchase');

    Mail::assertQueued(ProductOrderConfirmation::class, fn ($mail) => $mail->hasTo('chinedu@example.com') && $mail->order->reference === $order->reference);
    Mail::assertQueued(ProductOrderNotice::class, fn ($mail) => $mail->hasTo('ops@nextel.test'));
});

test('the receipt promises the product by email without publishing the address', function () {
    Mail::fake();
    fakeProductGateway();

    $order = startProductOrder($this);
    app(ProductOrderService::class)->settle($order->reference);

    $this->get(route('product.success', $order->reference))
        ->assertOk()
        ->assertSee('Payment successful')
        ->assertSee('Diamond eSIM')
        ->assertSee('will be sent to')
        ->assertSee($order->maskedEmail())
        ->assertSee($order->reference)
        ->assertSee('₦15,750.00')
        ->assertDontSee('chinedu@example.com'); // the full address is never rendered
});

test('a replayed settle can never credit or email twice', function () {
    Mail::fake();
    fakeProductGateway();

    $order = startProductOrder($this);
    $service = app(ProductOrderService::class);

    expect($service->settle($order->reference))->toBe('completed')
        ->and($service->settle($order->reference))->toBe('completed')
        ->and($service->settle($order->reference))->toBe('completed');

    expect(PaymentReference::query()->count())->toBe(1)
        ->and(ProductOrder::query()->where('status', ProductOrder::STATUS_PAID)->count())->toBe(1);

    expect(Mail::queued(ProductOrderConfirmation::class)->count())->toBe(1)
        ->and(Mail::queued(ProductOrderNotice::class)->count())->toBe(1);
});

test('an underpayment fails the order instead of delivering it', function () {
    Mail::fake();
    fakeProductGateway(fn (string $reference, float $expected) => ['amount' => (int) round(($expected - 100) * 100)]);

    $order = startProductOrder($this);

    expect(app(ProductOrderService::class)->settle($order->reference))->toBe('failed');

    $order->refresh();

    expect($order->status)->toBe(ProductOrder::STATUS_FAILED)
        ->and($order->meta['reconcile_error'] ?? null)->not->toBeNull()
        ->and(PaymentReference::query()->count())->toBe(0);

    Mail::assertNothingQueued();

    $this->get(route('product.success', $order->reference))
        ->assertOk()
        ->assertSee('Payment not completed');
});

test('a payment for another reference cannot settle this order', function () {
    Mail::fake();
    fakeProductGateway(fn () => ['reference' => 'PRD-SOMEONE-ELSE']);

    $order = startProductOrder($this);

    expect(app(ProductOrderService::class)->settle($order->reference))->toBe('failed');

    $order->refresh();

    expect($order->status)->toBe(ProductOrder::STATUS_FAILED)
        ->and(PaymentReference::query()->count())->toBe(0);

    Mail::assertNothingQueued();
});

test('a gateway that cannot be reached leaves the order pending, never failed', function () {
    Mail::fake();
    fakeProductGateway(fn () => ['http' => 502]);

    $order = startProductOrder($this);

    expect(app(ProductOrderService::class)->settle($order->reference))->toBe('unavailable');

    $order->refresh();

    expect($order->status)->toBe(ProductOrder::STATUS_PENDING)
        ->and(PaymentReference::query()->count())->toBe(0);

    // The receipt says we are still confirming — it never claims the payment failed.
    $this->get(route('product.success', $order->reference))
        ->assertOk()
        ->assertSee('Confirming your payment')
        ->assertSee('will be sent to');

    Mail::assertNothingQueued();
});

test('a payment the gateway reports as abandoned fails the order', function () {
    Mail::fake();
    fakeProductGateway(fn () => ['dataStatus' => 'abandoned']);

    $order = startProductOrder($this);

    expect(app(ProductOrderService::class)->settle($order->reference))->toBe('failed')
        ->and($order->refresh()->status)->toBe(ProductOrder::STATUS_FAILED)
        // The gateway adapter resolves Paystack's own `abandoned` data status into VerifyResult's
        // `failed`, so the reason records the status the service acted on — not the raw payload word.
        ->and($order->failure_reason)->toContain('The gateway reported the payment as');

    Mail::assertNothingQueued();
});

test('a checkout the gateway still calls open leaves the order pending for the sweep', function () {
    // Its own test on purpose: Http::fake() stacks, and a second registration inside one test would
    // keep answering with the first closure's provider state.
    Mail::fake();
    fakeProductGateway(fn () => ['dataStatus' => 'ongoing']);

    $order = startProductOrder($this, 'royal-esim');

    expect(app(ProductOrderService::class)->settle($order->reference))->toBe('pending')
        ->and($order->refresh()->status)->toBe(ProductOrder::STATUS_PENDING)
        ->and(PaymentReference::query()->count())->toBe(0);

    Mail::assertNothingQueued();
});

test('a payment already used by another order fails this one and flags it for review', function () {
    Mail::fake();
    fakeProductGateway();

    $order = startProductOrder($this);

    // The reference was consumed before this order tried to claim it.
    PaymentReference::create([
        'reference' => $order->reference,
        'provider' => 'paystack',
        'type' => 'package_purchase',
        'currency' => 'NGN',
        'amount_ngn' => 15750,
        'settled_at' => now(),
    ]);

    expect(app(ProductOrderService::class)->settle($order->reference))->toBe('failed');

    $order->refresh();

    expect($order->status)->toBe(ProductOrder::STATUS_FAILED)
        ->and($order->meta['manual_review']['reason'] ?? null)->toBe('duplicate_reference');

    Mail::assertNothingQueued();
});

test('the shared settlement router finds a guest order with no transaction row', function () {
    Mail::fake();
    fakeProductGateway();

    $order = startProductOrder($this);

    // The webhook and the requery sweep both go through ChargeSettlement; a guest order has no
    // Transactions row, so this is the path they take.
    expect(app(ChargeSettlement::class)->finalize($order->reference))->toBe('completed')
        ->and($order->refresh()->status)->toBe(ProductOrder::STATUS_PAID);
});

test('a signed Paystack webhook settles a guest order', function () {
    Mail::fake();
    fakeProductGateway();

    $order = startProductOrder($this);

    $body = json_encode([
        'event' => 'charge.success',
        'data' => ['reference' => $order->reference, 'status' => 'success', 'amount' => 1575000, 'currency' => 'NGN'],
    ]);

    $this->call('POST', route('webhooks.payments', ['provider' => 'paystack']), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X-Paystack-Signature' => hash_hmac('sha512', $body, 'sk_test_paystack_product'),
    ], $body)->assertOk();

    expect($order->refresh()->status)->toBe(ProductOrder::STATUS_PAID)
        ->and(PaymentReference::query()->count())->toBe(1);
});

test('an unsigned webhook settles nothing', function () {
    Mail::fake();
    fakeProductGateway();

    $order = startProductOrder($this);

    $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $order->reference, 'status' => 'success']]);

    $this->call('POST', route('webhooks.payments', ['provider' => 'paystack']), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], $body)->assertUnauthorized();

    expect($order->refresh()->status)->toBe(ProductOrder::STATUS_PENDING);
});

test('the receipt of an unknown reference is a 404', function () {
    $this->get(route('product.success', 'PRD-NOTHERE'))->assertNotFound();
    $this->get(route('product.callback', 'PRD-NOTHERE'))->assertNotFound();
});

/* ------------------------------------------------------------------ the emails */

test('all three emails render, with and without their optional parts', function () {
    // Mail::fake() never compiles a blade, so a broken mail view would only be discovered by a real
    // buyer (or by nobody). These renders compile every branch of every product email.
    $order = ProductOrder::create([
        'reference' => 'PRD-EMAILTEST01',
        'product_slug' => 'diamond-esim',
        'product_name' => 'Diamond eSIM',
        'full_name' => 'Chinedu Okeke',
        'phone' => '0803 123 4567',
        'email' => 'chinedu@example.com',
        'amount_usd' => 10.5,
        'amount_ngn' => 15750,
        'currency' => 'NGN',
        'provider' => 'paystack',
        'status' => ProductOrder::STATUS_PAID,
        'paid_at' => now(),
    ]);

    foreach ([
        new ProductOrderConfirmation($order),
        new ProductOrderNotice($order),
        new ProductDelivery($order, "Activation code: NXT-ABCD-1234", 'Welcome aboard.'),
        new ProductDelivery($order, "Activation code: NXT-ABCD-1234"),   // no note
    ] as $mailable) {
        expect($mailable->render())->toContain('Diamond eSIM');
    }

    // …and once more with the support address blanked out, which is the branch that used to be glued
    // to the line before it and would have thrown in production.
    config(['settings.contact.email' => null]);

    foreach ([
        new ProductOrderConfirmation($order),
        new ProductDelivery($order, 'Activation code: NXT-ABCD-1234'),
    ] as $mailable) {
        expect($mailable->render())->toContain('Diamond eSIM');
    }

    // An order with no name on it (the @if($name) branch falls away) still renders — the storefront
    // requires a name, but a delivered order must never blow up over one.
    $order->forceFill(['full_name' => ''])->save();

    expect((new ProductDelivery($order->refresh(), 'Activation code: NXT-ABCD-1234'))->render())
        ->toContain('Activation code');
});

/* ------------------------------------------------------------------ backoffice */

test('guests cannot reach the product-order pages', function () {
    foreach (['backoffice.products.orders', 'backoffice.products.orders.show'] as $route) {
        $parameters = $route === 'backoffice.products.orders.show' ? ['order' => 'nope'] : [];

        $this->get(route($route, $parameters))->assertRedirect(route('backoffice.login'));
    }
});

test('the order list shows the paid order, the money and the fulfilment queue', function () {
    Mail::fake();
    fakeProductGateway();

    $order = startProductOrder($this);
    app(ProductOrderService::class)->settle($order->reference);

    $this->actingAs(Admin::factory()->super()->create(), 'admin')
        ->get(route('backoffice.products.orders'))
        ->assertOk()
        ->assertSee('Product orders')
        ->assertSee($order->reference)
        ->assertSee('Chinedu Okeke')
        ->assertSee('chinedu@example.com')
        ->assertSee('Diamond eSIM')
        ->assertSee('₦15,750.00')
        ->assertSee('not sent yet')
        ->assertSee('Send');

    Livewire::test(Orders::class)
        ->assertSee($order->reference)
        ->set('queueFilter', 'to_send')
        ->assertSee($order->reference)
        ->set('search', '0803')
        ->assertSee($order->reference);
});

test('the fulfilment queue filters hide orders that are not waiting to be sent', function () {
    Mail::fake();
    fakeProductGateway();

    $paid = startProductOrder($this);
    app(ProductOrderService::class)->settle($paid->reference);

    $unpaid = startProductOrder($this, 'royal-esim', ['email' => 'other@example.com']);

    $this->actingAs(Admin::factory()->regular()->create(), 'admin');

    Livewire::test(Orders::class)->assertSee($paid->reference)->assertSee($unpaid->reference);

    Livewire::test(Orders::class)
        ->set('queueFilter', 'to_send')
        ->assertSee($paid->reference)
        ->assertDontSee($unpaid->reference);

    Livewire::test(Orders::class)
        ->set('statusFilter', 'pending')
        ->assertSee($unpaid->reference)
        ->assertDontSee($paid->reference);
});

test('a staff member sends the product and the order leaves the queue', function () {
    Mail::fake();
    fakeProductGateway();

    $order = startProductOrder($this);
    app(ProductOrderService::class)->settle($order->reference);

    $this->actingAs(Admin::factory()->regular()->create(), 'admin');

    Livewire::test(OrderShow::class, ['order' => $order->id])
        ->assertSee('Send the product')
        ->set('deliveryDetails', "Activation code: NXT-ABCD-1234\nSign up with this email to activate.")
        ->set('deliveryNote', 'Welcome aboard.')
        ->call('send')
        ->assertHasNoErrors();

    $order->refresh();

    expect($order->isFulfilled())->toBeTrue()
        ->and($order->fulfilment_note)->toBe('Welcome aboard.');

    // The details go to the buyer's inbox, not into this database.
    Mail::assertQueued(ProductDelivery::class, fn ($mail) => $mail->hasTo('chinedu@example.com') && str_contains($mail->details, 'NXT-ABCD-1234'));

    expect(json_encode($order->getAttributes()))->not->toContain('NXT-ABCD-1234');
});

test('a support admin can see the queue but cannot send anything', function () {
    Mail::fake();
    fakeProductGateway();

    $order = startProductOrder($this);
    app(ProductOrderService::class)->settle($order->reference);

    $this->actingAs(Admin::factory()->create(['role' => AdminRole::SUPPORT]), 'admin');

    Livewire::test(OrderShow::class, ['order' => $order->id])
        ->assertDontSee('Send to the buyer');

    // The action itself is guarded too (Livewire updates don't replay route middleware). However the
    // refusal surfaces (thrown or rendered), the delivery must not have happened.
    try {
        Livewire::test(OrderShow::class, ['order' => $order->id])
            ->set('deliveryDetails', 'Activation code: NXT-HACK-0000')
            ->call('send');
    } catch (\Throwable) {
        // refused
    }

    expect($order->refresh()->isFulfilled())->toBeFalse();

    Mail::assertNotQueued(ProductDelivery::class);
});

test('an unpaid order cannot be fulfilled', function () {
    Mail::fake();
    fakeProductGateway();

    $order = startProductOrder($this);

    $this->actingAs(Admin::factory()->super()->create(), 'admin');

    Livewire::test(OrderShow::class, ['order' => $order->id])
        ->assertSee('has not been paid')
        ->assertDontSee('Send to the buyer');

    $result = app(ProductOrderService::class)->deliver($order, 'Activation code: NXT-NOPE-0000');

    expect($result['ok'])->toBeFalse()
        ->and($order->refresh()->isFulfilled())->toBeFalse();

    Mail::assertNothingQueued();
});

test('the order page shows the money trail a disputed sale needs', function () {
    Mail::fake();
    fakeProductGateway();

    $order = startProductOrder($this);
    app(ProductOrderService::class)->settle($order->reference);

    $this->actingAs(Admin::factory()->super()->create(), 'admin')
        ->get(route('backoffice.products.orders.show', ['order' => $order->id]))
        ->assertOk()
        ->assertSee($order->reference)
        ->assertSee('Payment trail')
        ->assertSee('Settled')          // the ledger row exists
        ->assertSee('chinedu@example.com')
        ->assertSee('0803 123 4567')
        ->assertSee('₦15,750.00');
});

test('the product-order page 404s for an unknown order', function () {
    $this->actingAs(Admin::factory()->super()->create(), 'admin')
        ->get(route('backoffice.products.orders.show', ['order' => 'does-not-exist']))
        ->assertNotFound();
});
