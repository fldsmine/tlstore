<?php

use App\Livewire\Backoffice\Korapay\BalanceHistory;
use App\Livewire\Backoffice\Korapay\ListPaymentReferences;
use App\Livewire\Backoffice\Korapay\SendMoney;
use App\Livewire\Backoffice\Korapay\Balance;
use App\Livewire\Backoffice\Korapay\VerifyPayout;
use App\Models\Admin;
use App\Models\Bank;
use App\Models\Currency;
use App\Models\KorapayPayout;
use App\Models\PaymentReference;
use App\Models\Transaction;
use App\Models\User;
use App\Enums\TransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Currency::create(['code' => 'NGN', 'name' => 'Nigerian Naira', 'symbol' => '₦', 'usd_exchange_rate' => 1500, 'is_active' => true]);

    Bank::create(['name' => 'Guaranty Trust Bank', 'code' => '058', 'country' => 'NG', 'type' => 'commercial', 'is_active' => true]);
    Bank::create(['name' => 'Access Bank', 'code' => '044', 'country' => 'NG', 'type' => 'commercial', 'is_active' => true]);

    config([
        'services.korapay.base_url' => 'https://api.korapay.com',
        'services.korapay.secret_key' => 'sk_test_korapay',
        'services.korapay.currency' => 'NGN',
        'services.korapay.payout_currencies' => ['NGN'],
        'services.korapay.payout_max_amount' => 1000000,
    ]);
});

function korapayAdmin(): Admin
{
    return Admin::factory()->super()->create();
}

/** Fake the three Korapay merchant endpoints the backoffice uses. */
function fakeKorapayMerchant(array $overrides = []): void
{
    Http::fake(function (ClientRequest $request) use ($overrides) {
        $url = $request->url();

        if (str_contains($url, 'balances/history')) {
            // The filtered row is what the URL-filter test looks for; the unfiltered fixture is the
            // Korapay document's own example.
            return Http::response($overrides['history'] ?? (str_contains($url, 'direction=debit') ? [
                'status' => true,
                'message' => 'success',
                'has_more' => false,
                'data' => [[
                    'pointer' => 'cus_filter',
                    'amount' => '10.00',
                    'currency' => 'USD',
                    'balance_before' => '1.00',
                    'balance_after' => '11.00',
                    'date_created' => '2023-09-08 07:20:00',
                    'description' => 'Filtered row',
                    'direction' => 'debit',
                    'source' => 'payout',
                    'source_reference' => 'KPY-FILTER',
                ]],
            ] : [
                'status' => true,
                'message' => 'success',
                'has_more' => true,
                'data' => [
                    [
                        'pointer' => 'cus_1B89',
                        'amount' => '3000.00',
                        'currency' => 'NGN',
                        'balance_before' => '10811758.99',
                        'balance_after' => '10808758.99',
                        'date_created' => '2023-09-08 07:20:00',
                        'description' => 'Chargeback deduction for KPY-CM-s2Ao7BlFCm5FdfG',
                        'direction' => 'debit',
                        'source' => 'chargeback',
                        'source_reference' => 'KPY-CHB-3Xuop6zkvlsyP',
                    ],
                ],
            ]));
        }

        if (str_contains($url, 'misc/banks/resolve')) {
            return Http::response($overrides['resolve'] ?? [
                'status' => true,
                'message' => 'Request Completed',
                'data' => [
                    'bank_name' => 'Guaranty Trust Bank',
                    'bank_code' => '058',
                    'account_number' => '2158634852',
                    'account_name' => 'EBUKA CIROMA OLADEMJI',
                ],
            ]);
        }

        if (str_contains($url, 'transactions/disburse')) {
            return Http::response($overrides['disburse'] ?? [
                'status' => true,
                'message' => 'transfer initiated successfully',
                'data' => [
                    'amount' => '2500.00',
                    'fee' => '25.00',
                    'currency' => 'NGN',
                    'status' => 'processing',
                    'reference' => 'KPY-D-t74azVrw9oPLtv9',
                    'narration' => 'Payout from Nextel',
                    'message' => 'Payout processing',
                ],
            ], $overrides['disburse_status'] ?? 200);
        }

        if (preg_match('#transactions/([^/?]+)#', $url, $matches)) {
            return Http::response($overrides['verify'] ?? [
                'status' => true,
                'message' => 'Transaction retrieved successfully',
                'data' => [
                    'reference' => urldecode($matches[1]),
                    'status' => 'success',
                    'amount' => 1000,
                    'fee' => 15.31,
                    'currency' => 'NGN',
                    'narration' => 'Test transfer',
                    'trace_id' => '521853970021533363527535291465',
                    'message' => 'Payout successful',
                    'customer' => ['name' => 'Test User', 'email' => 'test@example.com'],
                ],
            ], $overrides['verify_status'] ?? 200);
        }

        return Http::response([
            'status' => true,
            'message' => 'success',
            'data' => [
                'NGN' => ['pending_balance' => 100000.78, 'available_balance' => 400300.90],
                'USD' => ['pending_balance' => 10000.78, 'available_balance' => 10093756.06, 'issuing_balance' => 17639.67],
            ],
        ]);
    });
}

test('guests cannot reach any Korapay backoffice page', function () {
    foreach (['backoffice.korapay.balance', 'backoffice.korapay.history', 'backoffice.korapay.verify', 'backoffice.korapay.references', 'backoffice.korapay.send-money'] as $route) {
        $this->get(route($route))->assertRedirect(route('backoffice.login'));
    }
});

test('the balance page shows the merchant wallet balances from Korapay', function () {
    fakeKorapayMerchant();

    $this->actingAs(korapayAdmin(), 'admin')
        ->get(route('backoffice.korapay.balance'))
        ->assertOk()
        ->assertSee('Korapay balance')
        ->assertSee('400,300.90')
        ->assertSee('100,000.78')
        ->assertSee('NGN')
        ->assertSee('Test mode');

    Livewire::test(Balance::class)
        ->assertSet('error', null)
        ->assertSee('17,639.67'); // USD issuing balance
});

test('the balance page degrades gracefully when Korapay rejects the request', function () {
    Http::fake(['*' => Http::response(['status' => false, 'message' => 'Invalid key'], 401)]);

    $this->actingAs(korapayAdmin(), 'admin');

    Livewire::test(Balance::class)
        ->assertSet('balances', [])
        ->assertSet('error', 'Invalid key')
        ->assertSee('Balance unavailable');
});

test('only super admins can open Send money', function () {
    fakeKorapayMerchant();

    $this->actingAs(Admin::factory()->regular()->create(), 'admin')
        ->get(route('backoffice.korapay.send-money'))
        ->assertForbidden();

    $this->actingAs(korapayAdmin(), 'admin')
        ->get(route('backoffice.korapay.send-money'))
        ->assertOk()
        ->assertSee('Payout details')
        ->assertSee('Super admin only');
});

test('the Send money component itself refuses to mount for a regular admin', function () {
    // Livewire actions reach the update endpoint, NOT the page route, so the super-admin middleware
    // never runs for them: without this guard a regular admin could hold a usable snapshot of a
    // money-moving form. Either the mount aborts or the framework error page is rendered — the form
    // markup must never reach the browser.
    fakeKorapayMerchant();

    $this->actingAs(Admin::factory()->regular()->create(), 'admin');

    $html = null;

    try {
        $html = Livewire::test(SendMoney::class)->html();
    } catch (\Throwable) {
        $html = null;
    }

    expect($html)->not->toContain('Payout details');
});

test('a super admin sending money disburses through Korapay and records the payout', function () {
    fakeKorapayMerchant();

    $admin = korapayAdmin();

    $this->actingAs($admin, 'admin');

    $component = Livewire::test(SendMoney::class)
        ->set('currency', 'NGN')
        ->set('bankCode', '058')
        ->set('accountNumber', '2158634852')
        ->set('accountName', 'EBUKA CIROMA OLADEMJI')
        ->set('amount', '2500')
        ->set('narration', 'Vendor settlement')
        ->set('note', 'March payout')
        ->set('customerEmail', 'vendor@example.com')
        ->call('confirm')
        ->assertSet('showConfirm', true)
        ->call('send')
        ->assertSet('showConfirm', false)
        ->assertHasNoErrors();

    $payout = KorapayPayout::query()->sole();

    expect($payout->status)->toBe('processing')
        ->and($payout->admin_id)->toBe($admin->id)
        ->and((float) $payout->amount)->toBe(2500.0)
        ->and($payout->currency)->toBe('NGN')
        ->and($payout->bank_code)->toBe('058')
        ->and($payout->bank_name)->toBe('Guaranty Trust Bank')
        ->and($payout->account_number)->toBe('2158634852')
        ->and($payout->account_name)->toBe('EBUKA CIROMA OLADEMJI')
        ->and($payout->note)->toBe('March payout')
        ->and($payout->initiated_at)->not->toBeNull()
        ->and($component->get('lastReference'))->toBe($payout->reference);

    // The payload Korapay actually received: bank rail, real bank code, our unique reference.
    Http::assertSent(function (ClientRequest $request) use ($payout) {
        if (! str_contains($request->url(), 'transactions/disburse')) {
            return false;
        }

        $body = $request->data();

        return $body['reference'] === $payout->reference
            && $body['destination']['type'] === 'bank_account'
            && $body['destination']['currency'] === 'NGN'
            && (float) $body['destination']['amount'] === 2500.0
            && $body['destination']['bank_account']['bank'] === '058'
            && $body['destination']['bank_account']['account'] === '2158634852'
            && $body['destination']['customer']['email'] === 'vendor@example.com';
    });
});

test('a payout rejected by Korapay is recorded as failed', function () {
    fakeKorapayMerchant(['disburse_status' => 422, 'disburse' => ['status' => false, 'message' => 'Insufficient balance']]);

    $this->actingAs(korapayAdmin(), 'admin');

    Livewire::test(SendMoney::class)
        ->set('bankCode', '044')
        ->set('accountNumber', '0123456789')
        ->set('amount', '500')
        ->set('narration', 'Test payout')
        ->set('customerEmail', 'ops@example.com')
        ->call('confirm')
        ->call('send');

    $payout = KorapayPayout::query()->sole();

    expect($payout->status)->toBe('failed')
        ->and($payout->error)->toBe('Insufficient balance');
});

test('an unanswered payout is never recorded as failed — it stays unverified for re-checking', function () {
    // Korapay's own guidance: a 5xx/504 does NOT mean the payout failed, so it must be verified
    // by reference rather than re-sent.
    fakeKorapayMerchant(['disburse_status' => 504, 'disburse' => ['status' => false, 'message' => 'Gateway timeout']]);

    $this->actingAs(korapayAdmin(), 'admin');

    Livewire::test(SendMoney::class)
        ->set('bankCode', '058')
        ->set('accountNumber', '2158634852')
        ->set('amount', '700')
        ->set('narration', 'Test payout')
        ->set('customerEmail', 'ops@example.com')
        ->call('confirm')
        ->call('send');

    $payout = KorapayPayout::query()->sole();

    expect($payout->status)->toBe('unknown')
        ->and($payout->isFinal())->toBeFalse()
        ->and($payout->response['http_status'] ?? null)->toBe(504);
});

test('an identical in-flight payout is not sent twice', function () {
    fakeKorapayMerchant();

    $admin = korapayAdmin();

    KorapayPayout::create([
        'admin_id' => $admin->id,
        'reference' => 'KPO-EXISTING',
        'currency' => 'NGN',
        'amount' => 2500,
        'status' => 'processing',
        'bank_code' => '058',
        'account_number' => '2158634852',
        'narration' => 'First attempt',
        'created_at' => now()->subMinute(),
    ]);

    $this->actingAs($admin, 'admin');

    Livewire::test(SendMoney::class)
        ->set('bankCode', '058')
        ->set('accountNumber', '2158634852')
        ->set('amount', '2500')
        ->set('narration', 'Second attempt')
        ->set('customerEmail', 'ops@example.com')
        ->call('confirm')
        ->call('send')
        ->assertSet('showConfirm', false);

    expect(KorapayPayout::query()->count())->toBe(1);
    Http::assertNotSent(fn (ClientRequest $request) => str_contains($request->url(), 'transactions/disburse'));
});

test('resolving a destination account fills in the bank-confirmed account name', function () {
    fakeKorapayMerchant();

    $this->actingAs(korapayAdmin(), 'admin');

    Livewire::test(SendMoney::class)
        ->set('bankCode', '058')
        ->set('accountNumber', '2158634852')
        ->call('resolveAccount')
        ->assertSet('accountName', 'EBUKA CIROMA OLADEMJI')
        ->assertSet('accountResolved', true);
});

test('the payout verification page reports Korapay status and updates the local record', function () {
    fakeKorapayMerchant();

    $admin = korapayAdmin();

    $payout = KorapayPayout::create([
        'admin_id' => $admin->id,
        'reference' => 'KPO-VERIFYME',
        'currency' => 'NGN',
        'amount' => 1000,
        'status' => 'unknown',
        'bank_code' => '058',
        'account_number' => '2158634852',
        'narration' => 'Awaiting confirmation',
    ]);

    $this->actingAs($admin, 'admin');

    Livewire::test(VerifyPayout::class)
        ->set('reference', 'KPO-VERIFYME')
        ->call('verify')
        ->assertSet('result.status', 'success')
        ->assertSet('result.amount', 1000.0)
        ->assertSet('result.trace_id', '521853970021533363527535291465')
        ->assertSet('localPayoutId', $payout->id)
        ->assertSee('Payout successful');

    $payout->refresh();

    expect($payout->status)->toBe('success')
        ->and($payout->trace_id)->toBe('521853970021533363527535291465')
        ->and($payout->verified_at)->not->toBeNull()
        ->and($payout->last_verified_at)->not->toBeNull();
});

test('verification never reports a payout as failed when Korapay cannot be reached', function () {
    fakeKorapayMerchant(['verify_status' => 503, 'verify' => ['status' => false, 'message' => 'Service unavailable']]);

    $this->actingAs(korapayAdmin(), 'admin');

    Livewire::test(VerifyPayout::class)
        ->set('reference', 'KPO-SLOWONE')
        ->call('verify')
        ->assertSet('result.ok', false)
        ->assertSet('result.status', 'unknown')
        ->assertSee('do not treat this payout as failed');
});

test('the balance history page shows movements from Korapay', function () {
    fakeKorapayMerchant();

    $this->actingAs(korapayAdmin(), 'admin');

    Livewire::test(BalanceHistory::class)
        ->assertSet('error', null)
        ->assertSee('Chargeback deduction')
        ->assertSee('KPY-CHB-3Xuop6zkvlsyP')
        ->assertSee('10,808,758.99')
        ->assertSee('cus_1B89');
});

test('balance history reads its filters from the URL on first load', function () {
    // The page fetches during mount(), so a ?currency=USD link has to be applied BEFORE that call —
    // otherwise the first page would show NGN movements under a USD filter.
    fakeKorapayMerchant();

    $this->actingAs(korapayAdmin(), 'admin')
        ->get(route('backoffice.korapay.history', ['currency' => 'USD', 'direction' => 'debit', 'limit' => 50]))
        ->assertOk()
        ->assertSee('Filtered row')
        ->assertSee('KPY-FILTER');

    Http::assertSent(function (ClientRequest $request) {
        if (! str_contains($request->url(), 'balances/history')) {
            return false;
        }

        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query['currency'] ?? null) === 'USD'
            && ($query['direction'] ?? null) === 'debit'
            && (int) ($query['limit'] ?? 0) === 50;
    });

    // A page size outside the allow-list falls back to the default instead of reaching the API.
    $this->get(route('backoffice.korapay.history', ['limit' => 999]))->assertOk();

    Http::assertSent(function (ClientRequest $request) {
        if (! str_contains($request->url(), 'balances/history')) {
            return false;
        }

        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return (int) ($query['limit'] ?? 0) === 25;
    });
});

test('balance history pages forward with the opaque cursor and can go back', function () {
    fakeKorapayMerchant();

    $this->actingAs(korapayAdmin(), 'admin');

    $component = Livewire::test(BalanceHistory::class);

    $component->call('nextPage');

    Http::assertSent(function (ClientRequest $request) {
        if (! str_contains($request->url(), 'balances/history')) {
            return false;
        }

        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        // Korapay pages with `starting_after` = the pointer of the last row on the previous page.
        return ($query['starting_after'] ?? null) === 'cus_1B89';
    });

    $component->call('previousPage')->assertSet('cursorIndex', 0);
});

test('the payment reference ledger lists settled references', function () {
    fakeKorapayMerchant();

    $user = User::factory()->create(['username' => 'adaokafor', 'email' => 'ada@example.com']);

    $transaction = Transaction::create([
        'user_id' => $user->id,
        'type' => TransactionType::STORAGE_PURCHASE,
        'direction' => 'debit',
        'amount' => 5,
        'status' => 'completed',
        'reference' => 'STP-LEDGERROW',
        'provider' => 'korapay',
        'meta' => ['amount_ngn' => 7500],
    ]);

    PaymentReference::create([
        'reference' => 'STP-LEDGERROW',
        'transaction_id' => $transaction->id,
        'user_id' => $user->id,
        'provider' => 'korapay',
        'gateway_reference' => 'kpy_ledger_row',
        'type' => TransactionType::STORAGE_PURCHASE->value,
        'currency' => 'NGN',
        'amount_ngn' => 7500,
        'settled_at' => now(),
    ]);

    $this->actingAs(korapayAdmin(), 'admin');

    Livewire::test(ListPaymentReferences::class)
        ->assertSee('STP-LEDGERROW')
        ->assertSee('kpy_ledger_row')
        ->assertSee('@adaokafor')
        ->assertSee('7,500.00')
        ->assertSee('Storage Purchase');
});
