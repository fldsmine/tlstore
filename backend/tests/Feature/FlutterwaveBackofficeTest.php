<?php

use App\Enums\PaymentProvider;
use App\Livewire\Backoffice\Flutterwave\Balance;
use App\Livewire\Backoffice\Flutterwave\BalanceHistory;
use App\Livewire\Backoffice\Flutterwave\SendMoney;
use App\Livewire\Backoffice\Flutterwave\VerifyPayout;
use App\Livewire\Backoffice\Korapay\ListPaymentReferences;
use App\Livewire\Backoffice\Settings\ServicesSetting;
use App\Models\Admin;
use App\Models\Bank;
use App\Models\Currency;
use App\Models\FlutterwavePayout;
use App\Models\KorapayPayout;
use App\Services\Payments\DTO\PayoutRequest;
use App\Services\Payments\FlutterwaveMerchantService;
use App\Services\Payments\MerchantWalletService;
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
        'services.flutterwave.base_url' => 'https://api.flutterwave.com',
        'services.flutterwave.secret_key' => 'FLWSECK_TEST-abc123-X',
        'services.flutterwave.currency' => 'NGN',
        'services.flutterwave.payout_currencies' => ['NGN', 'USD'],
        'services.flutterwave.payout_max_amount' => 1000000,
        'settings.payment.active_manager' => 'flutterwave',
    ]);
});

function flutterwaveAdmin(): Admin
{
    return Admin::factory()->super()->create();
}

/**
 * Fake every Flutterwave endpoint the backoffice touches with ONE stateful closure — Laravel
 * evaluates stacked Http::fake callbacks together, so a second fake() would silently lose branches.
 */
function fakeFlutterwaveMerchant(array $overrides = []): void
{
    Http::fake(function (ClientRequest $request) use ($overrides) {
        $url = $request->url();
        $body = $request->data();

        if (str_contains($url, '/v3/balances')) {
            if (($overrides['balances'] ?? null) === 'error') {
                return Http::response(['status' => 'error', 'message' => 'Invalid authorization key'], 401);
            }

            if (($overrides['balances'] ?? null) === 'single') {
                return Http::response([
                    'status' => 'success',
                    'message' => 'Balances retrieved',
                    'data' => ['currency' => 'NGN', 'balance' => 987654.32],
                ]);
            }

            return Http::response($overrides['balances'] ?? [
                'status' => 'success',
                'message' => 'Balances retrieved',
                'data' => [
                    ['currency' => 'NGN', 'balance' => 400300.90],
                    ['currency' => 'USD', 'balance' => 10093756.06],
                ],
            ]);
        }

        if (str_contains($url, '/v3/wallet/statement')) {
            return Http::response($overrides['statement'] ?? [
                'status' => 'success',
                'message' => 'Wallet statement retrieved',
                'data' => [
                    'page_info' => ['total' => 1, 'current_page' => (int) ($body['page'] ?? 1), 'total_pages' => 1],
                    'transactions' => [[
                        'type' => 'C',
                        'amount' => '2500.00',
                        'currency' => 'NGN',
                        'balance_before' => '397800.90',
                        'balance_after' => '400300.90',
                        'reference' => 'flw_stmt_1',
                        'date' => '2026-10-11T09:14:00.000Z',
                        'remarks' => 'Wallet credit from card charge',
                        'statement_type' => 'CARD',
                        'transaction_type' => 'credit',
                        'payout_id' => null,
                    ]],
                ],
            ]);
        }

        if (str_contains($url, '/v3/accounts/resolve')) {
            if (($overrides['resolve'] ?? null) === 'error') {
                return Http::response(['status' => 'error', 'message' => 'Account not found'], 400);
            }

            return Http::response([
                'status' => 'success',
                'message' => 'Account resolved',
                'data' => ['account_name' => 'JOHN OKAFOR', 'account_number' => '0123456789', 'bank_name' => 'Access Bank'],
            ]);
        }

        if (str_contains($url, '/v3/transfers')) {
            if (($overrides['disburse'] ?? null) === 'transport-error') {
                throw new ConnectionException('cURL error 28: Operation timed out');
            }

            if (($overrides['disburse'] ?? null) === 'reject') {
                return Http::response(['status' => 'error', 'message' => 'Insufficient balance in wallet'], 400);
            }

            // A GET is a lookup (by OUR reference, or by Flutterwave's numeric id); a POST is a payout.
            $path = parse_url($url, PHP_URL_PATH) ?: '';
            $isLookup = $request->method() === 'GET' && str_ends_with($path, '/v3/transfers');

            if ($isLookup) {
                $reference = $body['reference'] ?? null;

                if (! in_array($reference, ['FLW-VERIFYOK1', 'FLW-VERIFYNOW1'], true)) {
                    // Flutterwave answers "no such transfer" with an EMPTY list — never an error.
                    return Http::response([
                        'status' => 'success',
                        'message' => 'Transfers fetched',
                        'data' => [],
                        'meta' => ['page_info' => ['total' => 0, 'current_page' => 1, 'total_pages' => 1]],
                    ]);
                }

                return Http::response([
                    'status' => 'success',
                    'message' => 'Transfers fetched',
                    'data' => [[
                        'id' => 4242,
                        'status' => $reference === 'FLW-VERIFYOK1' ? 'SUCCESSFUL' : 'NEW',
                        'reference' => $reference,
                        'amount' => 15000,
                        'fee' => 75.5,
                        'currency' => 'NGN',
                        'created_at' => '2026-10-12T10:00:00.000Z',
                        'complete_message' => 'Transfer complete',
                    ]],
                ]);
            }

            return Http::response($overrides['transfer'] ?? [
                'status' => 'success',
                'message' => 'Transfer queued',
                'data' => [
                    'id' => 9911002233,
                    'account_number' => $body['account_number'] ?? '0123456789',
                    'bank_code' => '044',
                    'full_name' => 'JOHN OKAFOR',
                    'created_at' => '2026-10-12T12:00:00.000Z',
                    'currency' => $body['currency'] ?? 'NGN',
                    'amount' => $body['amount'] ?? 0,
                    'fee' => 75.5,
                    'status' => 'NEW',
                    'reference' => $body['reference'] ?? 'FLW-UNKNOWN',
                    'narration' => $body['narration'] ?? null,
                    'complete_message' => 'Transfer Queued',
                    'requires_approval' => 0,
                    'is_approved' => 1,
                    'bank_name' => 'ACCESS BANK NIGERIA',
                ],
            ]);
        }

        return Http::response(['status' => 'error', 'message' => 'unexpected call to '.$url], 500);
    });
}

/** Fill in the payout form the way an admin would, without confirming. */
function flutterwavePayoutForm(string $amount = '15000'): Testable
{
    return Livewire::test(SendMoney::class)
        ->set('amount', $amount)
        ->set('bankCode', '044')
        ->set('accountNumber', '0123456789')
        ->set('accountName', 'JOHN OKAFOR')
        ->set('narration', 'Payout from Nextel');
}

/* ------------------------------------------------------------------ services */

test('both merchant services implement the shared merchant wallet contract', function () {
    expect(app(FlutterwaveMerchantService::class))->toBeInstanceOf(MerchantWalletService::class)
        ->and(app(FlutterwaveMerchantService::class)->name())->toBe('flutterwave')
        ->and(app(FlutterwaveMerchantService::class)->isTestMode())->toBeTrue();
});

test('the balance API normalises a single-object answer into one balance row', function () {
    // A currency-filtered call answers with an object, not a list — both shapes must survive.
    fakeFlutterwaveMerchant(['balances' => 'single']);

    $single = app(FlutterwaveMerchantService::class)->balances();

    expect($single->ok)->toBeTrue()
        ->and($single->data)->toHaveCount(1)
        ->and($single->data[0]['currency'])->toBe('NGN')
        ->and($single->data[0]['available'])->toBe(987654.32);
});

test('the balance API normalises the usual list answer, payout wallet first', function () {
    fakeFlutterwaveMerchant();

    $list = app(FlutterwaveMerchantService::class)->balances();

    expect($list->ok)->toBeTrue()
        ->and($list->data)->toHaveCount(2)
        ->and($list->data[0]['currency'])->toBe('NGN');
});

test('an API error keeps Flutterwave\'s own message and never invents figures', function () {
    fakeFlutterwaveMerchant(['balances' => 'error']);

    $result = app(FlutterwaveMerchantService::class)->balances();

    expect($result->ok)->toBeFalse()
        ->and($result->data)->toBe([])
        ->and($result->message)->toContain('Invalid authorization key');
});

test('the wallet statement defaults to the last 30 days and asks for transaction types', function () {
    fakeFlutterwaveMerchant();

    $history = app(FlutterwaveMerchantService::class)->balanceHistory(['currency' => 'NGN']);

    expect($history['ok'])->toBeTrue()
        ->and($history['entries'])->toHaveCount(1)
        ->and($history['entries'][0]['direction'])->toBe('credit')
        ->and($history['entries'][0]['source_reference'])->toBe('flw_stmt_1')
        ->and($history['entries'][0]['balance_after'])->toBe(400300.90);

    Http::assertSent(function (ClientRequest $request) {
        $query = [];
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_contains($request->url(), '/v3/wallet/statement')
            && ($query['include_transaction_type'] ?? null) === 'true'
            && filled($query['from'] ?? null)
            && filled($query['to'] ?? null);
    });
});

/* ------------------------------------------------------------------ pages */

test('guests cannot reach any Flutterwave backoffice page', function () {
    foreach ([
        'backoffice.flutterwave.balance',
        'backoffice.flutterwave.history',
        'backoffice.flutterwave.verify',
        'backoffice.flutterwave.send-money',
        'backoffice.payment-references',
    ] as $route) {
        $this->get(route($route))->assertRedirect(route('backoffice.login'));
    }
});

test('the balance page shows the Flutterwave wallet balances', function () {
    fakeFlutterwaveMerchant();

    $this->actingAs(flutterwaveAdmin(), 'admin')
        ->get(route('backoffice.flutterwave.balance'))
        ->assertOk()
        ->assertSee('Flutterwave balance')
        ->assertSee('400,300.90')
        ->assertSee('10,093,756.06')
        ->assertSee('Test mode');

    Livewire::test(Balance::class)
        ->assertSet('error', null)
        ->assertSee('NGN');
});

test('the balance page degrades gracefully when Flutterwave rejects the request', function () {
    fakeFlutterwaveMerchant(['balances' => 'error']);

    $this->actingAs(flutterwaveAdmin(), 'admin')
        ->get(route('backoffice.flutterwave.balance'))
        ->assertOk()
        ->assertSee('Invalid authorization key');

    Livewire::test(Balance::class)->assertSet('error', 'Invalid authorization key');
});

test('the balance page refuses to call the API when the secret key is missing', function () {
    config(['services.flutterwave.secret_key' => '']);
    Http::fake();

    $this->actingAs(flutterwaveAdmin(), 'admin');

    Livewire::test(Balance::class)
        ->assertSet('balances', [])
        ->assertSee('secret key not set');

    Http::assertNothingSent();
});

test('the history page lists the wallet movements', function () {
    fakeFlutterwaveMerchant();

    $this->actingAs(flutterwaveAdmin(), 'admin')
        ->get(route('backoffice.flutterwave.history'))
        ->assertOk()
        ->assertSee('Flutterwave balance history')
        ->assertSee('flw_stmt_1')
        ->assertSee('2,500.00')
        ->assertSee('Wallet credit from card charge');
});

test('the history page hides the per-page selector, which Flutterwave does not support', function () {
    fakeFlutterwaveMerchant();

    $this->actingAs(flutterwaveAdmin(), 'admin')
        ->get(route('backoffice.flutterwave.history'))
        ->assertOk()
        ->assertDontSee('Per page');
});

test('a bad date filter is rejected before the API is called', function () {
    fakeFlutterwaveMerchant();

    $this->actingAs(flutterwaveAdmin(), 'admin');

    Livewire::test(BalanceHistory::class)
        ->set('dateFrom', '2026-10-10')
        ->set('dateTo', '2026-10-01')
        ->call('applyFilters')
        ->assertHasErrors('dateTo');
});

test('a ?reference= link opens the verify page and checks the payout straight away', function () {
    fakeFlutterwaveMerchant();

    FlutterwavePayout::create([
        'reference' => 'FLW-VERIFYOK1',
        'amount' => 15000,
        'currency' => 'NGN',
        'bank_code' => '044',
        'account_number' => '0123456789',
        'account_name' => 'JOHN OKAFOR',
        'narration' => 'Payout from Nextel',
        'status' => 'processing',
        'admin_id' => flutterwaveAdmin()->id,
        'initiated_at' => now(),
    ]);

    $this->actingAs(flutterwaveAdmin(), 'admin')
        ->get(route('backoffice.flutterwave.verify', ['reference' => 'FLW-VERIFYOK1']))
        ->assertOk()
        ->assertSee('FLW-VERIFYOK1')
        ->assertSee('Verification result');

    expect(FlutterwavePayout::sole()->status)->toBe('success');
});

test('the verify page renders and prefills from the query string', function () {
    fakeFlutterwaveMerchant();

    $this->actingAs(flutterwaveAdmin(), 'admin')
        ->get(route('backoffice.flutterwave.verify'))
        ->assertOk()
        ->assertSee('Verify Flutterwave payout')
        ->assertSee('FLW-… or a Flutterwave transfer id');

    Livewire::test(VerifyPayout::class, ['reference' => 'FLW-VERIFYOK1'])
        ->assertSet('reference', 'FLW-VERIFYOK1')
        ->assertSet('result.reference', 'FLW-VERIFYOK1');
});

/* ------------------------------------------------------------------ send money */

test('send money is super-admin only at the route level', function () {
    fakeFlutterwaveMerchant();

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('backoffice.flutterwave.send-money'))
        ->assertForbidden();
});

test('a regular admin cannot use the send money component either', function () {
    fakeFlutterwaveMerchant();

    $this->actingAs(Admin::factory()->create(), 'admin');

    // The mount guard aborts; Livewire renders the error page instead of the payout form.
    expect(Livewire::test(SendMoney::class)->html())->not->toContain('Review payout');
});

test('a payout reference is generated with the FLW prefix', function () {
    fakeFlutterwaveMerchant();

    $this->actingAs(flutterwaveAdmin(), 'admin');

    $component = Livewire::test(SendMoney::class);

    expect((string) $component->get('reference'))->toStartWith('FLW-');
});

test('resolving an account fills in the verified name', function () {
    fakeFlutterwaveMerchant();

    $this->actingAs(flutterwaveAdmin(), 'admin');

    Livewire::test(SendMoney::class)
        ->set('bankCode', '044')
        ->set('accountNumber', '0123456789')
        ->call('resolveAccount')
        ->assertSet('accountName', 'JOHN OKAFOR')
        ->assertSet('accountResolved', true);
});

test('the payout form cannot be submitted with an invalid amount', function () {
    fakeFlutterwaveMerchant();

    $this->actingAs(flutterwaveAdmin(), 'admin');

    Livewire::test(SendMoney::class)
        ->set('amount', '0')
        ->set('bankCode', '044')
        ->set('accountNumber', '0123456789')
        ->set('accountName', 'JOHN OKAFOR')
        ->call('confirm')
        ->assertHasErrors('amount')
        ->assertSet('showConfirm', false);
});

test('a transport error leaves the payout unverified instead of marking it failed', function () {
    fakeFlutterwaveMerchant(['disburse' => 'transport-error']);

    $this->actingAs(flutterwaveAdmin(), 'admin');

    flutterwavePayoutForm('15000')->call('confirm')->call('send');

    $payout = FlutterwavePayout::sole();

    expect($payout->status)->toBe('unknown')
        ->and($payout->provider)->toBe('flutterwave')
        ->and($payout->error)->not->toBeNull();
});

test('a definitive rejection is stored as failed with Flutterwave\'s message', function () {
    fakeFlutterwaveMerchant(['disburse' => 'reject']);

    $this->actingAs(flutterwaveAdmin(), 'admin');

    flutterwavePayoutForm('16000')->call('confirm')->call('send');

    $payout = FlutterwavePayout::sole();

    expect($payout->status)->toBe('failed')
        ->and($payout->error)->toContain('Insufficient balance')
        ->and($payout->verified_at)->toBeNull();
});

test('a queued transfer is stored as in flight with Flutterwave\'s transfer id', function () {
    fakeFlutterwaveMerchant();

    $this->actingAs(flutterwaveAdmin(), 'admin');

    flutterwavePayoutForm('20000')
        ->call('confirm')
        ->call('send')
        ->assertSet('showConfirm', false);

    $payout = FlutterwavePayout::sole();

    expect($payout->status)->toBe('processing')
        ->and($payout->gateway_reference)->toBe('9911002233')
        ->and((float) $payout->amount)->toBe(20000.0)
        ->and($payout->bank_name)->toBe('Access Bank');

    Http::assertSent(fn (ClientRequest $request) => str_contains($request->url(), '/v3/transfers')
        && $request->data()['reference'] === $payout->reference);
});

test('an identical in-flight payout is blocked instead of sent twice', function () {
    fakeFlutterwaveMerchant();

    $this->actingAs(flutterwaveAdmin(), 'admin');

    flutterwavePayoutForm('20000')->call('confirm')->call('send');
    flutterwavePayoutForm('20000')->call('confirm')->call('send');

    expect(FlutterwavePayout::count())->toBe(1);

    Http::assertSentCount(1); // one disbursement; the second attempt never reaches the API
});

test('a cross-currency payout sends the wallet to debit', function () {
    fakeFlutterwaveMerchant();

    $this->actingAs(flutterwaveAdmin(), 'admin');

    flutterwavePayoutForm('41000')
        ->set('debitCurrency', 'USD')
        ->call('confirm')
        ->call('send');

    expect(FlutterwavePayout::sole()->debit_currency)->toBe('USD');

    Http::assertSent(fn (ClientRequest $request) => str_contains($request->url(), '/v3/transfers')
        && ($request->data()['debit_currency'] ?? null) === 'USD');
});

test('the form validates against the active manager\'s own ceiling', function () {
    config(['services.flutterwave.payout_max_amount' => 50000, 'services.korapay.payout_max_amount' => 1000000]);
    fakeFlutterwaveMerchant();

    $this->actingAs(flutterwaveAdmin(), 'admin');

    $this->get(route('backoffice.flutterwave.send-money'))
        ->assertOk()
        ->assertSee('50,000.00');

    $this->get(route('backoffice.korapay.send-money'))
        ->assertOk()
        ->assertSee('1,000,000.00');
});

test('an amount above the ceiling never reaches the transfers API', function () {
    config(['services.flutterwave.payout_max_amount' => 100]);
    fakeFlutterwaveMerchant();

    $result = app(FlutterwaveMerchantService::class)->disburse(new PayoutRequest(
        reference: 'FLW-OVERLIMIT1',
        destinationType: 'bank_account',
        amount: 5000,
        currency: 'NGN',
        narration: 'Over the ceiling',
        accountNumber: '0123456789',
        bankCode: '044',
    ));

    expect($result->ok)->toBeTrue()
        ->and($result->status)->toBe('failed')
        ->and($result->message)->toContain('above the configured ceiling');

    Http::assertNothingSent();
});

test('a wallet outside the configured payout list is rejected before any call', function () {
    fakeFlutterwaveMerchant();

    $this->actingAs(flutterwaveAdmin(), 'admin');

    flutterwavePayoutForm('41000')
        ->set('debitCurrency', 'XXX')
        ->call('confirm')
        ->assertHasErrors('debitCurrency');

    expect(FlutterwavePayout::count())->toBe(0);
});

/* ------------------------------------------------------------------ verify payout */

test('verifying a completed transfer reconciles the local audit row', function () {
    fakeFlutterwaveMerchant();

    $this->actingAs(flutterwaveAdmin(), 'admin');

    $payout = FlutterwavePayout::create([
        'reference' => 'FLW-VERIFYOK1',
        'amount' => 15000,
        'currency' => 'NGN',
        'bank_code' => '044',
        'account_number' => '0123456789',
        'account_name' => 'JOHN OKAFOR',
        'narration' => 'Payout from Nextel',
        'status' => 'processing',
        'admin_id' => flutterwaveAdmin()->id,
        'initiated_at' => now(),
    ]);

    Livewire::test(VerifyPayout::class, ['reference' => 'FLW-VERIFYOK1'])
        ->assertSet('localPayoutId', $payout->id)
        ->assertSee('Success');

    $payout->refresh();

    expect($payout->status)->toBe('success')
        ->and((float) $payout->fee)->toBe(75.5)
        ->and($payout->gateway_reference)->toBe('4242')
        ->and($payout->verified_at)->not->toBeNull()
        ->and($payout->last_verified_at)->not->toBeNull();
});

test('an in-flight transfer is reported as processing, not success', function () {
    fakeFlutterwaveMerchant();

    $this->actingAs(flutterwaveAdmin(), 'admin');

    FlutterwavePayout::create([
        'reference' => 'FLW-VERIFYNOW1',
        'amount' => 1000,
        'currency' => 'NGN',
        'bank_code' => '044',
        'account_number' => '0800000000',
        'account_name' => 'JANE DOE',
        'narration' => 'Payout from Nextel',
        'status' => 'processing',
        'admin_id' => flutterwaveAdmin()->id,
        'initiated_at' => now(),
    ]);

    Livewire::test(VerifyPayout::class, ['reference' => 'FLW-VERIFYNOW1'])
        ->assertSet('result.status', 'processing')
        ->assertSet('result.ok', true);
});

test('an unknown reference is never reported as successful', function () {
    fakeFlutterwaveMerchant();

    $this->actingAs(flutterwaveAdmin(), 'admin');

    Livewire::test(VerifyPayout::class, ['reference' => 'FLW-NOSUCHREF'])
        ->assertSet('localPayoutId', null)
        ->assertSet('result.status', 'failed')
        ->assertSee('no transfer with that reference');
});

test('the recent payouts list deep-links a reference into the verify page', function () {
    fakeFlutterwaveMerchant();

    $this->actingAs(flutterwaveAdmin(), 'admin');

    $payout = FlutterwavePayout::create([
        'reference' => 'FLW-LISTED01',
        'amount' => 500,
        'currency' => 'NGN',
        'bank_code' => '044',
        'account_number' => '0123456789',
        'account_name' => 'LISTED PERSON',
        'narration' => 'Payout from Nextel',
        'status' => 'processing',
        'admin_id' => flutterwaveAdmin()->id,
        'initiated_at' => now(),
    ]);

    Livewire::test(VerifyPayout::class)
        ->assertSee('FLW-LISTED01')
        ->call('useReference', $payout->reference)
        ->assertRedirect(route('backoffice.flutterwave.verify', ['reference' => 'FLW-LISTED01']));
});

/* ------------------------------------------------------------------ active payment manager */

test('the sidebar shows only the active manager\'s wallet pages', function () {
    fakeFlutterwaveMerchant();
    $admin = flutterwaveAdmin();

    config(['settings.payment.active_manager' => 'korapay']);

    $this->actingAs($admin, 'admin')
        ->get(route('backoffice.dashboard'))
        ->assertSee('Korapay balance')
        ->assertSee('Korapay history')
        ->assertSee('Payment references')
        ->assertDontSee('Flutterwave balance')
        ->assertDontSee('Flutterwave history');

    config(['settings.payment.active_manager' => 'flutterwave']);

    $this->actingAs($admin, 'admin')
        ->get(route('backoffice.dashboard'))
        ->assertSee('Flutterwave balance')
        ->assertSee('Flutterwave history')
        ->assertSee('Send money')
        ->assertSee('Verify payout')
        ->assertSee('Payment references')
        ->assertDontSee('Korapay balance')
        ->assertDontSee('Korapay history');
});

test('selecting paystack swaps the payout menu over to the Paystack pages', function () {
    config(['settings.payment.active_manager' => 'paystack']);

    $this->actingAs(flutterwaveAdmin(), 'admin')
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

test('the reference ledger stays reachable under its own route and the legacy alias', function () {
    $this->actingAs(flutterwaveAdmin(), 'admin')
        ->get(route('backoffice.payment-references'))
        ->assertOk()
        ->assertSee('Payment references');

    $this->actingAs(flutterwaveAdmin(), 'admin')
        ->get(route('backoffice.korapay.references'))
        ->assertRedirect(route('backoffice.payment-references'));

    Livewire::test(ListPaymentReferences::class)->assertSee('Payment references');
});

test('the services page offers all three managers and previews the resulting menu', function () {
    $this->actingAs(flutterwaveAdmin(), 'admin');

    $response = $this->get(route('backoffice.settings.services'))
        ->assertOk()
        ->assertSee('Active payment manager');

    foreach (PaymentProvider::values() as $provider) {
        $response->assertSee('value="'.$provider.'"', false);
    }

    // Every manager the backoffice offers now has wallet pages, so the preview names them.
    Livewire::test(ServicesSetting::class)
        ->set('activePaymentManager', 'paystack')
        ->assertSee('Paystack balance')
        ->assertSee('Paystack history');

    Livewire::test(ServicesSetting::class)
        ->set('activePaymentManager', 'korapay')
        ->assertSee('Korapay balance');
});

test('saving the active manager persists it and the config mapper reads it back', function () {
    $this->actingAs(flutterwaveAdmin(), 'admin');

    Livewire::test(ServicesSetting::class)
        ->set('activePaymentManager', 'flutterwave')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('settings', ['key' => 'active_payment_manager', 'value' => 'flutterwave']);

    expect(\App\Support\SettingMapper::toConfigArray(['active_payment_manager' => 'flutterwave']))
        ->toHaveKey('settings.payment.active_manager', 'flutterwave');
});

test('an unsupported manager name is rejected', function () {
    $this->actingAs(flutterwaveAdmin(), 'admin');

    Livewire::test(ServicesSetting::class)
        ->set('activePaymentManager', 'stripe')
        ->call('save')
        ->assertHasErrors('activePaymentManager');
});

/* ------------------------------------------------------------------ audit parity */

test('both payout models share the trait behaviour', function () {
    foreach ([FlutterwavePayout::class, KorapayPayout::class] as $model) {
        $row = new $model(['status' => 'success']);

        expect($row->isFinal())->toBeTrue()
            ->and($row->statusTone())->toBe('success')
            ->and($row->statusLabel())->toBeString();
    }

    expect(FlutterwavePayout::generateReference())->toStartWith('FLW-')
        ->and(KorapayPayout::generateReference())->toStartWith('KPO-');
});

test('the flutterwave payout table carries the whole audit trail', function () {
    $columns = Schema::getColumnListing('flutterwave_payouts');

    foreach ([
        'id', 'admin_id', 'reference', 'provider', 'gateway_reference', 'trace_id', 'destination_type',
        'currency', 'debit_currency', 'amount', 'fee', 'status', 'narration', 'note', 'bank_code',
        'bank_name', 'account_number', 'account_name', 'customer_name', 'customer_email',
        'beneficiary_name', 'message', 'request', 'response', 'error', 'ip_address',
        'initiated_at', 'verified_at', 'last_verified_at',
    ] as $column) {
        expect($columns)->toContain($column);
    }

    // The Korapay table gained the shared cross-currency column so both tables stay parallel.
    expect(Schema::getColumnListing('korapay_payouts'))->toContain('debit_currency');
});

test('the Korapay pages still work through the shared partials', function () {
    // Both managers share the same partials, so the Korapay side needs its own key here.
    config(['services.korapay.base_url' => 'https://api.korapay.com', 'services.korapay.secret_key' => 'sk_test_korapay']);

    Http::fake([
        '*balances/history*' => Http::response(['status' => true, 'message' => 'ok', 'has_more' => false, 'data' => []]),
        '*merchant/api/v1/balances' => Http::response([
            'status' => true,
            'message' => 'ok',
            'data' => ['NGN' => ['pending_balance' => 100, 'available_balance' => 2000]],
        ]),
    ]);

    $this->actingAs(flutterwaveAdmin(), 'admin')
        ->get(route('backoffice.korapay.balance'))
        ->assertOk()
        ->assertSee('Korapay balance')
        ->assertSee('2,000.00');
});
