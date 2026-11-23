<?php

namespace App\Console\Commands;

use App\Enums\LoanStatus;
use App\Enums\TransactionType;
use App\Models\Loan;
use App\Models\PaymentReference;
use App\Models\Transaction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('loans:prune-expired-payments')]
#[Description('Delete unpaid loan application requests and fee transactions after their 150-minute payment window.')]
class PruneExpiredLoanApplicationPayments extends Command
{
    public const int PAYMENT_WINDOW_MINUTES = 150;

    private const int BATCH_SIZE = 100;

    public function handle(): int
    {
        $cutoff = now()->subMinutes(self::PAYMENT_WINDOW_MINUTES);
        $deletedLoans = 0;
        $deletedPayments = 0;

        Transaction::query()
            ->where('type', TransactionType::LOAN_APPLICATION_FEE->value)
            ->where('status', 'pending')
            ->where('created_at', '<=', $cutoff)
            ->chunkById(self::BATCH_SIZE, function ($transactions) use ($cutoff, &$deletedLoans, &$deletedPayments): void {
                foreach ($transactions as $candidate) {
                    [$loanCount, $paymentCount] = DB::transaction(function () use ($candidate, $cutoff): array {
                        $payment = Transaction::query()
                            ->whereKey($candidate->getKey())
                            ->lockForUpdate()
                            ->first();

                        // Settlement/webhook work can race this scheduled cleanup. Re-check the
                        // locked row so a payment completed while the provider was verifying survives.
                        if ($payment === null
                            || $payment->type !== TransactionType::LOAN_APPLICATION_FEE
                            || $payment->status !== 'pending'
                            || $payment->created_at->gt($cutoff)
                            || PaymentReference::query()->where('reference', $payment->reference)->exists()) {
                            return [0, 0];
                        }

                        // The current application flow creates its Loan row only after the fee is
                        // verified, but also remove any matching legacy/incomplete processing row.
                        $loans = Loan::query()
                            ->where('user_id', $payment->user_id)
                            ->where('application_fee_reference', $payment->reference)
                            ->where('status', LoanStatus::PROCESSING->value)
                            ->lockForUpdate()
                            ->get();

                        foreach ($loans as $loan) {
                            $loan->delete();
                        }

                        $payment->delete();

                        return [$loans->count(), 1];
                    }, attempts: 3);

                    $deletedLoans += $loanCount;
                    $deletedPayments += $paymentCount;
                }
            });

        $this->info(sprintf(
            'Deleted %d expired loan request(s) and %d unpaid fee payment record(s) older than %d minutes.',
            $deletedLoans,
            $deletedPayments,
            self::PAYMENT_WINDOW_MINUTES,
        ));

        return self::SUCCESS;
    }
}
