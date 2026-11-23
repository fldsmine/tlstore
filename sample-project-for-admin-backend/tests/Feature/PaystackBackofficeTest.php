<?php

use App\Enums\PaymentProvider;
use App\Enums\TransactionType;
use App\Livewire\Backoffice\Korapay\ListPaymentReferences;
use App\Livewire\Backoffice\Paystack\Balance;
use App\Livewire\Backoffice\Paystack\BalanceHistory;
use App\Livewire\Backoffice\Paystack\SendMoney;
use App\Livewire\Backoffice\Paystack\VerifyPayout;
use App\Livewire\Backoffice\Settings\ServicesSetting;
use App\Models\Admin;
use App\Models\Bank;
use App\Models\Currency;
use App\Models\FlutterwavePayout;
use App\Models\KorapayPayout;
use App\Models\PaystackPayout;
use App\Models\PaymentReference;
use App\Services\Payments\DTO\PayoutRequest;
use App\Services\Payments\FlutterwaveMerchantService;
use App\Services\Payments\KorapayMerchantService;
use App\Services\Payments\MerchantWalletService;
use App\Services\Payments\PaystackMerchantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Currency::create(['code' => 'NGN', 'name' => 'Nigerian Naira', 'symbol' => '₦', 'usd_exchange_rate' => 1500, 'is_active' => true]);

    Bank::create(['name' => 'Guaranty Trust Bank', 'code' => '058', 'country' => 'NG', 'type' => 'commercial', 'is_active' => true]);
    Bank::create(['name' => 'Access Bank', 'code' => '044', 'country' => 'NG', 'type' => 'commercial', 'is_active' => true]);

    config([
        'services.paystack.base_url' => 'https://api.paystack.co',
        'services.paystack.secret_key' => 'sk_test_paystack_abc123',
        'services.paystack.currency' => 'NGN',
        'services.paystack.payout_currencies' => ['NGN'],
        'services.paystack.payout_max_amount' => 1000000,
        'settings.payment.active_manager' => 'paystack',
    ]);
});

function paystackAdmin(): Admin
{
    return Admin::factory()->super()->create();
}

/**
 * Fake every Paystack endpoint the backoffice touches with ONE stateful closure — Laravel evaluates
 * stacked Http::fake callbacks together, so a second fake() would silently lose branches.
 *
 * Amounts are the ones Paystack really sends: integers in subunits (kobo).
 */
function fakePaystackMerchant(array $overrides = []): void
{
    Http::fake(function (ClientRequest $request) use ($overrides) {
        $url = $request->url();
        $body = $request->data();

        if (str_contains($url, '/balance/ledger')) {
            if (($overrides['ledger'] ?? null) === 'error') {
                return Http::response(['status' => false, 'message' => 'Invalid key'], 401);
            }

            $rows = $overrides['ledgerRows'] ?? [
                [
                    'integration' => 463433, 'domain' => 'test', 'balance' => 2078224968, 'currency' => 'NGN',
                    'difference' => -50000, 'reason' => 'Transfer to John', 'model_responsible' => 'Transfer',
                    'model_row' => 56610600, 'id' => 149411613, 'createdAt' => '2026-10-12T09:39:49.000Z',
                ],
                [
                    'integration' => 463433, 'domain' => 'test', 'balance' => 2078274968, 'currency' => 'NGN',
                    'difference' => 10000, 'reason' => '', 'model_responsible' => 'Transaction',
                    'model_row' => 1073891448, 'id' => 149314482, 'createdAt' => '2026-10-11T00:00:11.000Z',
                ],
            ];

            return Http::response([
                'status' => true,
                'message' => 'Balance ledger retrieved',
                'data' => $rows,
                'meta' => ['total' => 2, 'skipped' => 0, 'perPage' => (int) ($body['perPage'] ?? 50), 'page' => (int) ($body['page'] ?? 1), 'pageCount' => (int) ($overrides['ledgerPages'] ?? 1)],
            ]);
        }

        if (str_contains($url, '/balance')) {
            if (($overrides['balances'] ?? null) === 'error') {
                return Http::response(['status' => false, 'message' => 'Invalid key'], 401);
            }

            return Http::response([
                'status' => true,
                'message' => 'Balances retrieved',
                'data' => $overrides['balances'] ?? [
                    ['currency' => 'USD', 'balance' => 50000],
                    ['currency' => 'NGN', 'balance' => 1700000],
                ],
            ]);
        }

        if (str_contains($url, '/bank/resolve')) {
            if (($overrides['resolve'] ?? null) === 'error') {
                return Http::response(['status' => false, 'message' => 'Could not resolve account'], 422);
            }

            return Http::response([
                'status' => true,
                'message' => 'Account number resolved',
                'data' => ['account_number' => '0123456789', 'account_name' => 'JOHN OKAFOR'],
            ]);
        }

        if (str_contains($url, '/transferrecipient')) {
            if (($overrides['recipient'] ?? null) === 'error') {
                return Http::response(['status' => false, 'message' => 'Recipient is invalid'], 400);
            }

            return Http::response([
                'status' => true,
                'message' => 'Transfer recipient created successfully',
                'data' => ['recipient_code' => 'RCP_test123456', 'name' => 'JOHN OKAFOR', 'type' => 'nuban', 'currency' => 'NGN'],
            ]);
        }

        // Verify first: /transfer/verify/{reference} also contains "/transfer".
        if (str_contains($url, '/transfer/verify/') || (preg_match('#/transfer/(?!verify)#', $url) === 1)) {
            if (($overrides['verify'] ?? null) === '404') {
                return Http::response(['status' => false, 'message' => 'Transfer not found'], 404);
            }

            if (($overrides['verify'] ?? null) === 'server-error') {
                return Http::response(['status' => false, 'message' => 'Server error'], 502);
            }

            $status = $overrides['verifyStatus'] ?? 'success';

            return Http::response([
                'status' => true,
                'message' => 'Transfer retrieved',
                'data' => array_filter([
                    'reference' => $overrides['verifyReference'] ?? 'psk-abc123def456',
                    'amount' => $overrides['verifyAmount'] ?? 1500000,
                    'currency' => 'NGN',
                    'status' => $status,
                    'transfer_code' => 'TRF_verify123',
                    'fee_charged' => 1000,
                    'reason' => 'Payout from Nextel',
                ], fn ($value): bool => $value !== null),
            ]);
        }

        if (str_contains($url, '/transfer')) {
            if (($overrides['transfer'] ?? null) === 'transport-error') {
                throw new ConnectionException('cURL error 28: Operation timed out');
            }

            if (($overrides['transfer'] ?? null) === 'reject') {
                return Http::response(['status' => false, 'message' => 'Insufficient balance'], 400);
            }

            if (($overrides['transfer'] ?? null) === 'server-error') {
                return Http::response(['status' => false, 'message' => 'Server error'], 502);
            }

            $status = ($overrides['transfer'] ?? null) === 'otp' ? 'otp' : 'success';

            return Http::response([
                'status' => true,
                'message' => $status === 'otp' ? 'Transfer requires OTP' : 'Transfer has been queued',
                'data' => [
                    'reference' => $body['reference'] ?? null,
                    'amount' => $body['amount'] ?? null,
                    'currency' => $body['currency'] ?? 'NGN',
                    'status' => $status,
                    'transfer_code' => 'TRF_queued123',
                    'fee_charged' => 0,
                ],
            ]);
        }

        return Http::response(['status' => false, 'message' => 'unexpected call to '.$url], 500);
    });
}

/** Fill in the payout form the way an admin would, without confirming. */
function paystackPayoutForm(string $amount = '15000'): Testable
{
    return Livewire::test(SendMoney::class)
        ->set('amount', $amount)
        ->set('bankCode', '044')
        ->set('accountNumber', '0123456789')
        ->set('accountName', 'JOHN OKAFOR')
        ->set('narration', 'Payout from Nextel');
}

/* ------------------------------------------------------------------ services */

test('all three merchant services implement the shared merchant wallet contract', function () {
    foreach ([PaystackMerchantService::class, KorapayMerchantService::class, FlutterwaveMerchantService::class] as $service) {
        expect(app($service))->toBeInstanceOf(MerchantWalletService::class);
    }

    expect(app(PaystackMerchantService::class)->name())->toBe('paystack')
        ->and(app(PaystackMerchantService::class)->isTestMode())->toBeTrue()
        ->and(app(PaystackMerchantService::class)->payoutCurrencies())->toBe(['NGN'])
        ->and(app(PaystackMerchantService::class)->maxPayoutAmount())->toBe(1000000.0);
});

test('the balance API reports subunits as major units, payout currency first', function () {
    fakePaystackMerchant();

    $result = app(PaystackMerchantService::class)->balances();

    expect($result->ok)->toBeTrue()
        ->and($result->data)->toHaveCount(2)
        ->and($result->data[0]['currency'])->toBe('NGN')
        ->and($result->data[0]['available'])->toBe(17000.0)
        ->and($result->data[1]['currency'])->toBe('USD')
        ->and($result->data[1]['available'])->toBe(500.0);
});

test('an API error keeps Paystack\'s own message and never invents figures', function () {
    fakePaystackMerchant(['balances' => 'error']);

    $result = app(PaystackMerchantService::class)->balances();

    expect($result->ok)->toBeFalse()
        ->and($result->data)->toBe([])
        ->and($result->message)->toBe('Invalid key');
});

test('the balance ledger normalises the signed difference into amount and direction', function () {
    fakePaystackMerchant();

    $history = app(PaystackMerchantService::class)->balanceHistory();

    expect($history['ok'])->toBeTrue()
        ->and($history['entries'])->toHaveCount(2)
        // A negative difference is money OUT: the amount is positive, the direction says debit.
        ->and($history['entries'][0]['direction'])->toBe('debit')
        ->and($history['entries'][0]['amount'])->toBe(500.0)
        ->and($history['entries'][0]['balance_after'])->toBe(20782249.68)
        ->and($history['entries'][0]['balance_before'])->toBe(20782749.68)
        ->and($history['entries'][0]['description'])->toBe('Transfer to John')
        ->and($history['entries'][0]['source'])->toBe('Transfer')
        ->and($history['entries'][1]['direction'])->toBe('credit')
        ->and($history['entries'][1]['amount'])->toBe(100.0);
});

test('the ledger asks for the requested page size and reports more pages', function () {
    fakePaystackMerchant(['ledgerPages' => 3]);

    $history = app(PaystackMerchantService::class)->balanceHistory(['limit' => 10]);

    expect($history['ok'])->toBeTrue()
        ->and($history['has_more'])->toBeTrue();

    Http::assertSent(fn (ClientRequest $request): bool => str_contains($request->url(), '/balance/ledger')
        && str_contains($request->url(), 'perPage=10')
        && str_contains($request->url(), 'page=1'));
});

test('ledger filters are enforced even if the endpoint ignores them', function () {
    fakePaystackMerchant();

    $service = app(PaystackMerchantService::class);

    // The endpoint was never asked to filter, yet the page may not show rows that do not match.
    $debits = $service->balanceHistory(['direction' => 'debit']);
    expect($debits['entries'])->toHaveCount(1)
        ->and($debits['entries'][0]['direction'])->toBe('debit');

    $credits = $service->balanceHistory(['direction' => 'credit']);
    expect($credits['entries'])->toHaveCount(1)
        ->and($credits['entries'][0]['direction'])->toBe('credit');

    $none = $service->balanceHistory(['currency' => 'GHS']);
    expect($none['entries'])->toBe([]);
});

test('our references are mapped onto ones Paystack accepts', function () {
    $service = app(PaystackMerchantService::class);

    $wire = $service->wireReference('PSK-ABC123DEF456');

    expect($wire)->toBe('psk-abc123def456')
        ->and($wire)->toHaveLength(16)
        ->and($wire)->toMatch('/^[a-z0-9\-_]{16,50}$/')
        // Deterministic: verifying with our reference rebuilds exactly this string.
        ->and($service->wireReference('PSK-ABC123DEF456'))->toBe($wire);

    // A short or unkind reference is padded/sanitised into the same legal shape.
    $short = $service->wireReference('PAY 1');

    expect($short)->toHaveLength(16)
        ->and($short)->toMatch('/^[a-z0-9\-_]+$/')
        ->and($service->wireReference('PAY 1'))->toBe($short)
        // …without colliding with a different short reference.
        ->and($service->wireReference('PAY 2'))->not->toBe($short);
});

test('a payout creates the transfer recipient and only then transfers, in subunits', function () {
    fakePaystackMerchant();

    $result = app(PaystackMerchantService::class)->disburse(new PayoutRequest(
        reference: 'PSK-ABC123DEF456',
        destinationType: 'bank_account',
        amount: 15000.0,
        currency: 'NGN',
        narration: 'Payout from Nextel',
        accountNumber: '0123456789',
        bankCode: '044',
        accountName: 'JOHN OKAFOR',
    ));

    // Asserting the message too: if this ever fails, the failure output shows what Paystack (or the
    // transport) actually said instead of a bare "false is not true".
    expect(['ok' => $result->ok, 'said' => $result->message])->toBe(['ok' => true, 'said' => 'Transfer has been queued'])
        ->and($result->gatewayId)->toBe('TRF_queued123')
        ->and($result->reference)->toBe('psk-abc123def456')
        // Paystack says "success" the moment it queues — that is NOT a completed transfer.
        ->and($result->status)->toBe('processing');

    Http::assertSent(fn (ClientRequest $request): bool => str_contains($request->url(), '/transferrecipient')
        && $request->data()['account_number'] === '0123456789'
        && $request->data()['bank_code'] === '044'
        && $request->data()['type'] === 'nuban'
        && $request->data()['name'] === 'JOHN OKAFOR');

    Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/transfer')
        && $request->data()['recipient'] === 'RCP_test123456'
        && $request->data()['amount'] === 1500000   // kobo
        && $request->data()['reference'] === 'psk-abc123def456'
        && $request->data()['source'] === 'balance');
});

test('a rejected recipient is definitive and no transfer is attempted', function () {
    fakePaystackMerchant(['recipient' => 'error']);

    $result = app(PaystackMerchantService::class)->disburse(new PayoutRequest(
        reference: 'PSK-ABC123DEF456',
        destinationType: 'bank_account',
        amount: 15000.0,
        currency: 'NGN',
        narration: 'Payout from Nextel',
        accountNumber: '0123456789',
        bankCode: '044',
    ));

    expect($result->ok)->toBeTrue()
        ->and($result->status)->toBe('failed')
        ->and($result->message)->toBe('Recipient is invalid');

    Http::assertNotSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/transfer'));
});

test('a payout Paystack holds for OTP is pending, never success', function () {
    fakePaystackMerchant(['transfer' => 'otp']);

    $result = app(PaystackMerchantService::class)->disburse(new PayoutRequest(
        reference: 'PSK-ABC123DEF456',
        destinationType: 'bank_account',
        amount: 15000.0,
        currency: 'NGN',
        narration: 'Payout from Nextel',
        accountNumber: '0123456789',
        bankCode: '044',
    ));

    expect($result->status)->toBe('pending')
        ->and($result->completed())->toBeFalse()
        ->and($result->inFlight())->toBeTrue()
        ->and($result->message)->toContain('OTP');
});

test('a transport error leaves the payout unverified instead of marking it failed', function () {
    // The recipient is created, then the transfer itself times out — the money may be on its way.
    fakePaystackMerchant(['transfer' => 'transport-error']);

    $result = app(PaystackMerchantService::class)->disburse(new PayoutRequest(
        reference: 'PSK-ABC123DEF456',
        destinationType: 'bank_account',
        amount: 15000.0,
        currency: 'NGN',
        narration: 'Payout from Nextel',
        accountNumber: '0123456789',
        bankCode: '044',
    ));

    expect($result->ok)->toBeFalse()
        ->and($result->status)->toBe('unknown')
        ->and($result->completed())->toBeFalse()
        ->and($result->failed())->toBeFalse();
});

test('a 5xx answer is unknown, not a failed payout', function () {
    fakePaystackMerchant(['transfer' => 'server-error']);

    $result = app(PaystackMerchantService::class)->disburse(new PayoutRequest(
        reference: 'PSK-ABC123DEF456',
        destinationType: 'bank_account',
        amount: 15000.0,
        currency: 'NGN',
        narration: 'Payout from Nextel',
        accountNumber: '0123456789',
        bankCode: '044',
    ));

    expect($result->ok)->toBeFalse()
        ->and($result->status)->toBe('unknown');
});

test('an amount above the ceiling never reaches the API', function () {
    fakePaystackMerchant();

    $result = app(PaystackMerchantService::class)->disburse(new PayoutRequest(
        reference: 'PSK-ABC123DEF456',
        destinationType: 'bank_account',
        amount: 1000001.0,
        currency: 'NGN',
        narration: 'Payout from Nextel',
        accountNumber: '0123456789',
        bankCode: '044',
    ));

    expect($result->status)->toBe('failed')
        ->and($result->message)->toContain('ceiling');

    Http::assertNothingSent();
});

test('verifying by reference asks Paystack for our reference in its own format', function () {
    fakePaystackMerchant(['verifyReference' => 'psk-abc123def456']);

    $result = app(PaystackMerchantService::class)->verifyPayout('PSK-ABC123DEF456');

    expect(['ok' => $result->ok, 'said' => $result->message])->toBe(['ok' => true, 'said' => 'Transfer retrieved'])
        ->and($result->status)->toBe('success')
        ->and($result->completed())->toBeTrue()
        // Paystack quotes subunits on every endpoint, including this one.
        ->and($result->amount)->toBe(15000.0)
        ->and($result->fee)->toBe(10.0)
        ->and($result->gatewayId)->toBe('TRF_verify123');

    Http::assertSent(fn (ClientRequest $request): bool => str_contains($request->url(), '/transfer/verify/psk-abc123def456'));
});

test('a Paystack transfer code is looked up directly', function () {
    fakePaystackMerchant();

    $result = app(PaystackMerchantService::class)->verifyPayout('TRF_fpmd0l8uta8upow7');

    expect($result->ok)->toBeTrue()
        ->and($result->status)->toBe('success');

    Http::assertSent(fn (ClientRequest $request): bool => str_contains($request->url(), '/transfer/TRF_fpmd0l8uta8upow7'));
});

test('an unknown reference is a definitive answer, never a fake success', function () {
    fakePaystackMerchant(['verify' => '404']);

    $result = app(PaystackMerchantService::class)->verifyPayout('PSK-NOSUCHREFERENCE');

    expect($result->ok)->toBeTrue()
        ->and($result->status)->toBe('failed')
        ->and($result->completed())->toBeFalse();
});

test('a verification Paystack cannot answer stays unknown', function () {
    fakePaystackMerchant(['verify' => 'server-error']);

    $result = app(PaystackMerchantService::class)->verifyPayout('PSK-ABC123DEF456');

    expect($result->ok)->toBeFalse()
        ->and($result->status)->toBe('unknown');
});

test('resolving an account returns the name Paystack holds for it', function () {
    fakePaystackMerchant();

    $result = app(PaystackMerchantService::class)->resolveBankAccount('058', '0123456789');

    expect($result['ok'])->toBeTrue()
        ->and($result['account_name'])->toBe('JOHN OKAFOR');
});

/* ------------------------------------------------------------------ pages */

test('guests cannot reach any Paystack backoffice page', function () {
    foreach ([
        'backoffice.paystack.balance',
        'backoffice.paystack.history',
        'backoffice.paystack.verify',
        'backoffice.paystack.send-money',
    ] as $route) {
        $this->get(route($route))->assertRedirect(route('backoffice.login'));
    }
});

test('the balance page shows the Paystack balance', function () {
    fakePaystackMerchant();

    $this->actingAs(paystackAdmin(), 'admin')
        ->get(route('backoffice.paystack.balance'))
        ->assertOk()
        ->assertSee('Paystack balance')
        ->assertSee('17,000.00')
        ->assertSee('500.00')
        ->assertSee('Test mode');

    Livewire::test(Balance::class)
        ->assertSet('error', null)
        ->assertSee('NGN');
});

test('the balance page degrades gracefully when Paystack rejects the request', function () {
    fakePaystackMerchant(['balances' => 'error']);

    $this->actingAs(paystackAdmin(), 'admin')
        ->get(route('backoffice.paystack.balance'))
        ->assertOk()
        ->assertSee('Invalid key');

    Livewire::test(Balance::class)->assertSet('error', 'Invalid key');
});

test('the balance page refuses to call the API when the secret key is missing', function () {
    config(['services.paystack.secret_key' => '']);
    Http::fake();

    $this->actingAs(paystackAdmin(), 'admin');

    Livewire::test(Balance::class)
        ->assertSet('balances', [])
        ->assertSee('secret key not set');

    Http::assertNothingSent();
});

test('the history page lists the balance movements with their direction', function () {
    fakePaystackMerchant();

    $this->actingAs(paystackAdmin(), 'admin')
        ->get(route('backoffice.paystack.history'))
        ->assertOk()
        ->assertSee('Paystack balance history')
        ->assertSee('Transfer to John')
        ->assertSee('500.00')   // the NGN 500 debit, in major units
        ->assertSee('100.00');  // and the NGN 100 credit

    Livewire::test(BalanceHistory::class)->assertSet('error', null);
});

test('the history page offers a page size, which the ledger endpoint accepts', function () {
    fakePaystackMerchant();

    $this->actingAs(paystackAdmin(), 'admin')
        ->get(route('backoffice.paystack.history'))
        ->assertOk()
        ->assertSee('Per page');
});

test('a bad date filter is rejected before the API is called', function () {
    fakePaystackMerchant();

    $this->actingAs(paystackAdmin(), 'admin');

    Livewire::test(BalanceHistory::class)
        ->set('dateFrom', '2026-10-10')
        ->set('dateTo', '2026-10-01')
        ->call('applyFilters')
        ->assertHasErrors('dateTo');
});

test('a ?reference= link opens the verify page and checks the payout straight away', function () {
    fakePaystackMerchant();

    PaystackPayout::create([
        'reference' => 'PSK-VERIFYOK1',
        'amount' => 15000,
        'currency' => 'NGN',
        'bank_code' => '044',
        'account_number' => '0123456789',
        'account_name' => 'JOHN OKAFOR',
        'narration' => 'Payout from Nextel',
        'status' => 'processing',
        'admin_id' => paystackAdmin()->id,
        'initiated_at' => now(),
    ]);

    $this->actingAs(paystackAdmin(), 'admin')
        ->get(route('backoffice.paystack.verify', ['reference' => 'PSK-VERIFYOK1']))
        ->assertOk()
        ->assertSee('PSK-VERIFYOK1')
        ->assertSee('Verification result');

    // Opening the link is enough: the payout was checked and the audit row now says so.
    expect(PaystackPayout::sole()->status)->toBe('success');
});

test('the verify page renders and prefills from the query string', function () {
    fakePaystackMerchant();

    $this->actingAs(paystackAdmin(), 'admin')
        ->get(route('backoffice.paystack.verify'))
        ->assertOk()
        ->assertSee('Verify Paystack payout')
        ->assertSee('PSK-… or a Paystack TRF_… code');

    Livewire::test(VerifyPayout::class, ['reference' => 'PSK-VERIFYOK1'])
        ->assertSet('reference', 'PSK-VERIFYOK1')
        ->assertSet('result.status', 'success')
        // Paystack answers with the reference it was sent — ours, in its own lowercase format.
        ->assertSet('result.reference', 'psk-abc123def456')
        ->assertSee('Transfer code');
});

/* ------------------------------------------------------------------ send money */

test('send money is super-admin only at the route level', function () {
    fakePaystackMerchant();

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('backoffice.paystack.send-money'))
        ->assertForbidden();
});

test('a regular admin cannot use the send money component either', function () {
    fakePaystackMerchant();

    $this->actingAs(Admin::factory()->create(), 'admin');

    // The mount guard aborts; Livewire renders the error page instead of the payout form.
    expect(Livewire::test(SendMoney::class)->html())->not->toContain('Review payout');
});

test('a payout reference is generated with the PSK prefix', function () {
    $this->actingAs(paystackAdmin(), 'admin');

    $reference = Livewire::test(SendMoney::class)->get('reference');

    expect($reference)->toStartWith('PSK-')
        ->and(PaystackPayout::generateReference())->toStartWith('PSK-');
});

test('resolving an account fills in the verified name', function () {
    fakePaystackMerchant();

    $this->actingAs(paystackAdmin(), 'admin');

    Livewire::test(SendMoney::class)
        ->set('currency', 'NGN')
        ->set('bankCode', '044')
        ->set('accountNumber', '0123456789')
        ->call('resolveAccount')
        ->assertSet('accountName', 'JOHN OKAFOR')
        ->assertSet('accountResolved', true);
});

test('the payout form cannot be submitted with an invalid amount', function () {
    fakePaystackMerchant();

    $this->actingAs(paystackAdmin(), 'admin');

    paystackPayoutForm('0')
        ->call('confirm')
        ->assertHasErrors('amount')
        ->assertSet('showConfirm', false);

    Http::assertNothingSent();
});

test('the form shows the active manager\'s own ceiling', function () {
    config(['services.paystack.payout_max_amount' => 250000, 'services.korapay.payout_max_amount' => 1000000]);
    fakePaystackMerchant();

    $this->actingAs(paystackAdmin(), 'admin')
        ->get(route('backoffice.paystack.send-money'))
        ->assertOk()
        ->assertSee('250,000.00');
});

test('a currency outside the configured payout list is rejected before any call', function () {
    fakePaystackMerchant();

    $this->actingAs(paystackAdmin(), 'admin');

    paystackPayoutForm()->set('currency', 'KES')->call('confirm')->assertHasErrors('currency');

    Http::assertNothingSent();
});

test('a sent payout is recorded with its recipient, transfer code and Paystack\'s own words', function () {
    fakePaystackMerchant();

    $admin = paystackAdmin();
    $this->actingAs($admin, 'admin');

    paystackPayoutForm()->call('confirm')->assertSet('showConfirm', true)->call('send');

    $payout = PaystackPayout::query()->firstOrFail();

    expect($payout->status)->toBe('processing')
        ->and($payout->provider)->toBe('paystack')
        ->and($payout->gateway_reference)->toBe('TRF_queued123')
        ->and($payout->recipient_code)->toBe('RCP_test123456')
        ->and($payout->message)->toBe('Transfer has been queued')
        ->and($payout->bank_name)->toBe('Access Bank')
        ->and((float) $payout->amount)->toBe(15000.0)
        ->and($payout->admin_id)->toBe($admin->id)
        ->and($payout->initiated_at)->not->toBeNull()
        // The reference on the row is OURS, so the verify page can look it up by reference.
        ->and($payout->reference)->toStartWith('PSK-');
});

test('a definitive rejection is stored as failed with Paystack\'s message', function () {
    fakePaystackMerchant(['transfer' => 'reject']);

    $this->actingAs(paystackAdmin(), 'admin');

    paystackPayoutForm()->call('send');

    $payout = PaystackPayout::query()->firstOrFail();

    expect($payout->status)->toBe('failed')
        ->and($payout->error)->toBe('Insufficient balance');
});

test('an unanswered payout is stored unverified, with the reference kept for checking', function () {
    fakePaystackMerchant(['transfer' => 'server-error']);

    $this->actingAs(paystackAdmin(), 'admin');

    paystackPayoutForm()->call('send');

    $payout = PaystackPayout::query()->firstOrFail();

    expect($payout->status)->toBe('unknown')
        ->and($payout->reference)->toStartWith('PSK-');
});

test('an identical in-flight payout is blocked instead of sent twice', function () {
    fakePaystackMerchant();

    $this->actingAs(paystackAdmin(), 'admin');

    paystackPayoutForm()->call('confirm')->call('send');
    paystackPayoutForm()->call('confirm')->call('send');

    expect(PaystackPayout::query()->count())->toBe(1);

    // One payout = exactly two calls (the recipient, then the transfer); the second attempt is
    // answered by looking at the payout still in flight, so it never reaches Paystack.
    Http::assertSentCount(2);
});

test('a payout Paystack holds for OTP is stored as pending, not as sent', function () {
    fakePaystackMerchant(['transfer' => 'otp']);

    $this->actingAs(paystackAdmin(), 'admin');

    paystackPayoutForm()->call('send');

    $payout = PaystackPayout::query()->firstOrFail();

    expect($payout->status)->toBe('pending')
        ->and($payout->isFinal())->toBeFalse();
});

/* ------------------------------------------------------------------ verify payout */

test('verifying a completed transfer reconciles the local audit row', function () {
    fakePaystackMerchant();

    $this->actingAs(paystackAdmin(), 'admin');

    paystackPayoutForm()->call('send');

    $payout = PaystackPayout::query()->firstOrFail();

    Livewire::test(VerifyPayout::class)
        ->set('reference', $payout->reference)
        ->call('verify')
        ->assertSet('result.status', 'success')
        ->assertSet('localPayoutId', $payout->id);

    $payout->refresh();

    expect($payout->status)->toBe('success')
        ->and((float) $payout->fee)->toBe(10.0)
        ->and($payout->verified_at)->not->toBeNull()
        ->and($payout->last_verified_at)->not->toBeNull();
});

test('a transfer Paystack is still holding is reported as pending, not success', function () {
    fakePaystackMerchant(['verifyStatus' => 'otp']);

    $this->actingAs(paystackAdmin(), 'admin');

    Livewire::test(VerifyPayout::class)
        ->set('reference', 'PSK-ABC123DEF456')
        ->call('verify')
        ->assertSet('result.status', 'pending')
        // The badge spells out what "pending" means here — Paystack is holding it until its OTP.
        ->assertSee('Pending');
});

test('an unknown reference is never reported as successful', function () {
    fakePaystackMerchant(['verify' => '404']);

    $this->actingAs(paystackAdmin(), 'admin');

    Livewire::test(VerifyPayout::class)
        ->set('reference', 'PSK-NOSUCHREFERENCE')
        ->call('verify')
        ->assertSet('result.status', 'failed')
        ->assertSet('localPayoutId', null);
});

test('the recent payouts list deep-links a reference into the verify page', function () {
    fakePaystackMerchant();

    $this->actingAs(paystackAdmin(), 'admin');

    $reference = paystackPayoutForm()->call('send')->get('lastReference');

    Livewire::test(VerifyPayout::class)
        ->assertSee($reference)
        ->call('useReference', $reference)
        ->assertRedirect(route('backoffice.paystack.verify', ['reference' => $reference]));
});

/* ------------------------------------------------------------------ active payment manager */

test('the sidebar shows only the active manager\'s wallet pages', function () {
    config(['settings.payment.active_manager' => 'paystack']);

    $this->actingAs(paystackAdmin(), 'admin')
        ->get(route('backoffice.dashboard'))
        ->assertSee('Paystack balance')
        ->assertSee('Paystack history')
        ->assertSee('Send money')
        ->assertSee('Verify payout')
        ->assertSee('Payment references')
        ->assertDontSee('Korapay balance')
        ->assertDontSee('Korapay history')
        ->assertDontSee('Flutterwave balance')
        ->assertDontSee('Flutterwave history');
});

test('a regular admin sees the Paystack pages but not the one that moves money', function () {
    config(['settings.payment.active_manager' => 'paystack']);

    $this->actingAs(Admin::factory()->regular()->create(), 'admin')
        ->get(route('backoffice.dashboard'))
        ->assertSee('Paystack balance')
        ->assertSee('Paystack history')
        ->assertSee('Verify payout')
        ->assertDontSee('Send money');
});

test('every manager\'s pages stay reachable whichever one is active', function () {
    config(['settings.payment.active_manager' => 'korapay']);

    Http::fake([
        '*paystack.co/balance' => Http::response(['status' => true, 'message' => 'ok', 'data' => [['currency' => 'NGN', 'balance' => 100000]]]),
    ]);

    $this->actingAs(paystackAdmin(), 'admin')
        ->get(route('backoffice.paystack.balance'))
        ->assertOk()
        ->assertSee('Paystack balance');
});

test('the services page previews the Paystack menu and saves it', function () {
    $this->actingAs(paystackAdmin(), 'admin');

    // All three managers stay selectable, Paystack included.
    $page = $this->get(route('backoffice.settings.services'))
        ->assertOk()
        ->assertSee('Active payment manager');

    foreach (PaymentProvider::cases() as $provider) {
        $page->assertSee('value="'.$provider->value.'"', false);
    }

    Livewire::test(ServicesSetting::class)
        ->set('activePaymentManager', 'paystack')
        ->assertSee('Paystack balance')
        ->assertSee('Paystack history')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('settings', ['key' => 'active_payment_manager', 'value' => 'paystack']);
});

/* ------------------------------------------------------------------ the shared ledger */

test('a Paystack charge settles into the shared payment-reference ledger', function () {
    $this->actingAs(paystackAdmin(), 'admin');

    PaymentReference::create([
        'reference' => 'NXT-PSK-0001',
        'provider' => 'paystack',
        'gateway_reference' => 'PSK_CHARGE_1',
        'type' => TransactionType::STORAGE_PURCHASE->value,
        'currency' => 'NGN',
        'amount_ngn' => 2500,
        'settled_at' => now(),
    ]);

    $this->get(route('backoffice.payment-references'))
        ->assertOk()
        ->assertSee('Payment references')
        ->assertSee('NXT-PSK-0001');

    Livewire::test(ListPaymentReferences::class)
        ->set('search', 'NXT-PSK-0001')
        ->assertSee('NXT-PSK-0001');
});

/* ------------------------------------------------------------------ audit parity */

test('all three payout models share the trait behaviour', function () {
    foreach ([PaystackPayout::class, FlutterwavePayout::class, KorapayPayout::class] as $model) {
        $row = new $model(['status' => 'unknown']);

        expect($row->isFinal())->toBeFalse()
            ->and($row->statusTone())->toBe('warning')
            ->and($row->statusLabel())->toBe('Unverified');
    }

    expect(PaystackPayout::generateReference())->toStartWith('PSK-');
});

test('the paystack payout table carries the whole audit trail', function () {
    $columns = Schema::getColumnListing('paystack_payouts');

    foreach ([
        'id', 'admin_id', 'reference', 'provider', 'gateway_reference', 'trace_id', 'destination_type',
        'currency', 'debit_currency', 'amount', 'fee', 'status', 'narration', 'note', 'bank_code',
        'bank_name', 'account_number', 'account_name', 'customer_name', 'customer_email',
        'recipient_code', 'message', 'request', 'response', 'error', 'ip_address',
        'initiated_at', 'verified_at', 'last_verified_at',
    ] as $column) {
        expect($columns)->toContain($column);
    }

    // One ledger per manager: a Paystack payout never lands in another manager's table.
    expect(PaystackPayout::query()->count())->toBe(0)
        ->and(KorapayPayout::query()->count())->toBe(0)
        ->and(FlutterwavePayout::query()->count())->toBe(0);
});

test('the Korapay and Flutterwave pages still work through the shared partials', function () {
    // All three managers share the same partials, so each one needs its own key in this test.
    config([
        'services.korapay.base_url' => 'https://api.korapay.com',
        'services.korapay.secret_key' => 'sk_test_korapay',
        'services.flutterwave.base_url' => 'https://api.flutterwave.com',
        'services.flutterwave.secret_key' => 'FLWSECK_TEST-abc123-X',
    ]);

    Http::fake([
        '*korapay.com/merchant/api/v1/balances' => Http::response([
            'status' => true,
            'message' => 'ok',
            'data' => ['NGN' => ['pending_balance' => 100, 'available_balance' => 2000]],
        ]),
        '*flutterwave.com/v3/balances' => Http::response([
            'status' => 'success',
            'message' => 'ok',
            'data' => [['currency' => 'NGN', 'balance' => 3000]],
        ]),
    ]);

    $this->actingAs(paystackAdmin(), 'admin')
        ->get(route('backoffice.korapay.balance'))
        ->assertOk()
        ->assertSee('2,000.00');

    $this->actingAs(paystackAdmin(), 'admin')
        ->get(route('backoffice.flutterwave.balance'))
        ->assertOk()
        ->assertSee('3,000.00');
});
