<?php

use App\Enums\LoanStatus;
use App\Enums\TransactionType;
use App\Models\Loan;
use App\Models\PaymentReference;
use App\Models\Transaction;
use App\Models\User;

test('expired unpaid loan fee requests and their linked processing loans are pruned only after 150 minutes', function () {
    $this->travelTo(now()->startOfDay()->addHours(12));
    $user = User::factory()->create();
    $expiredAt = now()->subMinutes(151);
    $cutoffAt = now()->subMinutes(150);
    $recentAt = now()->subMinutes(149);

    $expiredPayment = Transaction::factory()->create([
        'user_id' => $user->id,
        'type' => TransactionType::LOAN_APPLICATION_FEE,
        'status' => 'pending',
        'reference' => 'LAF-EXPIRED-WITH-LOAN',
        'created_at' => $expiredAt,
        'updated_at' => $expiredAt,
    ]);
    $expiredLoan = Loan::factory()->create([
        'user_id' => $user->id,
        'status' => LoanStatus::PROCESSING,
        'application_fee_reference' => $expiredPayment->reference,
        'created_at' => $expiredAt,
        'updated_at' => $expiredAt,
    ]);

    $cutoffPayment = Transaction::factory()->create([
        'user_id' => $user->id,
        'type' => TransactionType::LOAN_APPLICATION_FEE,
        'status' => 'pending',
        'reference' => 'LAF-EXPIRED-AT-CUTOFF',
        'created_at' => $cutoffAt,
        'updated_at' => $cutoffAt,
    ]);
    $recentPayment = Transaction::factory()->create([
        'user_id' => $user->id,
        'type' => TransactionType::LOAN_APPLICATION_FEE,
        'status' => 'pending',
        'reference' => 'LAF-STILL-IN-WINDOW',
        'created_at' => $recentAt,
        'updated_at' => $recentAt,
    ]);
    $completedPayment = Transaction::factory()->create([
        'user_id' => $user->id,
        'type' => TransactionType::LOAN_APPLICATION_FEE,
        'status' => 'completed',
        'reference' => 'LAF-ALREADY-PAID',
        'created_at' => $expiredAt,
        'updated_at' => $expiredAt,
    ]);
    $completedLoan = Loan::factory()->create([
        'user_id' => $user->id,
        'status' => LoanStatus::PROCESSING,
        'application_fee_reference' => $completedPayment->reference,
        'created_at' => $expiredAt,
        'updated_at' => $expiredAt,
    ]);
    $failedPayment = Transaction::factory()->create([
        'user_id' => $user->id,
        'type' => TransactionType::LOAN_APPLICATION_FEE,
        'status' => 'failed',
        'reference' => 'LAF-FAILED-AUDIT',
        'created_at' => $expiredAt,
        'updated_at' => $expiredAt,
    ]);
    $ledgerPayment = Transaction::factory()->create([
        'user_id' => $user->id,
        'type' => TransactionType::LOAN_APPLICATION_FEE,
        'status' => 'pending',
        'reference' => 'LAF-SETTLED-LEDGER',
        'created_at' => $expiredAt,
        'updated_at' => $expiredAt,
    ]);
    PaymentReference::query()->create([
        'reference' => $ledgerPayment->reference,
        'transaction_id' => $ledgerPayment->id,
        'user_id' => $user->id,
        'type' => TransactionType::LOAN_APPLICATION_FEE->value,
        'currency' => 'NGN',
        'amount_ngn' => 1000,
        'settled_at' => $expiredAt,
    ]);
    $ledgerLoan = Loan::factory()->create([
        'user_id' => $user->id,
        'status' => LoanStatus::PROCESSING,
        'application_fee_reference' => $ledgerPayment->reference,
        'created_at' => $expiredAt,
        'updated_at' => $expiredAt,
    ]);
    $otherPayment = Transaction::factory()->create([
        'user_id' => $user->id,
        'type' => TransactionType::BILLS_TOPUP,
        'status' => 'pending',
        'reference' => 'BILL-STILL-RETAINED',
        'created_at' => $expiredAt,
        'updated_at' => $expiredAt,
    ]);

    $this->artisan('loans:prune-expired-payments')
        ->expectsOutputToContain('Deleted 1 expired loan request(s) and 2 unpaid fee payment record(s) older than 150 minutes.')
        ->assertSuccessful();

    expect(Transaction::query()->whereKey($expiredPayment->id)->exists())->toBeFalse()
        ->and(Loan::query()->whereKey($expiredLoan->id)->exists())->toBeFalse()
        ->and(Transaction::query()->whereKey($cutoffPayment->id)->exists())->toBeFalse()
        ->and(Transaction::query()->whereKey($recentPayment->id)->exists())->toBeTrue()
        ->and(Transaction::query()->whereKey($completedPayment->id)->exists())->toBeTrue()
        ->and(Loan::query()->whereKey($completedLoan->id)->exists())->toBeTrue()
        ->and(Transaction::query()->whereKey($failedPayment->id)->exists())->toBeTrue()
        ->and(Transaction::query()->whereKey($ledgerPayment->id)->exists())->toBeTrue()
        ->and(Loan::query()->whereKey($ledgerLoan->id)->exists())->toBeTrue()
        ->and(PaymentReference::query()->where('reference', $ledgerPayment->reference)->exists())->toBeTrue()
        ->and(Transaction::query()->whereKey($otherPayment->id)->exists())->toBeTrue();
});
