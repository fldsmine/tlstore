<?php

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Livewire\Backoffice\Loans\ListLoans;
use App\Livewire\Backoffice\Settings\FeatureSetting;
use App\Models\Admin;
use App\Models\Loan;
use App\Models\Setting;
use App\Models\User;
use App\Services\LoanDisbursementService;
use App\Support\SettingMapper;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('staff explicitly decide pending loan reviews and cannot change a terminal decision', function () {
    $user = User::factory()->create();
    $pending = Loan::factory()->create([
        'user_id' => $user->id,
        'loan_type' => LoanType::BUSINESS,
        'application_fee_amount' => 1000,
        'application_fee_currency' => 'NGN',
        'disbursement_account_name' => 'Loan Applicant',
        'disbursement_account_number' => '1234567890',
        'disbursement_bank_name' => 'Example Bank',
        'agreement_accepted_at' => now(),
        'status' => LoanStatus::PROCESSING,
    ]);
    $admin = Admin::factory()->regular()->create();
    $this->actingAs($admin, 'admin');

    Livewire::test(ListLoans::class)
        ->call('openDetail', $pending->id)
        ->assertSee('Business')
        ->assertSee('NGN 1,000.00')
        ->assertSee('Loan Applicant')
        ->assertSee('1234567890')
        ->assertSee('Example Bank')
        ->call('setLoanStatus', $pending->id, LoanStatus::APPROVED->value)
        ->assertHasNoErrors();

    expect($pending->fresh()->status)->toBe(LoanStatus::APPROVED);

    Livewire::test(ListLoans::class)
        ->call('setLoanStatus', $pending->id, LoanStatus::REJECTED->value)
        ->assertHasNoErrors();

    expect($pending->fresh()->status)->toBe(LoanStatus::APPROVED);

    $another = Loan::factory()->create([
        'user_id' => User::factory()->create()->id,
        'status' => LoanStatus::PROCESSING,
    ]);

    Livewire::test(ListLoans::class)
        ->call('setLoanStatus', $another->id, LoanStatus::REJECTED->value)
        ->assertHasNoErrors();

    expect($another->fresh()->status)->toBe(LoanStatus::REJECTED)
        ->and($another->fresh()->rejection_reason)->toBe(config('settings.loan.rejection_reason'));
});

test('approved loan bank exports include disbursement details and mark the exported loan paid', function () {
    $user = User::factory()->create([
        'username' => 'loanexporter',
        'first_name' => 'Loan',
        'last_name' => 'Applicant',
    ]);
    $loan = Loan::factory()->create([
        'user_id' => $user->id,
        'loan_type' => LoanType::PERSONAL,
        'amount' => 125.5,
        'status' => LoanStatus::APPROVED,
        'disbursement_account_name' => 'Loan Applicant',
        'disbursement_account_number' => '00123456789',
        'disbursement_bank_name' => 'Example Bank',
        'guarantor_form_fee_reference' => 'LGF-EXPORT-VERIFIED',
        'guarantor_form_fee_amount' => 1500,
        'guarantor_form_fee_currency' => 'NGN',
        'guarantor_form_fee_paid_at' => now(),
        'guarantor_form_data' => ['full_name' => 'Verified Guarantor', 'signature' => 'Verified Guarantor'],
        'guarantor_form_submitted_at' => now(),
    ]);
    $admin = Admin::factory()->regular()->create();
    $this->actingAs($admin, 'admin');

    $export = app(LoanDisbursementService::class)->exportApproved([$loan->id], $admin->id);

    expect($export)->not->toBeNull()
        ->and($export['count'])->toBe(1)
        ->and($export['content'])->toContain('Requested Amount (USD)')
        ->toContain('125.50')
        ->toContain('Loan Applicant')
        ->toContain('00123456789')
        ->toContain('Example Bank');

    $paidLoan = $loan->fresh();
    expect($paidLoan->status)->toBe(LoanStatus::PAID)
        ->and($paidLoan->paid_at)->not->toBeNull()
        ->and((string) $paidLoan->paid_by)->toBe((string) $admin->id)
        ->and($paidLoan->exported_at)->not->toBeNull();
});

test('manual disbursement and bank-detail export require a paid submitted guarantor form', function () {
    $admin = Admin::factory()->regular()->create();
    $this->actingAs($admin, 'admin');
    $loan = Loan::factory()->create([
        'status' => LoanStatus::APPROVED,
        'disbursement_account_name' => 'Loan Applicant',
        'disbursement_account_number' => '00123456789',
        'disbursement_bank_name' => 'Example Bank',
    ]);

    expect($loan->requiresGuarantorForm())->toBeTrue()
        ->and($loan->userFacingStatusValue())->toBe('guarantor_form_required')
        ->and($loan->userFacingStatusLabel())->toBe('Guarantor form required');

    expect(fn () => app(LoanDisbursementService::class)->exportApproved([$loan->id], $admin->id))
        ->toThrow(ValidationException::class);
    expect(fn () => app(LoanDisbursementService::class)->markPaid([$loan->id], $admin->id))
        ->toThrow(ValidationException::class);

    expect($loan->fresh()->status)->toBe(LoanStatus::APPROVED)
        ->and($loan->fresh()->paid_at)->toBeNull();
});

test('invalid bank details prevent the entire loan export from being marked paid', function () {
    $loan = Loan::factory()->create([
        'status' => LoanStatus::APPROVED,
        'guarantor_form_fee_reference' => 'LGF-BANK-VERIFIED',
        'guarantor_form_fee_amount' => 1500,
        'guarantor_form_fee_currency' => 'NGN',
        'guarantor_form_fee_paid_at' => now(),
        'guarantor_form_data' => ['full_name' => 'Verified Guarantor', 'signature' => 'Verified Guarantor'],
        'guarantor_form_submitted_at' => now(),
    ]);
    $admin = Admin::factory()->regular()->create();
    $this->actingAs($admin, 'admin');

    expect(fn () => app(LoanDisbursementService::class)->exportApproved([$loan->id], $admin->id))
        ->toThrow(ValidationException::class);

    expect($loan->fresh()->status)->toBe(LoanStatus::APPROVED)
        ->and($loan->fresh()->paid_at)->toBeNull();
});

test('staff can manually mark an approved loan paid and only approved loans can transition', function () {
    $admin = Admin::factory()->regular()->create();
    $this->actingAs($admin, 'admin');
    $approved = Loan::factory()->create([
        'status' => LoanStatus::APPROVED,
        'guarantor_form_fee_reference' => 'LGF-MARK-VERIFIED',
        'guarantor_form_fee_amount' => 1500,
        'guarantor_form_fee_currency' => 'NGN',
        'guarantor_form_fee_paid_at' => now(),
        'guarantor_form_data' => ['full_name' => 'Verified Guarantor', 'signature' => 'Verified Guarantor'],
        'guarantor_form_submitted_at' => now(),
    ]);
    $pending = Loan::factory()->create(['status' => LoanStatus::PROCESSING]);

    Livewire::test(ListLoans::class)
        ->call('openMarkPaid', $approved->id)
        ->call('confirmMarkPaid')
        ->assertHasNoErrors();

    expect($approved->fresh()->status)->toBe(LoanStatus::PAID)
        ->and($approved->fresh()->paid_at)->not->toBeNull()
        ->and((string) $approved->fresh()->paid_by)->toBe((string) $admin->id)
        ->and(app(LoanDisbursementService::class)->markPaid([$pending->id], $admin->id))->toBe(0)
        ->and($pending->fresh()->status)->toBe(LoanStatus::PROCESSING);
});

test('staff can reject selected pending loan requests together without partially rejecting stale selections', function () {
    $admin = Admin::factory()->regular()->create();
    $this->actingAs($admin, 'admin');

    $first = Loan::factory()->create(['status' => LoanStatus::PROCESSING]);
    $second = Loan::factory()->create(['status' => LoanStatus::PROCESSING]);
    $pendingAfterRace = Loan::factory()->create(['status' => LoanStatus::PROCESSING]);
    $approved = Loan::factory()->create(['status' => LoanStatus::APPROVED]);

    Livewire::test(ListLoans::class)
        ->set('status', LoanStatus::PROCESSING->value)
        ->set('selected', [$first->id, $second->id])
        ->assertSee('Reject selected')
        ->call('rejectSelected')
        ->assertHasNoErrors();

    expect($first->fresh()->status)->toBe(LoanStatus::REJECTED)
        ->and($second->fresh()->status)->toBe(LoanStatus::REJECTED)
        ->and($first->fresh()->rejection_reason)->toBe(config('settings.loan.rejection_reason'));

    Livewire::test(ListLoans::class)
        ->set('status', LoanStatus::PROCESSING->value)
        ->set('selected', [$pendingAfterRace->id, $approved->id])
        ->call('rejectSelected')
        ->assertHasNoErrors();

    expect($pendingAfterRace->fresh()->status)->toBe(LoanStatus::PROCESSING)
        ->and($approved->fresh()->status)->toBe(LoanStatus::APPROVED);
});

test('feature settings save a custom list of loan amount presets for the applicant form', function () {
    $this->actingAs(Admin::factory()->regular()->create(), 'admin');

    Livewire::test(FeatureSetting::class)
        ->set('loanPresets', '20, 50, 100, 250, 500')
        ->set('loanGuarantorFormFeeNgn', '2250')
        ->call('save')
        ->assertHasNoErrors();

    $mapped = SettingMapper::toConfigArray(transform_setting(Setting::all()));
    expect($mapped['settings.loan.presets'])->toEqual([20.0, 50.0, 100.0, 250.0, 500.0])
        ->and($mapped['settings.loan.guarantor_form_fee_ngn'])->toBe('2250');
});
