<?php

use App\Enums\ChargePurpose;
use App\Enums\PaymentProvider;
use App\Enums\TransactionType;
use App\Livewire\Backoffice\Bachs\Balance;
use App\Livewire\Backoffice\Bachs\BalanceHistory;
use App\Livewire\Backoffice\Bachs\SendMoney;
use App\Livewire\Backoffice\Bachs\VerifyPayout;
use App\Livewire\Backoffice\Korapay\ListPaymentReferences;
use App\Livewire\Backoffice\Settings\ServicesSetting;
use App\Models\Admin;
use App\Models\BachsPayout;
use App\Models\Bank;
use App\Models\Currency;
use App\Models\FlutterwavePayout;
use App\Models\KorapayPayout;
use App\Models\PaymentReference;
use App\Models\PaystackPayout;
use App\Models\Setting;
use App\Services\Payments\BachsMerchantService;
use App\Services\Payments\DTO\PayoutRequest;
use App\Services\Payments\FlutterwaveMerchantService;
use App\Services\Payments\KorapayMerchantService;
use App\Services\Payments\MerchantWalletService;
use App\Services\Payments\PaystackMerchantService;
use App\Support\SettingMapper;
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
        'services.bachs.base_url' => 'https://api.bachs.io',
        'services.bachs.secret_key' => 'sk_test_bachs_abc123',
        'services.bachs.currency' => 'NGN',
        'services.bachs.payout_currencies' => ['NGN'],
        'services.bachs.payout_max_amount' => 1000000,
        'settings.payment.active_manager' => 'bachs',
    ]);
});

function bachsAdmin(): Admin
{
    return Admin::factory()->super()->create();
}

/**
 * Fake every Bachs endpoint the backoffice touches with ONE stateful closure — Laravel evaluates
 * stacked Http::fake callbacks together, so a second fake() would silently lose branches.
 *
 * Amounts are the ones Bachs really sends: decimal STRINGS in major units, never minor units.
 */
function fakeBachsMerchant(array $overrides = []): void
{
    // Stateful: a payout created through this fake can be looked up again afterwards (the payouts list
    // echoes our reference), so a single registration covers a whole create → verify flow. Laravel
    // MERGES stacked fakes and the first match wins, so a test must never register a second one.
    $created = [];

    Http::fake(function (ClientRequest $request) use ($overrides, &$created) {
        $url = $request->url();
        $body = $request->data();

        if (str_contains($url, '/v1/balances')) {
            if (($overrides['balances'] ?? null) === 'error') {
                return Http::response(['detail' => 'Invalid API key', 'error_code' => 'UNAUTHORIZED'], 401);
            }

            return Http::response([
                'account_id' => 'acct_test',
                'balances' => $overrides['balances'] ?? [
                    ['currency' => 'USD', 'available_balance' => '95178.20', 'pending_balance' => '0.00'],
                    ['currency' => 'NGN', 'available_balance' => '58700.00', 'pending_balance' => '1500.00'],
                ],
                'total_balance_usd' => '95221.29',
                'pending_settlements_by_day' => [],
            ]);
        }

        if (str_contains($url, '/v1/payments')) {
            $items = $overrides['payments'] ?? [
                [
                    'id' => 'chrg_collected1', 'reference' => 'BTU-COLLECTED-1', 'status' => 'succeeded',
                    'amount' => '7500.00', 'amount_paid' => '7500.00', 'currency' => 'NGN',
                    'transaction_date' => '2026-10-14T10:00:00Z',
                ],
                [
                    'id' => 'chrg_dead1', 'reference' => 'BTU-FAILED-1', 'status' => 'failed',
                    'amount' => '2500.00', 'amount_paid' => '0.00', 'currency' => 'NGN',
                    'transaction_date' => '2026-10-13T10:00:00Z',
                ],
            ];

            return Http::response([
                'items' => $items,
                'pagination' => ['has_more' => (bool) ($overrides['paymentsMore'] ?? false), 'limit' => 100, 'offset' => (int) ($body['offset'] ?? 0), 'returned' => count($items), 'total' => count($items)],
            ]);
        }

        if (str_contains($url, '/v1/payouts/destinations')) {
            if ($request->method() === 'POST') {
                if (($overrides['destination'] ?? null) === 'error') {
                    return Http::response(['detail' => 'Bank routing details are invalid', 'error_code' => 'BANK_ROUTING_DETAILS_INVALID'], 400);
                }

                return Http::response([
                    'id' => 'pd_new123',
                    'name' => 'Access Bank',
                    'type' => 'bank_account',
                    'currency' => (string) ($body['currency'] ?? 'NGN'),
                    'status' => ($overrides['destination'] ?? null) === 'review' ? 'pending_review' : 'approved',
                    'status_reason' => null,
                    'is_usable' => ($overrides['destination'] ?? null) !== 'review',
                    'account_number' => (string) ($body['account_number'] ?? ''),
                    'account_name' => 'JOHN OKAFOR',
                    'bank_code' => (string) ($body['bank_code'] ?? ''),
                    'bank_name' => 'Access Bank',
                ], 201);
            }

            if (($overrides['destinations'] ?? null) === 'error') {
                return Http::response(['detail' => 'Service unavailable', 'error_code' => 'SERVICE_UNAVAILABLE'], 503);
            }

            return Http::response([
                'items' => $overrides['destinations'] ?? [[
                    'id' => 'pd_saved123',
                    'name' => 'Access Bank',
                    'type' => 'bank_account',
                    'currency' => 'NGN',
                    'status' => 'approved',
                    'status_reason' => null,
                    'is_usable' => true,
                    'is_default' => false,
                    'account_number' => '0123456789',
                    'account_name' => 'JOHN OKAFOR',
                    'bank_code' => '044',
                    'bank_name' => 'Access Bank',
                ]],
                'pagination' => ['has_more' => false, 'limit' => 100, 'offset' => 0, 'returned' => 1],
            ]);
        }

        if (str_contains($url, '/v1/misc/bank-accounts/resolve')) {
            if (($overrides['resolve'] ?? null) === 'unresolved') {
                return Http::response(['resolved' => false, 'account_name' => null, 'account_number' => null, 'message' => 'Account number not found'], 200);
            }

            if (($overrides['resolve'] ?? null) === 'error') {
                return Http::response(['detail' => 'Could not reach the bank', 'error_code' => 'SERVICE_UNAVAILABLE'], 503);
            }

            return Http::response(['resolved' => true, 'account_name' => 'JOHN OKAFOR', 'account_number' => '0123456789', 'message' => null]);
        }

        // Get one payout by Bachs' own id.
        if (preg_match('#/v1/payouts/(pay_[A-Za-z0-9_]+)#', $url, $matches) === 1) {
            if (($overrides['payoutById'] ?? null) === '404' || $matches[1] === 'pay_missing') {
                return Http::response(['detail' => 'Payout not found', 'error_code' => 'NOT_FOUND'], 404);
            }

            if (($overrides['payoutById'] ?? null) === 'server-error') {
                return Http::response(['detail' => 'Server error', 'error_code' => 'INTERNAL_SERVER_ERROR'], 502);
            }

            return Http::response([
                'id' => $matches[1],
                'status' => $overrides['payoutStatus'] ?? 'completed',
                'amount' => '15000.00',
                'currency' => 'NGN',
                'source_currency' => 'NGN',
                'fee' => '50.00',
                'total_debited' => '15050.00',
                'destination' => 'pd_saved123',
                'reference' => $overrides['payoutReference'] ?? 'BCH-VERIFY1',
                'failure_reason' => null,
                'created_at' => '2026-10-14T11:00:00Z',
                'completed_at' => '2026-10-14T11:02:00Z',
            ]);
        }

        // The payouts list (both the statement and verify-by-reference use it).
        if (str_contains($url, '/v1/payouts') && $request->method() === 'GET') {
            if (($overrides['payouts'] ?? null) === 'error') {
                return Http::response(['detail' => 'Server error', 'error_code' => 'INTERNAL_SERVER_ERROR'], 502);
            }

            $items = $overrides['payouts'] ?? ($created !== [] ? [$created[0]] : [[
                    'id' => 'pay_moved1',
                    'status' => 'completed',
                    'amount' => '5000.00',
                    'currency' => 'NGN',
                    'source_currency' => 'NGN',
                    'fee' => '100.00',
                    'total_debited' => '5100.00',
                    'destination' => 'pd_saved123',
                    'reference' => 'BCH-MOVED-1',
                    'failure_reason' => null,
                    'created_at' => '2026-10-14T11:00:00Z',
                    'completed_at' => '2026-10-14T11:01:00Z',
                ]]);

            return Http::response([
                'items' => $items,
                'pagination' => ['has_more' => (bool) ($overrides['payoutsMore'] ?? false), 'limit' => 100, 'offset' => (int) ($body['offset'] ?? 0), 'returned' => count($items)],
            ]);
        }

        // Create a payout.
        if (str_contains($url, '/v1/payouts') && $request->method() === 'POST') {
            if (($overrides['payout'] ?? null) === 'transport-error') {
                throw new ConnectionException('cURL error 28: Operation timed out');
            }

            if (($overrides['payout'] ?? null) === 'reject') {
                return Http::response(['detail' => 'Insufficient balance', 'error_code' => 'PAYOUT_SOURCE_CURRENCY_UNSUPPORTED'], 400);
            }

            if (($overrides['payout'] ?? null) === 'server-error') {
                return Http::response(['detail' => 'Server error', 'error_code' => 'INTERNAL_SERVER_ERROR'], 502);
            }

            $created[0] = [
                'id' => 'pay_queued123',
                'status' => $overrides['payoutStatus'] ?? 'completed',
                'amount' => (string) ($body['amount'] ?? '0.00'),
                'currency' => 'NGN',
                'source_currency' => 'NGN',
                'fee' => '50.00',
                'total_debited' => '15050.00',
                'destination' => (string) ($body['destination'] ?? ''),
                'reference' => (string) ($body['reference'] ?? ''),
                'failure_reason' => null,
                'created_at' => '2026-10-14T12:00:00Z',
                'completed_at' => '2026-10-14T12:01:00Z',
            ];

            return Http::response([
                'id' => 'pay_queued123',
                'status' => $overrides['payoutStatus'] ?? 'pending',
                'amount' => (string) ($body['amount'] ?? ''),
                'currency' => 'NGN',
                'source_currency' => 'NGN',
                'fee' => '50.00',
                'total_debited' => '15050.00',
                'destination' => (string) ($body['destination'] ?? ''),
                'reference' => (string) ($body['reference'] ?? ''),
                'failure_reason' => null,
                'created_at' => '2026-10-14T12:00:00Z',
            ], 201);
        }

        return Http::response(['detail' => 'unexpected '.$request->method().' call to '.$url, 'error_code' => 'UNEXPECTED'], 500);
    });
}

/** Fill in the payout form the way an admin would, without confirming. */
function bachsPayoutForm(string $amount = '15000'): Testable
{
    return Livewire::test(SendMoney::class)
        ->set('amount', $amount)
        ->set('bankCode', '044')
        ->set('accountNumber', '0123456789')
        ->set('accountName', 'JOHN OKAFOR')
        ->set('narration', 'Payout from Nextel');
}

/* ------------------------------------------------------------------ services */

test('the Bachs merchant service implements the shared merchant wallet contract', function () {
    foreach ([BachsMerchantService::class, PaystackMerchantService::class, KorapayMerchantService::class, FlutterwaveMerchantService::class] as $service) {
        expect(app($service))->toBeInstanceOf(MerchantWalletService::class);
    }

    expect(app(BachsMerchantService::class)->name())->toBe('bachs')
        ->and(app(BachsMerchantService::class)->isTestMode())->toBeFalse()
        ->and(app(BachsMerchantService::class)->payoutCurrencies())->toBe(['NGN'])
        ->and(app(BachsMerchantService::class)->maxPayoutAmount())->toBe(1000000.0);

    // A sandbox key (or a sandbox base URL) is what the badge is for.
    config(['services.bachs.secret_key' => 'sk_sandbox_bachs_abc123']);

    expect(app(BachsMerchantService::class)->isTestMode())->toBeTrue();
});

test('the balance API reports each currency bucket in major units, payout currency first', function () {
    fakeBachsMerchant();

    $result = app(BachsMerchantService::class)->balances();

    expect($result->ok)->toBeTrue()
        ->and($result->data[0]['currency'])->toBe('NGN')
        ->and($result->data[0]['available'])->toBe(58700.0)
        ->and($result->data[0]['pending'])->toBe(1500.0)
        ->and($result->data[0]['issuing'])->toBeNull()
        ->and($result->data[1]['currency'])->toBe('USD')
        ->and($result->data[1]['available'])->toBe(95178.20);
});

test('a Bachs balance error keeps Bachs own message and invents nothing', function () {
    fakeBachsMerchant(['balances' => 'error']);

    $result = app(BachsMerchantService::class)->balances();

    expect($result->ok)->toBeFalse()
        ->and($result->data)->toBe([])
        ->and($result->message)->toBe('Invalid API key');
});

test('no balance is requested at all when the secret key is missing', function () {
    config(['services.bachs.secret_key' => '']);

    Http::fake();

    $result = app(BachsMerchantService::class)->balances();

    expect($result->ok)->toBeFalse()
        ->and($result->message)->toContain('not configured');

    Http::assertNothingSent();
});

/* ------------------------------------------------------------------ balance history */

test('the statement lists collections in and payouts out, and drops what never moved money', function () {
    fakeBachsMerchant();

    $result = app(BachsMerchantService::class)->balanceHistory(['currency' => 'NGN', 'limit' => 25]);

    expect($result['ok'])->toBeTrue()
        ->and($result['entries'])->toHaveCount(2)
        ->and($result['pointers']['last'])->toBe('1');

    $credit = collect($result['entries'])->firstWhere('direction', 'credit');
    $debit = collect($result['entries'])->firstWhere('direction', 'debit');

    // The failed collection and the failed payout are not movements.
    expect($credit['amount'])->toBe(7500.0)
        ->and($credit['source_reference'])->toBe('BTU-COLLECTED-1')
        ->and($credit['source'])->toBe('payment')
        ->and($debit['amount'])->toBe(5100.0) // total_debited, not the destination amount
        ->and($debit['source_reference'])->toBe('BCH-MOVED-1')
        ->and($debit['source'])->toBe('payout');
});

test('the statement reports no running balance rather than inventing one', function () {
    fakeBachsMerchant();

    $result = app(BachsMerchantService::class)->balanceHistory(['limit' => 25]);

    // Bachs has no ledger endpoint, so a per-row balance would be a guess.
    expect($result['entries'][0]['balance_before'])->toBeNull()
        ->and($result['entries'][0]['balance_after'])->toBeNull();

    $this->actingAs(bachsAdmin(), 'admin')
        ->get(route('backoffice.bachs.history'))
        ->assertOk()
        ->assertSee('Bachs balance history')
        ->assertSee('BTU-COLLECTED-1')
        ->assertSee('7,500.00')
        ->assertSee('BCH-MOVED-1')
        ->assertSee('5,100.00')
        ->assertSee('balance not reported');
});

test('the statement asks for the requested page size at that page offset and reports more', function () {
    fakeBachsMerchant(['payoutsMore' => true]);

    app(BachsMerchantService::class)->balanceHistory(['limit' => 10, 'starting_after' => '3']);

    Http::assertSent(fn (ClientRequest $request): bool => str_contains($request->url(), '/v1/payments')
        && ($request->data()['limit'] ?? null) === 10
        && ($request->data()['offset'] ?? null) === 20);

    $result = app(BachsMerchantService::class)->balanceHistory(['limit' => 10, 'starting_after' => '3']);

    expect($result['has_more'])->toBeTrue()
        ->and($result['entries'][0]['pointer'])->toBe('3');
});

test('statement filters are enforced even if the endpoint ignores them', function () {
    fakeBachsMerchant();

    $service = app(BachsMerchantService::class);

    // Direction: only the payout, never the collection.
    $debits = $service->balanceHistory(['direction' => 'debit', 'limit' => 25]);
    expect($debits['entries'])->toHaveCount(1)
        ->and($debits['entries'][0]['direction'])->toBe('debit');

    // Currency: the USD collection is filtered out of an NGN-only view.
    $usdOnly = $service->balanceHistory(['currency' => 'USD', 'limit' => 25]);
    expect($usdOnly['entries'])->toHaveCount(0);

    // Dates: a window that excludes both movements returns nothing.
    $old = $service->balanceHistory(['date_from' => '2026-10-01-00-00-00', 'date_to' => '2026-10-05-23-59-59', 'limit' => 25]);
    expect($old['entries'])->toHaveCount(0);
});

test('a statement Bachs rejects shows Bachs own message', function () {
    fakeBachsMerchant(['payouts' => 'error']);

    $result = app(BachsMerchantService::class)->balanceHistory(['limit' => 25]);

    expect($result['ok'])->toBeFalse()
        ->and($result['entries'])->toBe([])
        ->and($result['message'])->toBe('Server error');
});

/* ------------------------------------------------------------------ payouts */

test('a payout reuses the saved destination for that account and pays it with our reference', function () {
    fakeBachsMerchant();

    $result = app(BachsMerchantService::class)->disburse(new PayoutRequest(
        reference: 'BCH-TEST0001',
        destinationType: 'bank_account',
        amount: 15000.0,
        currency: 'NGN',
        narration: 'Payout from Nextel',
        accountNumber: '0123456789',
        bankCode: '044',
        bankName: 'Access Bank',
        accountName: 'JOHN OKAFOR',
        customerName: 'Admin',
        customerEmail: 'admin@example.com',
    ));

    // The saved destination was reused (no second one created), the amount went out as a decimal
    // string, and our reference doubles as the Idempotency-Key so a repeat cannot create a second
    // payout.
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
        && str_contains($request->url(), '/v1/payouts')
        && ($request->data()['destination'] ?? null) === 'pd_saved123'
        && ($request->data()['amount'] ?? null) === '15000.00'
        && ($request->data()['reference'] ?? null) === 'BCH-TEST0001'
        && $request->hasHeader('Idempotency-Key', 'BCH-TEST0001'));

    Http::assertNotSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
        && str_contains($request->url(), '/v1/payouts/destinations'));

    Http::assertSentCount(2);

    // Bachs' own words in the assertion too, so a failure shows what went wrong.
    expect(['ok' => $result->ok, 'status' => $result->status, 'said' => $result->message])
        ->toBe(['ok' => true, 'status' => 'pending', 'said' => null])
        ->and($result->gatewayId)->toBe('pay_queued123')
        ->and($result->reference)->toBe('BCH-TEST0001')
        ->and((float) $result->data['payload']['amount'])->toBe(15000.0);
});

test('a payout registers the account when no destination is saved for it', function () {
    fakeBachsMerchant(['destinations' => []]);

    $result = app(BachsMerchantService::class)->disburse(new PayoutRequest(
        reference: 'BCH-TEST0002',
        destinationType: 'bank_account',
        amount: 9000.0,
        currency: 'NGN',
        narration: 'Payout from Nextel',
        accountNumber: '0123456789',
        bankCode: '044',
    ));

    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
        && str_contains($request->url(), '/v1/payouts/destinations')
        && ($request->data()['type'] ?? null) === 'bank_account'
        && ($request->data()['bank_code'] ?? null) === '044');

    Http::assertSentCount(3); // destinations · register · payout

    expect(['ok' => $result->ok, 'status' => $result->status, 'said' => $result->message])
        ->toBe(['ok' => true, 'status' => 'pending', 'said' => null])
        ->and($result->data['destination'])->toBe('pd_new123');
});

test('a destination still under review refuses before anything is sent', function () {
    fakeBachsMerchant(['destinations' => [[
        'id' => 'pd_review1',
        'type' => 'bank_account',
        'currency' => 'NGN',
        'status' => 'pending_review',
        'status_reason' => 'Awaiting account review',
        'is_usable' => false,
        'account_number' => '0123456789',
        'bank_code' => '044',
    ]]]);

    $result = app(BachsMerchantService::class)->disburse(new PayoutRequest(
        reference: 'BCH-TEST0003',
        destinationType: 'bank_account',
        amount: 9000.0,
        currency: 'NGN',
        narration: 'Payout from Nextel',
        accountNumber: '0123456789',
        bankCode: '044',
    ));

    expect($result->ok)->toBeTrue()
        ->and($result->status)->toBe('failed')
        ->and($result->message)->toContain('not usable');

    // Only the destination lookup happened — no payout was created, so nothing can be in flight.
    Http::assertSentCount(1);

    Http::assertNotSent(fn (ClientRequest $request): bool => $request->method() === 'POST');
});

test('a payout above the ceiling never reaches the API', function () {
    config(['services.bachs.payout_max_amount' => 10000]);

    Http::fake();

    $result = app(BachsMerchantService::class)->disburse(new PayoutRequest(
        reference: 'BCH-TEST0004',
        destinationType: 'bank_account',
        amount: 15000.0,
        currency: 'NGN',
        narration: 'Payout from Nextel',
        accountNumber: '0123456789',
        bankCode: '044',
    ));

    expect($result->status)->toBe('failed')
        ->and($result->message)->toContain('ceiling');

    Http::assertNothingSent();
});

test('a payout request that times out stays unverified instead of looking failed', function () {
    fakeBachsMerchant(['payout' => 'transport-error']);

    $result = app(BachsMerchantService::class)->disburse(new PayoutRequest(
        reference: 'BCH-TEST0005',
        destinationType: 'bank_account',
        amount: 15000.0,
        currency: 'NGN',
        narration: 'Payout from Nextel',
        accountNumber: '0123456789',
        bankCode: '044',
    ));

    // Unknown, never failed: the payout may have been accepted before the connection died. The
    // reference is what the Send money page keeps on the row, so it can be verified afterwards.
    expect(['ok' => $result->ok, 'status' => $result->status, 'said' => $result->message])
        ->toBe(['ok' => false, 'status' => 'unknown', 'said' => 'The payout request could not be completed — verify the reference before retrying.'])
        ->and($result->data['why'] ?? '')->toContain('cURL error 28');

    // The destination was looked up, then the payout call died — nothing else went out.
    Http::assertSentCount(1);
});

test('a payout answered with a 5xx stays unverified instead of looking failed', function () {
    fakeBachsMerchant(['payout' => 'server-error']);

    $result = app(BachsMerchantService::class)->disburse(new PayoutRequest(
        reference: 'BCH-TEST0006',
        destinationType: 'bank_account',
        amount: 15000.0,
        currency: 'NGN',
        narration: 'Payout from Nextel',
        accountNumber: '0123456789',
        bankCode: '044',
    ));

    expect(['ok' => $result->ok, 'status' => $result->status, 'said' => $result->message])
        ->toBe(['ok' => false, 'status' => 'unknown', 'said' => 'Server error'])
        ->and($result->data['http_status'] ?? null)->toBe(502);

    // Our reference went out as the idempotency key, so the payout can be verified (and never
    // re-sent blindly) afterwards.
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
        && str_contains($request->url(), '/v1/payouts')
        && $request->hasHeader('Idempotency-Key', 'BCH-TEST0006'));
});

/* ------------------------------------------------------------------ verify payout */

test('verifying by reference finds the payout in the newest payouts and returns its state', function () {
    fakeBachsMerchant();

    $result = app(BachsMerchantService::class)->verifyPayout('BCH-MOVED-1');

    expect($result->ok)->toBeTrue()
        ->and($result->status)->toBe('success')
        ->and($result->gatewayId)->toBe('pay_moved1')
        ->and((float) $result->fee)->toBe(100.0);
});

test('a Bachs payout id is looked up directly', function () {
    fakeBachsMerchant();

    $result = app(BachsMerchantService::class)->verifyPayout('pay_abc123');

    expect($result->ok)->toBeTrue()
        ->and($result->status)->toBe('success')
        ->and($result->reference)->toBe('BCH-VERIFY1');

    Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/v1/payouts/pay_abc123'));
});

test('an unknown reference is a definitive answer, never a fake success', function () {
    fakeBachsMerchant();

    $result = app(BachsMerchantService::class)->verifyPayout('BCH-NOTHERE');

    expect($result->ok)->toBeTrue()
        ->and($result->status)->toBe('failed')
        ->and($result->message)->toBe('Bachs has no payout with that reference.');
});

test('an unknown payout id is a definitive answer too', function () {
    fakeBachsMerchant();

    expect(app(BachsMerchantService::class)->verifyPayout('pay_missing')->status)->toBe('failed');
});

test('a verification Bachs cannot answer stays unknown', function () {
    fakeBachsMerchant(['payoutById' => 'server-error']);

    $result = app(BachsMerchantService::class)->verifyPayout('pay_abc123');

    expect($result->ok)->toBeFalse()
        ->and($result->status)->toBe('unknown');
});

/* ------------------------------------------------------------------ resolve account */

test('resolving an account returns the name Bachs holds for it', function () {
    fakeBachsMerchant();

    $result = app(BachsMerchantService::class)->resolveBankAccount('044', '0123456789');

    expect($result['ok'])->toBeTrue()
        ->and($result['account_name'])->toBe('JOHN OKAFOR');

    Http::assertSent(fn (ClientRequest $request): bool => str_contains($request->url(), '/v1/misc/bank-accounts/resolve')
        && ($request->data()['country'] ?? null) === 'NG');
});

test('an unresolved account reports Bachs reason instead of a name', function () {
    fakeBachsMerchant(['resolve' => 'unresolved']);

    $result = app(BachsMerchantService::class)->resolveBankAccount('044', '0000000000');

    expect($result['ok'])->toBeFalse()
        ->and($result['account_name'])->toBeNull()
        ->and($result['message'])->toBe('Account number not found');
});

test('an account resolve Bachs cannot answer reports the failure, never a name', function () {
    fakeBachsMerchant(['resolve' => 'error']);

    $result = app(BachsMerchantService::class)->resolveBankAccount('044', '0123456789');

    expect($result['ok'])->toBeFalse()
        ->and($result['account_name'])->toBeNull()
        ->and($result['message'])->toBe('Could not reach the bank');
});

/* ------------------------------------------------------------------ pages */

test('guests cannot reach any Bachs backoffice page', function () {
    foreach ([
        'backoffice.bachs.balance',
        'backoffice.bachs.history',
        'backoffice.bachs.verify',
        'backoffice.bachs.send-money',
    ] as $route) {
        $this->get(route($route))->assertRedirect(route('backoffice.login'));
    }
});

test('the balance page shows the Bachs balances', function () {
    fakeBachsMerchant();

    $this->actingAs(bachsAdmin(), 'admin')
        ->get(route('backoffice.bachs.balance'))
        ->assertOk()
        ->assertSee('Bachs balance')
        ->assertSee('58,700.00')
        ->assertSee('1,500.00')
        ->assertSee('95,178.20');

    Livewire::test(Balance::class)
        ->assertSet('error', null)
        ->assertSee('NGN');
});

test('the balance page refuses to call the API when the secret key is missing', function () {
    config(['services.bachs.secret_key' => '']);
    Http::fake();

    $this->actingAs(bachsAdmin(), 'admin');

    Livewire::test(Balance::class)
        ->assertSet('balances', [])
        ->assertSee('secret key not set');

    Http::assertNothingSent();
});

test('the history page offers a page size, which the Bachs endpoints accept', function () {
    fakeBachsMerchant();

    $this->actingAs(bachsAdmin(), 'admin')
        ->get(route('backoffice.bachs.history'))
        ->assertOk()
        ->assertSee('Per page');

    Livewire::test(BalanceHistory::class)->assertSet('error', null);
});

test('a bad date filter is rejected before the API is called', function () {
    fakeBachsMerchant();

    $this->actingAs(bachsAdmin(), 'admin');

    Livewire::test(BalanceHistory::class)
        ->set('dateFrom', '2026-10-10')
        ->set('dateTo', '2026-10-01')
        ->call('applyFilters')
        ->assertHasErrors('dateTo');
});

/* ------------------------------------------------------------------ send money */

test('send money is super-admin only at the route level', function () {
    fakeBachsMerchant();

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get(route('backoffice.bachs.send-money'))
        ->assertForbidden();
});

test('a regular admin cannot use the send money component either', function () {
    fakeBachsMerchant();

    $this->actingAs(Admin::factory()->create(), 'admin');

    // The mount guard aborts; Livewire renders the error page instead of the payout form.
    expect(Livewire::test(SendMoney::class)->html())->not->toContain('Review payout');
});

test('a payout reference is generated with the BCH prefix', function () {
    $this->actingAs(bachsAdmin(), 'admin');

    expect(Livewire::test(SendMoney::class)->get('reference'))->toStartWith('BCH-')
        ->and(BachsPayout::generateReference())->toStartWith('BCH-');
});

test('resolving an account fills in the verified name', function () {
    fakeBachsMerchant();

    $this->actingAs(bachsAdmin(), 'admin');

    Livewire::test(SendMoney::class)
        ->set('currency', 'NGN')
        ->set('bankCode', '044')
        ->set('accountNumber', '0123456789')
        ->call('resolveAccount')
        ->assertSet('accountName', 'JOHN OKAFOR')
        ->assertSet('accountResolved', true);
});

test('the payout form cannot be submitted with an invalid amount', function () {
    fakeBachsMerchant();

    $this->actingAs(bachsAdmin(), 'admin');

    bachsPayoutForm('0')
        ->call('confirm')
        ->assertHasErrors('amount')
        ->assertSet('showConfirm', false);

    Http::assertNothingSent();
});

test('the form shows the Bachs ceiling, not another manager\'s', function () {
    config(['services.bachs.payout_max_amount' => 250000, 'services.paystack.payout_max_amount' => 1000000]);
    fakeBachsMerchant();

    $this->actingAs(bachsAdmin(), 'admin')
        ->get(route('backoffice.bachs.send-money'))
        ->assertOk()
        ->assertSee('250,000.00');
});

test('a currency outside the configured payout list is rejected before any call', function () {
    fakeBachsMerchant();

    $this->actingAs(bachsAdmin(), 'admin');

    bachsPayoutForm()->set('currency', 'KES')->call('confirm')->assertHasErrors('currency');

    Http::assertNothingSent();
});

test('a sent payout is recorded with its destination and Bachs own words', function () {
    fakeBachsMerchant();

    $admin = bachsAdmin();
    $this->actingAs($admin, 'admin');

    bachsPayoutForm()->call('confirm')->assertSet('showConfirm', true)->call('send');

    $payout = BachsPayout::query()->firstOrFail();

    expect($payout->status)->toBe('pending')
        ->and($payout->provider)->toBe('bachs')
        ->and($payout->gateway_reference)->toBe('pay_queued123')
        ->and($payout->destination_id)->toBe('pd_saved123')
        ->and($payout->bank_name)->toBe('Access Bank')
        ->and((float) $payout->amount)->toBe(15000.0)
        ->and((float) $payout->fee)->toBe(50.0)
        ->and($payout->admin_id)->toBe($admin->id)
        ->and($payout->initiated_at)->not->toBeNull()
        ->and($payout->reference)->toStartWith('BCH-')
        ->and($payout->isFinal())->toBeFalse();
});

test('a definitive rejection is stored as failed with Bachs message', function () {
    fakeBachsMerchant(['payout' => 'reject']);

    $this->actingAs(bachsAdmin(), 'admin');

    bachsPayoutForm()->call('send');

    $payout = BachsPayout::query()->firstOrFail();

    expect($payout->status)->toBe('failed')
        ->and($payout->error)->toBe('Insufficient balance');
});

test('an unanswered payout is stored unverified, with the reference kept for checking', function () {
    fakeBachsMerchant(['payout' => 'server-error']);

    $this->actingAs(bachsAdmin(), 'admin');

    bachsPayoutForm()->call('send');

    $payout = BachsPayout::query()->firstOrFail();

    expect($payout->status)->toBe('unknown')
        ->and($payout->reference)->toStartWith('BCH-');
});

test('an identical in-flight payout is blocked instead of sent twice', function () {
    fakeBachsMerchant();

    $this->actingAs(bachsAdmin(), 'admin');

    bachsPayoutForm()->call('confirm')->call('send');
    bachsPayoutForm()->call('confirm')->call('send');

    expect(BachsPayout::query()->count())->toBe(1);

    // One payout = exactly two calls (the destination lookup, then the payout); the second attempt is
    // answered by looking at the payout still in flight, so it never reaches Bachs.
    Http::assertSentCount(2);
});

test('verifying a completed payout reconciles the local audit row', function () {
    fakeBachsMerchant(['payoutStatus' => 'completed']);

    $this->actingAs(bachsAdmin(), 'admin');

    bachsPayoutForm()->call('send');

    $payout = BachsPayout::query()->firstOrFail();

    Livewire::test(VerifyPayout::class)
        ->set('reference', $payout->reference)
        ->call('verify')
        ->assertSet('result.status', 'success')
        ->assertSet('localPayoutId', $payout->id);

    $payout->refresh();

    expect($payout->status)->toBe('success')
        ->and((float) $payout->fee)->toBe(50.0)
        ->and($payout->verified_at)->not->toBeNull()
        ->and($payout->last_verified_at)->not->toBeNull();
});

/* ------------------------------------------------------------------ settings & sidebar */

test('the routing card offers Bachs for every purchase, and its keys save', function () {
    $this->actingAs(bachsAdmin(), 'admin');

    $page = $this->get(route('backoffice.settings.services'))
        ->assertOk()
        ->assertSee('Active payment manager');

    // Every purchase's selector offers Bachs (disabled until its key is set), plus the
    // active-manager selector — and nothing else on the page offers it.
    expect(substr_count($page->getContent(), 'value="bachs"'))->toBe(count(ChargePurpose::cases()) + 1);

    Livewire::test(ServicesSetting::class)
        ->set('activePaymentManager', 'bachs')
        ->set('bachsBaseUrl', 'https://api.bachs.io')
        ->set('bachsSecretKey', 'sk_live_bachs_saved')
        ->set('bachsWebhookSecret', 'whsec_saved')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('settings', ['key' => 'active_payment_manager', 'value' => 'bachs']);
    $this->assertDatabaseHas('settings', ['key' => 'bachs_secret_key', 'value' => 'sk_live_bachs_saved']);
    $this->assertDatabaseHas('settings', ['key' => 'bachs_webhook_secret', 'value' => 'whsec_saved']);

    // …and the saved keys reach config('services.bachs.*'), which is what the drivers read
    // (the same mapping the SettingServiceProvider applies on every boot).
    $mapped = SettingMapper::toConfigArray(transform_setting(Setting::all()));

    expect($mapped['services.bachs.secret_key'])->toBe('sk_live_bachs_saved')
        ->and($mapped['services.bachs.webhook_secret'])->toBe('whsec_saved')
        ->and($mapped['services.bachs.base_url'])->toBe('https://api.bachs.io')
        ->and($mapped['settings.payment.active_manager'])->toBe('bachs');
});

test('the Bachs references alias opens the shared payment-reference ledger', function () {
    $this->actingAs(bachsAdmin(), 'admin')
        ->get(route('backoffice.bachs.references'))
        ->assertRedirect(route('backoffice.payment-references'));
});

test('the sidebar shows the Bachs wallet pages only when Bachs is the active manager', function () {
    config(['settings.payment.active_manager' => 'bachs']);

    $this->actingAs(bachsAdmin(), 'admin')
        ->get(route('backoffice.dashboard'))
        ->assertSee('Bachs balance')
        ->assertSee('Bachs history')
        ->assertSee('Send money')
        ->assertSee('Verify payout')
        ->assertSee('Payment references')
        ->assertDontSee('Paystack balance')
        ->assertDontSee('Korapay balance')
        ->assertDontSee('Flutterwave balance');

    config(['settings.payment.active_manager' => 'korapay']);

    $this->actingAs(bachsAdmin(), 'admin')
        ->get(route('backoffice.dashboard'))
        ->assertDontSee('Bachs balance')
        ->assertSee('Korapay balance');
});

test('a regular admin sees the Bachs pages but not the one that moves money', function () {
    config(['settings.payment.active_manager' => 'bachs']);

    $this->actingAs(Admin::factory()->regular()->create(), 'admin')
        ->get(route('backoffice.dashboard'))
        ->assertSee('Bachs balance')
        ->assertSee('Bachs history')
        ->assertSee('Verify payout')
        ->assertDontSee('Send money');
});

test('every Bachs page stays reachable whichever manager is active', function () {
    config(['settings.payment.active_manager' => 'korapay']);
    fakeBachsMerchant();

    $this->actingAs(bachsAdmin(), 'admin')
        ->get(route('backoffice.bachs.balance'))
        ->assertOk()
        ->assertSee('Bachs balance');
});

/* ------------------------------------------------------------------ ledger & schema */

test('a Bachs charge settles into the shared payment-reference ledger', function () {
    $this->actingAs(bachsAdmin(), 'admin');

    PaymentReference::create([
        'reference' => 'NXT-BCH-0001',
        'provider' => 'bachs',
        'gateway_reference' => 'chk_shared123',
        'type' => TransactionType::STORAGE_PURCHASE->value,
        'currency' => 'NGN',
        'amount_ngn' => 7500,
        'settled_at' => now(),
    ]);

    $this->get(route('backoffice.payment-references'))
        ->assertOk()
        ->assertSee('Payment references')
        ->assertSee('NXT-BCH-0001');

    Livewire::test(ListPaymentReferences::class)
        ->set('search', 'NXT-BCH-0001')
        ->assertSee('NXT-BCH-0001');
});

test('the Bachs payout table carries the whole audit trail', function () {
    $columns = Schema::getColumnListing('bachs_payouts');

    foreach ([
        'id', 'admin_id', 'reference', 'provider', 'gateway_reference', 'trace_id', 'destination_type',
        'currency', 'debit_currency', 'amount', 'fee', 'status', 'narration', 'note', 'bank_code',
        'bank_name', 'account_number', 'account_name', 'customer_name', 'customer_email',
        'destination_id', 'message', 'request', 'response', 'error', 'ip_address',
        'initiated_at', 'verified_at', 'last_verified_at',
    ] as $column) {
        expect($columns)->toContain($column);
    }

    // One audit table per manager: a Bachs payout never lands in another manager's table.
    expect(BachsPayout::query()->count())->toBe(0)
        ->and(PaymentReference::query()->count())->toBe(0);
});

test('all four payout models share the trait behaviour', function () {
    foreach ([BachsPayout::class, PaystackPayout::class, KorapayPayout::class, FlutterwavePayout::class] as $model) {
        $row = new $model(['status' => 'unknown']);

        expect($row->isFinal())->toBeFalse()
            ->and($row->statusTone())->toBe('warning')
            ->and($row->statusLabel())->toBe('Unverified');
    }

    expect(PaymentProvider::values())->toContain('bachs');
});
