<?php

namespace App\Console\Commands;

use App\Enums\LoanStatus;
use App\Models\Loan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('loans:auto-reject')]
#[Description('Reject pending loans from users who do not meet the minimum account age, after the configured delay.')]
class AutoRejectLoansCommand extends Command
{
    public function handle(): int
    {
        if (! config('settings.loan.auto_reject', false)) {
            $this->info('Loan auto-rejection is disabled.');

            return self::SUCCESS;
        }

        $cutoff = now()->subMinutes((int) config('settings.loan.auto_reject_minutes', 5));
        $minAccountAge = now()->subDays((int) config('settings.loan.min_account_age_days', 30));
        $reason = (string) config('settings.loan.rejection_reason');

        $count = Loan::query()
            ->where('status', LoanStatus::PROCESSING)
            ->where('created_at', '<=', $cutoff)
            ->whereHas('user', fn (Builder $q) => $q->where('created_at', '>', $minAccountAge))
            ->update([
                'status' => LoanStatus::REJECTED,
                'rejection_reason' => $reason,
            ]);

        $this->info("Auto-rejected {$count} loan application(s).");

        return self::SUCCESS;
    }
}
