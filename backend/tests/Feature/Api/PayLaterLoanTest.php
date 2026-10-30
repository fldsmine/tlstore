<?php

use App\Enums\CouponType;
use App\Enums\LoanStatus;
use App\Enums\PayLaterStatus;
use App\Models\AdditionalMinutePlan;
use App\Models\Coupon;
use App\Models\Loan;
use App\Models\MinuteCredit;
use App\Models\Transaction;
use App\Livewire\Dashboard\Agent\AgentDashboard;
use App\Livewire\Dashboard\Finance\Loan as LoanPage;
use App\Services\CouponMintingService;
use App\Services\Payments\ChargeSettlement;
use App\Enums\TransactionType;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function payLaterUser(): \App\Models\User
{
    return activatedUser(['transaction_pin' => '1234']);
}

/** @return array<string, mixed> */
function loanApplicationPayload(array $overrides = []): array
{
    return array_merge([
        'loan_type' => 'student',
        'amount' => 50,
        'due_date' => now()->addDays(5)->toDateString(),
        'agreed' => true,
        'disbursement_account_name' => 'Test Applicant',
        'disbursement_account_number' => '1234567890',
        'disbursement_bank_name' => 'Example Bank',
    ], $overrides);
}

function fakeKorapayForLoanFee(string $currency = 'NGN', string $verificationStatus = 'success'): void
{
    $expected = [];

    Http::fake(function (ClientRequest $request) use (&$expected, $currency, $verificationStatus) {
        if (str_contains($request->url(), 'charges/initialize')) {
            $body = $request->data();
            $expected[$body['reference']] = (float) $body['amount'];

            return Http::response([
                'status' => true,
                'data' => ['checkout_url' => 'https://checkout.korapay.com/'.$body['reference'], 'reference' => 'kpy_'.$body['reference']],
            ]);
        }

        $reference = basename((string) parse_url($request->url(), PHP_URL_PATH));
        if ($verificationStatus === 'unavailable') {
            return Http::response(['status' => false], 503);
        }

        return Http::response([
            'status' => true,
            'data' => [
                'status' => $verificationStatus,
                'amount' => $expected[$reference] ?? 0.0,
                'currency' => $currency,
                'reference' => $reference,
            ],
        ]);
    });
}

test('pay later status starts clean', function () {
    $user = payLaterUser();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/pay-later')
        ->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.has_outstanding', false)
        ->assertJsonCount(0, 'data.credits');
});

test('eligible users can activate minutes on credit', function () {
    config(['settings.paylater.audience' => 'everyone']);

    $user = payLaterUser();
    $plan = AdditionalMinutePlan::factory()->create(['daily_call_minutes' => 15, 'paylater_duration_days' => 14]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/pay-later', ['plan_id' => $plan->id, 'pin' => '1234', 'agreed' => true])
        ->assertCreated();

    $credit = MinuteCredit::where('user_id', $user->id)->first();

    expect($credit)->not->toBeNull()
        ->and($credit->status)->toBe(PayLaterStatus::ACTIVE)
        ->and($user->refresh()->minuteTopUps()->count())->toBe(1);
});

test('a wrong pin blocks the application', function () {
    config(['settings.paylater.audience' => 'everyone']);

    $user = payLaterUser();
    $plan = AdditionalMinutePlan::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/pay-later', ['plan_id' => $plan->id, 'pin' => '0000', 'agreed' => true])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['pin']);

    expect(MinuteCredit::count())->toBe(0);
});

test('an outstanding credit can be repaid with an extra-minutes token', function () {
    $user = payLaterUser();
    $plan = AdditionalMinutePlan::factory()->create();
    $topUp = $user->minuteTopUps()->create(['daily_call_minutes' => 15, 'expires_at' => now()->addDays(30)]);
    $credit = MinuteCredit::factory()->create([
        'user_id' => $user->id,
        'additional_minute_plan_id' => $plan->id,
        'minute_top_up_id' => $topUp->id,
    ]);
    $coupon = Coupon::factory()->create([
        'type' => CouponType::EXTRA_MINUTES,
        'region' => 'nigeria',
        'additional_minute_plan_id' => $plan->id,
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/pay-later/'.$credit->id.'/repay', ['token' => $coupon->code])
        ->assertOk()
        ->assertJsonPath('data.credit.status', PayLaterStatus::REPAID->value);

    expect($coupon->refresh()->used_at)->not->toBeNull();
});

test('loans show the terms and user history', function () {
    $user = payLaterUser();
    Loan::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/loans')
        ->assertOk();

    expect($response->json('data.enabled'))->toBeTrue()
        ->and($response->json('data.max_amount_usd'))->toEqual(100.0)
        ->and($response->json('data.min_amount_usd'))->toEqual(50.0)
        ->and($response->json('data.loan_types.personal'))->toBe('Personal Loan')
        ->and($response->json('data.loan_types.travel'))->toBe('Travel Loan')
        ->and(count($response->json('data.loans')))->toBe(1)
        ->and($response->json('data.loans.0.receipt_url'))->toContain('/downloads/loan-receipt/')
        ->and($response->json('data.loans.0.receipt_png_url'))->toContain('format=png');
});

test('approved loan status stays hidden until the paid guarantor form is submitted', function () {
    fakeKorapayForLoanFee();
    config([
        'settings.loan.guarantor_form_fee_ngn' => 1500,
        'settings.payment.loan_application_fee_provider' => 'korapay',
        'services.korapay.base_url' => 'https://api.korapay.com',
        'services.korapay.secret_key' => 'sk_test_korapay',
        'services.korapay.currency' => 'NGN',
    ]);

    $user = payLaterUser();
    $loan = Loan::factory()->create(['user_id' => $user->id, 'status' => LoanStatus::APPROVED]);
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/loans')
        ->assertOk()
        ->assertJsonPath('data.loans.0.status', 'guarantor_form_required')
        ->assertJsonPath('data.loans.0.status_label', 'Guarantor form required')
        ->assertJsonPath('data.loans.0.guarantor_form.required', true)
        ->assertJsonPath('data.loans.0.guarantor_form.fee_amount_ngn', 1500.0);

    $paymentResponse = $this->postJson('/api/v1/loans/'.$loan->id.'/guarantor-form/payment', [])
        ->assertOk()
        ->assertJsonPath('data.fee_ngn', 1500.0);
    $reference = $paymentResponse->json('data.reference');
    $payment = Transaction::where('reference', $reference)->firstOrFail();

    expect($payment->type)->toBe(TransactionType::LOAN_GUARANTOR_FORM_FEE)
        ->and($payment->status)->toBe('pending')
        ->and($payment->meta['loan_id'])->toBe($loan->id);

    $this->deleteJson('/api/v1/loans/'.$loan->id.'/guarantor-form/payment/'.$reference)
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');
    expect($payment->fresh()->status)->toBe('completed')
        ->and($loan->fresh()->guarantor_form_fee_paid_at)->not->toBeNull();

    $this->getJson('/api/v1/loans')
        ->assertOk()
        ->assertJsonPath('data.loans.0.status', 'guarantor_form_required')
        ->assertJsonPath('data.loans.0.guarantor_form.fee_paid', true)
        ->assertJsonPath('data.loans.0.guarantor_form.required', true);

    $submitResponse = $this->postJson('/api/v1/loans/'.$loan->id.'/guarantor-form', [
        'full_name' => 'Jamie Guarantor',
        'relationship' => 'Sibling',
        'phone' => '+234 801 234 5678',
        'email' => 'jamie@example.test',
        'residential_address' => '10 Example Street, Port Harcourt',
        'occupation' => 'Legacy field should be discarded',
        'employer' => 'Legacy field should be discarded',
        'signature' => 'Jamie Guarantor',
        'consent' => true,
    ])
        ->assertOk()
        ->assertJsonPath('data.loan.status', LoanStatus::APPROVED->value)
        ->assertJsonPath('data.loan.status_label', 'Approved')
        ->assertJsonPath('data.loan.guarantor_form.required', false);

    expect($submitResponse->json('data.loan.guarantor_form.view_url'))->toContain('/dashboard/view-guarantor-form/')
        ->and($submitResponse->json('data.loan.guarantor_form.download_url'))->toContain('/downloads/loan-guarantor-form/');

    $completed = $loan->fresh();
    expect($completed->hasCompletedGuarantorForm())->toBeTrue()
        ->and($completed->guarantor_form_data['full_name'])->toBe('Jamie Guarantor')
        ->and($completed->guarantor_form_data['consent'])->toBeTrue()
        ->and(array_key_exists('occupation', $completed->guarantor_form_data))->toBeFalse()
        ->and(array_key_exists('employer', $completed->guarantor_form_data))->toBeFalse();
});

test('cancellation keeps a guarantor-form charge while the provider still reports it pending', function () {
    fakeKorapayForLoanFee('NGN', 'pending');
    config([
        'settings.loan.guarantor_form_fee_ngn' => 1500,
        'settings.payment.loan_application_fee_provider' => 'korapay',
        'services.korapay.base_url' => 'https://api.korapay.com',
        'services.korapay.secret_key' => 'sk_test_korapay',
        'services.korapay.currency' => 'NGN',
    ]);

    $user = payLaterUser();
    $loan = Loan::factory()->create(['user_id' => $user->id, 'status' => LoanStatus::APPROVED]);
    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/loans/'.$loan->id.'/guarantor-form/payment', [])
        ->assertOk();
    $reference = $response->json('data.reference');
    $payment = Transaction::where('reference', $reference)->firstOrFail();

    $this->postJson('/api/v1/loans/'.$loan->id.'/guarantor-form/payment/'.$reference.'/refresh', [])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending');

    $this->deleteJson('/api/v1/loans/'.$loan->id.'/guarantor-form/payment/'.$reference)
        ->assertOk()
        ->assertJsonPath('data.status', 'pending');

    expect($payment->fresh()->status)->toBe('pending')
        ->and($loan->fresh()->guarantor_form_fee_paid_at)->toBeNull();

    $this->getJson('/api/v1/loans')
        ->assertOk()
        ->assertJsonPath('data.loans.0.guarantor_form.pending_payment.reference', $reference)
        ->assertJsonPath('data.loans.0.guarantor_form.pending_payment.checkout_url', 'https://checkout.korapay.com/'.$reference);
});

test('an unverifiable guarantor-form payment request is left unchanged on cancellation', function () {
    fakeKorapayForLoanFee('NGN', 'unavailable');
    config([
        'settings.loan.guarantor_form_fee_ngn' => 1500,
        'settings.payment.loan_application_fee_provider' => 'korapay',
        'services.korapay.base_url' => 'https://api.korapay.com',
        'services.korapay.secret_key' => 'sk_test_korapay',
        'services.korapay.currency' => 'NGN',
    ]);

    $user = payLaterUser();
    $loan = Loan::factory()->create(['user_id' => $user->id, 'status' => LoanStatus::APPROVED]);
    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/loans/'.$loan->id.'/guarantor-form/payment', [])
        ->assertOk();
    $reference = $response->json('data.reference');
    $payment = Transaction::where('reference', $reference)->firstOrFail();

    $this->deleteJson('/api/v1/loans/'.$loan->id.'/guarantor-form/payment/'.$reference)
        ->assertOk()
        ->assertJsonPath('data.status', 'unavailable');

    expect($payment->fresh()->status)->toBe('pending')
        ->and($loan->fresh()->guarantor_form_fee_paid_at)->toBeNull();
});

test('a guarantor-form payment request can be cancelled after the provider confirms failure', function () {
    fakeKorapayForLoanFee('NGN', 'failed');
    config([
        'settings.loan.guarantor_form_fee_ngn' => 1500,
        'settings.payment.loan_application_fee_provider' => 'korapay',
        'services.korapay.base_url' => 'https://api.korapay.com',
        'services.korapay.secret_key' => 'sk_test_korapay',
        'services.korapay.currency' => 'NGN',
    ]);

    $user = payLaterUser();
    $loan = Loan::factory()->create(['user_id' => $user->id, 'status' => LoanStatus::APPROVED]);
    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/loans/'.$loan->id.'/guarantor-form/payment', [])
        ->assertOk();
    $reference = $response->json('data.reference');
    $payment = Transaction::where('reference', $reference)->firstOrFail();

    $this->deleteJson('/api/v1/loans/'.$loan->id.'/guarantor-form/payment/'.$reference)
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($payment->fresh())->toBeNull()
        ->and($loan->fresh()->guarantor_form_fee_paid_at)->toBeNull();
});

test('the loan page hides staff approval behind the guarantor form requirement', function () {
    $user = payLaterUser();
    $loan = Loan::factory()->create(['user_id' => $user->id, 'status' => LoanStatus::APPROVED]);
    $this->actingAs($user);

    Livewire::test(LoanPage::class)
        ->assertSee('Guarantor form required')
        ->assertSee('Guarantor form')
        ->call('openGuarantorForm', $loan->id)
        ->assertSee('Required before manual disbursement')
        ->assertSee('1500.00')
        ->assertDontSee('Approved');
});

test('the guarantor form no longer asks for occupation or employer', function () {
    $user = payLaterUser();
    $loan = Loan::factory()->create([
        'user_id' => $user->id,
        'status' => LoanStatus::APPROVED,
        'guarantor_form_fee_reference' => 'LGF-NO-OCCUPATION',
        'guarantor_form_fee_amount' => 1500,
        'guarantor_form_fee_currency' => 'NGN',
        'guarantor_form_fee_paid_at' => now(),
        'guarantor_form_data' => [
            'occupation' => 'Legacy occupation',
            'employer' => 'Legacy employer',
        ],
    ]);
    $this->actingAs($user);

    Livewire::test(LoanPage::class)
        ->call('openGuarantorForm', $loan->id)
        ->assertSee('Guarantor’s full name')
        ->assertDontSee('Occupation')
        ->assertDontSee('Employer');
});

test('a valid loan-fee coupon is claimed atomically before the application is created', function () {
    config(['settings.loan.service_fee_percent' => 5]);
    $user = payLaterUser();
    $coupon = Coupon::factory()->create([
        'type' => CouponType::LOAN_FEE,
        'region' => 'nigeria',
        'price' => 1,
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/loans', loanApplicationPayload(['loan_type' => 'personal', 'fee_coupon_code' => $coupon->code]))
        ->assertCreated()
        ->assertJsonPath('data.loan.status', LoanStatus::PROCESSING->value)
        ->assertJsonPath('data.loan.loan_type', 'personal')
        ->assertJsonPath('data.loan.application_fee.currency', 'NGN')
        ->assertJsonPath('data.loan.application_fee.non_refundable', true);

    $loan = Loan::where('user_id', $user->id)->first();

    expect((float) $loan->total_repayment)->toBeGreaterThan(50.0)
        ->and($loan->application_fee_coupon_id)->toBe($coupon->id)
        ->and($loan->disbursement_account_name)->toBe('Test Applicant')
        ->and($loan->disbursement_account_number)->toBe('1234567890')
        ->and($loan->disbursement_bank_name)->toBe('Example Bank')
        ->and($loan->agreement_accepted_at)->not->toBeNull()
        ->and($coupon->refresh()->user_id)->toBe($user->id)
        ->and($coupon->used_at)->not->toBeNull();
});

test('a loan is not created until an application fee coupon or verified payment is available', function () {
    $user = payLaterUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/loans', loanApplicationPayload())
        ->assertStatus(422);

    expect(Loan::where('user_id', $user->id)->count())->toBe(0)
        ->and(\App\Models\Transaction::where('user_id', $user->id)->where('type', TransactionType::LOAN_APPLICATION_FEE)->count())->toBe(0);
});

test('invalid loan-fee coupons do not create applications or consume other coupon types', function () {
    $user = payLaterUser();
    $activationCoupon = Coupon::factory()->create(['type' => CouponType::ACTIVATION, 'region' => 'nigeria']);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/loans', loanApplicationPayload(['fee_coupon_code' => $activationCoupon->code]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['fee_coupon_code']);

    expect(Loan::where('user_id', $user->id)->count())->toBe(0)
        ->and($activationCoupon->refresh()->used_at)->toBeNull();
});

test('a pending loan or fee payment blocks a second application', function () {
    $user = payLaterUser();
    $coupon = Coupon::factory()->create(['type' => CouponType::LOAN_FEE, 'region' => 'nigeria']);
    Loan::factory()->create(['user_id' => $user->id, 'status' => LoanStatus::PROCESSING]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/loans', loanApplicationPayload(['fee_coupon_code' => $coupon->code]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);

    expect($coupon->refresh()->used_at)->toBeNull();
});

test('resolved loan applications do not prevent a later application', function () {
    $user = payLaterUser();
    $previous = Loan::factory()->create(['user_id' => $user->id, 'status' => LoanStatus::REJECTED]);
    $coupon = Coupon::factory()->create(['type' => CouponType::LOAN_FEE, 'region' => 'nigeria']);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/loans', loanApplicationPayload(['fee_coupon_code' => $coupon->code]))
        ->assertCreated();

    expect($previous->refresh()->status)->toBe(LoanStatus::REJECTED)
        ->and(Loan::where('user_id', $user->id)->count())->toBe(2);
});

test('an active loan payment channel creates no loan until the gateway verifies the fee', function () {
    fakeKorapayForLoanFee();
    config([
        'settings.payment.loan_application_fee_provider' => 'korapay',
        'services.korapay.base_url' => 'https://api.korapay.com',
        'services.korapay.secret_key' => 'sk_test_korapay',
        'services.korapay.currency' => 'NGN',
    ]);

    $user = payLaterUser();
    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/loans', loanApplicationPayload())
        ->assertOk()
        ->assertJsonPath('data.application_fee.currency', 'NGN')
        ->assertJsonPath('data.application_fee.amount', 1000.0);

    $reference = $response->json('data.reference');
    $retry = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/loans', loanApplicationPayload())
        ->assertOk();
    $transaction = \App\Models\Transaction::where('reference', $reference)->firstOrFail();

    expect($retry->json('data.reference'))->toBe($reference)
        ->and(\App\Models\Transaction::where('user_id', $user->id)->where('type', TransactionType::LOAN_APPLICATION_FEE)->count())->toBe(1)
        ->and($transaction->type)->toBe(TransactionType::LOAN_APPLICATION_FEE)
        ->and($transaction->status)->toBe('pending')
        ->and($transaction->meta['loan_type'])->toBe('student')
        ->and(Loan::where('user_id', $user->id)->count())->toBe(0)
        ->and(app(ChargeSettlement::class)->finalize($reference))->toBe('completed')
        ->and(Loan::where('user_id', $user->id)->count())->toBe(1);

    $loan = Loan::where('user_id', $user->id)->firstOrFail();
    expect($loan->application_fee_reference)->toBe($reference)
        ->and($loan->application_fee_currency)->toBe('NGN')
        ->and($loan->application_fee_type)->toBe('fixed')
        ->and($loan->disbursement_account_name)->toBe('Test Applicant')
        ->and($loan->disbursement_account_number)->toBe('1234567890')
        ->and($loan->disbursement_bank_name)->toBe('Example Bank')
        ->and($loan->status)->toBe(LoanStatus::PROCESSING)
        ->and($transaction->refresh()->status)->toBe('completed');
});

test('users can cancel a pending loan-fee request and its linked processing loan', function () {
    fakeKorapayForLoanFee('NGN', 'pending');
    config([
        'settings.payment.loan_application_fee_provider' => 'korapay',
        'services.korapay.base_url' => 'https://api.korapay.com',
        'services.korapay.secret_key' => 'sk_test_korapay',
        'services.korapay.currency' => 'NGN',
    ]);

    $user = payLaterUser();
    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/loans', loanApplicationPayload())
        ->assertOk();
    $reference = $response->json('data.reference');
    $payment = Transaction::where('reference', $reference)->firstOrFail();
    $linkedLoan = Loan::factory()->create([
        'user_id' => $user->id,
        'application_fee_reference' => $reference,
        'status' => LoanStatus::PROCESSING,
    ]);

    $this->actingAs($user);

    Livewire::test(LoanPage::class)
        ->assertSee('Application fee payment awaiting verification')
        ->assertSee('Cancel payment')
        ->assertSee('confirm-action')
        ->assertDontSee('wire:confirm')
        ->call('cancelPendingPayment', $reference)
        ->assertRedirect(route('loan'));

    expect($payment->fresh())->toBeNull()
        ->and($linkedLoan->fresh())->toBeNull()
        ->and(Loan::where('user_id', $user->id)->where('application_fee_reference', $reference)->count())->toBe(0);
});

test('cancellation keeps a fee payment that the provider has already verified', function () {
    fakeKorapayForLoanFee();
    config([
        'settings.payment.loan_application_fee_provider' => 'korapay',
        'services.korapay.base_url' => 'https://api.korapay.com',
        'services.korapay.secret_key' => 'sk_test_korapay',
        'services.korapay.currency' => 'NGN',
    ]);

    $user = payLaterUser();
    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/loans', loanApplicationPayload())
        ->assertOk();
    $reference = $response->json('data.reference');
    $payment = Transaction::where('reference', $reference)->firstOrFail();

    $this->actingAs($user);

    Livewire::test(LoanPage::class)
        ->call('cancelPendingPayment', $reference)
        ->assertRedirect(route('loan'));

    $loan = Loan::where('user_id', $user->id)->where('application_fee_reference', $reference)->first();
    expect($payment->fresh()->status)->toBe('completed')
        ->and($loan)->not->toBeNull()
        ->and($loan->status)->toBe(LoanStatus::PROCESSING);
});

test('percentage application fees use the active channel and exclude vendor coupons', function () {
    fakeKorapayForLoanFee('USD');
    config([
        'settings.loan.enabled' => true,
        'settings.loan.application_fee_mode' => 'percentage',
        'settings.loan.application_fee_percentage' => 5,
        'settings.payment.loan_application_fee_provider' => 'korapay',
        'services.korapay.base_url' => 'https://api.korapay.com',
        'services.korapay.secret_key' => 'sk_test_korapay',
        'services.korapay.currency' => 'USD',
    ]);

    $user = payLaterUser();
    $coupon = Coupon::factory()->create(['type' => CouponType::LOAN_FEE, 'region' => 'nigeria']);
    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/loans', loanApplicationPayload(['fee_coupon_code' => $coupon->code]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['fee_coupon_code']);

    expect($coupon->fresh()->used_at)->toBeNull()
        ->and(Loan::where('user_id', $user->id)->count())->toBe(0)
        ->and(Transaction::where('user_id', $user->id)->count())->toBe(0);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/loans', loanApplicationPayload())
        ->assertOk()
        ->assertJsonPath('data.application_fee.amount', 2.5)
        ->assertJsonPath('data.application_fee.currency', 'USD')
        ->assertJsonPath('data.application_fee.fee_type', 'percentage')
        ->assertJsonPath('data.application_fee.fee_percentage', 5.0);

    $transaction = Transaction::where('reference', $response->json('data.reference'))->firstOrFail();
    $agent = activatedUser(['is_agent' => true, 'agent_balance' => 5, 'country' => 'US']);

    expect($transaction->meta['application_fee_amount'])->toBe(2.5)
        ->and((float) $transaction->meta['min_amount_usd'])->toBe(50.0)
        ->and((float) $transaction->meta['application_fee_percentage'])->toBe(5.0)
        ->and(Loan::where('user_id', $user->id)->count())->toBe(0)
        ->and(app(CouponMintingService::class)->planOptions($agent)->firstWhere('key', 'loan_fee'))->toBeNull()
        ->and(app(ChargeSettlement::class)->finalize((string) $transaction->reference))->toBe('completed');

    $loan = Loan::where('user_id', $user->id)->firstOrFail();
    expect((float) $loan->application_fee_amount)->toBe(2.5)
        ->and($loan->application_fee_type)->toBe('percentage')
        ->and((float) $loan->application_fee_percentage)->toBe(5.0)
        ->and($loan->disbursement_account_number)->toBe('1234567890');
});

test('amounts below the configured loan minimum are rejected', function () {
    $user = payLaterUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/loans', loanApplicationPayload(['amount' => 49.99]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);

    expect(Loan::where('user_id', $user->id)->count())->toBe(0)
        ->and(Transaction::where('user_id', $user->id)->count())->toBe(0);
});

test('amounts above the cap are rejected', function () {
    $user = payLaterUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/loans', loanApplicationPayload(['amount' => 500]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

test('loan applications require valid bank-account details for disbursement', function () {
    $user = payLaterUser();
    $payload = loanApplicationPayload();
    unset($payload['disbursement_account_name'], $payload['disbursement_account_number'], $payload['disbursement_bank_name']);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/loans', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'disbursement_account_name',
            'disbursement_account_number',
            'disbursement_bank_name',
        ]);

    expect(Loan::where('user_id', $user->id)->count())->toBe(0)
        ->and(Transaction::where('user_id', $user->id)->count())->toBe(0);
});

test('due dates beyond the repayment window are rejected', function () {
    $user = payLaterUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/loans', loanApplicationPayload(['due_date' => now()->addDays(120)->toDateString()]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['dueDate']);
});

test('vendors can mint loan application fee coupons at the configured vendor price', function () {
    config([
        'settings.loan.enabled' => true,
        'settings.loan.application_fee_agent_usd' => 0.80,
    ]);
    $agent = activatedUser(['is_agent' => true, 'agent_balance' => 5, 'country' => 'US']);
    $service = app(CouponMintingService::class);
    $option = $service->planOptions($agent)->firstWhere('key', 'loan_fee');

    expect($option)->not->toBeNull()
        ->and($option['agent_price'])->toBe(0.8);

    $result = $service->mint($agent, 'loan_fee', 2);

    expect($result['batch']->type)->toBe(CouponType::LOAN_FEE)
        ->and($result['batch']->quantity)->toBe(2)
        ->and($result['batch']->coupons()->count())->toBe(2)
        ->and((float) $agent->refresh()->agent_balance)->toBe(3.4);
});

test('the dashboard agent modal generates loan-fee coupons', function () {
    config(['settings.loan.enabled' => true]);
    $agent = activatedUser(['is_agent' => true, 'agent_balance' => 5, 'country' => 'NG']);
    $this->actingAs($agent);

    Livewire::test(AgentDashboard::class)
        ->assertSee('Loan Application Fee Coupon')
        ->set('planKey', 'loan_fee')
        ->set('quantity', 2)
        ->call('generate')
        ->assertHasNoErrors();

    $batch = \App\Models\CouponBatch::query()->where('agent_id', $agent->id)->sole();

    expect($batch->type)->toBe(CouponType::LOAN_FEE)
        ->and($batch->coupons()->count())->toBe(2)
        ->and((float) $agent->refresh()->agent_balance)->toBe(3.4);
});
