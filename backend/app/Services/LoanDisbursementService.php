<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Models\Loan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff-only manual loan disbursement state transitions and bank-detail export.
 * This service never initiates a bank transfer; it records staff-confirmed state only.
 */
class LoanDisbursementService
{
    public const int MAX_SELECTED_LOANS = 500;

    /**
     * Export approved loan details as CSV and mark the exact exported rows as paid atomically.
     * A stale/forged selection is rejected as a whole; no subset is changed or exported.
     *
     * @return array{content: string, count: int}|null
     */
    public function exportApproved(array $ids, string $adminId): ?array
    {
        $ids = $this->normalizeIds($ids);
        $this->assertSelection($ids, $adminId);

        if ($ids === []) {
            return null;
        }

        return DB::transaction(function () use ($ids, $adminId): ?array {
            $loans = Loan::query()
                ->with('user')
                ->whereKey($ids)
                ->where('status', LoanStatus::APPROVED->value)
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();

            if ($loans->count() !== count($ids)) {
                return null;
            }

            if ($loans->contains(fn (Loan $loan): bool => ! $loan->hasCompletedGuarantorForm())) {
                throw ValidationException::withMessages([
                    'disbursement' => 'Every selected loan must have a paid guarantor-form fee and submitted form before disbursement.',
                ]);
            }

            $hasIncompleteAccount = $loans->contains(fn (Loan $loan): bool => blank($loan->disbursement_account_name)
                || blank($loan->disbursement_account_number)
                || blank($loan->disbursement_bank_name));

            if ($hasIncompleteAccount) {
                throw ValidationException::withMessages([
                    'export' => 'Every selected approved loan must have an account name, account number and bank name before export.',
                ]);
            }

            $content = $this->csv($loans);
            $paidAt = now();

            foreach ($loans as $loan) {
                $loan->update([
                    'status' => LoanStatus::PAID->value,
                    'paid_at' => $paidAt,
                    'paid_by' => $adminId,
                    'exported_at' => $paidAt,
                ]);
            }

            return ['content' => $content, 'count' => $loans->count()];
        });
    }

    /** Mark loans paid only while they are still approved; no transfer is triggered. */
    public function markPaid(array $ids, string $adminId): int
    {
        $ids = $this->normalizeIds($ids);
        $this->assertSelection($ids, $adminId);

        if ($ids === []) {
            return 0;
        }

        return DB::transaction(function () use ($ids, $adminId): int {
            $loans = Loan::query()
                ->whereKey($ids)
                ->where('status', LoanStatus::APPROVED->value)
                ->lockForUpdate()
                ->get();

            if ($loans->count() !== count($ids)) {
                return 0;
            }

            if ($loans->contains(fn (Loan $loan): bool => ! $loan->hasCompletedGuarantorForm())) {
                throw ValidationException::withMessages([
                    'disbursement' => 'Every selected loan must have a paid guarantor-form fee and submitted form before disbursement.',
                ]);
            }

            $paidAt = now();
            foreach ($loans as $loan) {
                $loan->update([
                    'status' => LoanStatus::PAID->value,
                    'paid_at' => $paidAt,
                    'paid_by' => $adminId,
                ]);
            }

            return $loans->count();
        });
    }

    /** @return list<string> */
    protected function normalizeIds(array $ids): array
    {
        $normalized = [];
        foreach ($ids as $id) {
            if (! is_string($id) || trim($id) === '') {
                throw ValidationException::withMessages(['selection' => 'The loan selection contains an invalid ID.']);
            }

            $normalized[] = trim($id);
        }

        return array_values(array_unique($normalized));
    }

    /** @param list<string> $ids */
    protected function assertSelection(array $ids, string $adminId): void
    {
        $admin = auth('admin')->user();
        if (! $admin || ! $admin->canWrite() || (string) $admin->id !== $adminId) {
            throw new AuthorizationException('A staff account with write access is required to update loan disbursement records.');
        }

        if (count($ids) > self::MAX_SELECTED_LOANS) {
            throw ValidationException::withMessages(['selection' => 'Export or mark up to '.self::MAX_SELECTED_LOANS.' loans at a time.']);
        }
    }

    protected function csv(Collection $loans): string
    {
        $stream = fopen('php://temp', 'r+');
        if (! is_resource($stream)) {
            throw new \RuntimeException('Could not prepare the loan disbursement export.');
        }

        fputcsv($stream, [
            'Loan ID',
            'Username',
            'Applicant Name',
            'Loan Type',
            'Requested Amount (USD)',
            'Account Name',
            'Account Number',
            'Bank Name',
            'Repayment Due Date',
        ], ',', '"', '');

        foreach ($loans as $loan) {
            $user = $loan->user;
            $name = trim(implode(' ', array_filter([
                $user?->first_name,
                $user?->last_name,
            ], fn (?string $part): bool => filled($part))));

            $row = [
                $loan->id,
                $user?->username ?? '',
                $name,
                $loan->loan_type?->label() ?? '',
                number_format((float) $loan->amount, 2, '.', ''),
                $loan->disbursement_account_name,
                $loan->disbursement_account_number,
                $loan->disbursement_bank_name,
                $loan->due_date?->toDateString() ?? '',
            ];

            fputcsv($stream, array_map(fn (mixed $value): string => $this->neutralizeCell((string) $value), $row), ',', '"', '');
        }

        rewind($stream);
        $content = (string) stream_get_contents($stream);
        fclose($stream);

        return $content;
    }

    protected function neutralizeCell(string $value): string
    {
        return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) === 1
            ? "'".$value
            : $value;
    }
}
