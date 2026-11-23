<?php

namespace App\Livewire\Backoffice\Loans;

use App\Enums\LoanStatus;
use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\CloudStoragePlan;
use App\Models\Loan;
use App\Services\LoanDisbursementService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('components.layouts.backoffice')]
#[Title('Loans — Backoffice')]
class ListLoans extends Component
{
    use AuthorizesAdminActions, WithPagination;

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $search = '';

    #[Url]
    public int $perPage = 20;

    public array $selected = [];

    public bool $selectPage = false;

    public bool $showDetail = false;

    public ?string $detailId = null;

    public ?string $markPaidId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->reset('selected', 'selectPage');
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
        $this->reset('selected', 'selectPage');
    }

    protected function pageSize(): int
    {
        return in_array($this->perPage, [10, 20, 50, 100, 250, 500], true) ? $this->perPage : 20;
    }

    public function updatedSelected(): void
    {
        if (count($this->selected) > LoanDisbursementService::MAX_SELECTED_LOANS) {
            $this->selected = array_slice($this->selected, 0, LoanDisbursementService::MAX_SELECTED_LOANS);
            $this->dispatch('toast', message: 'Select no more than '.LoanDisbursementService::MAX_SELECTED_LOANS.' loans at a time.', type: 'warning');
        }
    }

    public function updatedSelectPage(bool $value): void
    {
        if (! $value || ! in_array($this->status, [LoanStatus::PROCESSING->value, LoanStatus::APPROVED->value], true)) {
            $this->selected = [];

            return;
        }

        $this->selected = $this->baseQuery()
            ->paginate($this->pageSize())
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();
    }

    public function openDetail(string $id): void
    {
        $this->detailId = $id;
        $this->showDetail = true;
    }

    public function setStatus(string $status): void
    {
        abort_unless($status === 'all' || LoanStatus::tryFrom($status) !== null, 422);

        $this->status = $status;
        $this->resetPage();
        $this->reset('selected', 'selectPage');
    }

    protected function baseQuery(): Builder
    {
        return Loan::query()
            ->with(['user', 'applicationFeeCoupon'])
            ->when($this->status !== 'all', fn (Builder $query) => $query->where('status', $this->status))
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $search = trim($this->search);
                $term = '%'.$search.'%';
                $query->where(function (Builder $sub) use ($search, $term): void {
                    if (Str::isUuid($search)) {
                        $sub->whereKey($search);
                    }

                    $sub->orWhere('disbursement_account_name', 'like', $term)
                        ->orWhere('disbursement_account_number', 'like', $term)
                        ->orWhere('disbursement_bank_name', 'like', $term)
                        ->orWhereHas('user', fn (Builder $user) => $user
                            ->where('username', 'like', $term)
                            ->orWhere('email', 'like', $term)
                            ->orWhere('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term));
                });
            })
            ->latest();
    }

    public function setLoanStatus(string $id, string $status): void
    {
        $this->authorizeWrite();
        $value = LoanStatus::tryFrom($status);
        abort_unless(in_array($value, [LoanStatus::APPROVED, LoanStatus::REJECTED], true), 422);

        $updated = DB::transaction(function () use ($id, $value): bool {
            $loan = Loan::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            // Only a pending review may be decided. Decisions remain explicit staff actions.
            if ($loan->status !== LoanStatus::PROCESSING) {
                return false;
            }

            $loan->update([
                'status' => $value,
                'rejection_reason' => $value === LoanStatus::REJECTED
                    ? (string) config('settings.loan.rejection_reason')
                    : null,
            ]);

            return true;
        });

        if (! $updated) {
            $this->dispatch('toast', message: 'This loan has already been reviewed.', type: 'warning');

            return;
        }

        $this->dispatch('toast', message: 'Loan '.$value->label().' successfully', type: 'success');
        $this->showDetail = false;
    }

    public function rejectSelected(): void
    {
        $this->authorizeWrite();

        if ($this->status !== LoanStatus::PROCESSING->value) {
            $this->dispatch('toast', message: 'Only pending loan requests can be rejected in bulk.', type: 'warning');

            return;
        }

        try {
            $ids = $this->normalizedSelection();
        } catch (ValidationException $exception) {
            $this->dispatch('toast', message: $exception->validator->errors()->first() ?: 'Could not reject the selected loans.', type: 'warning');

            return;
        }

        if ($ids === []) {
            $this->dispatch('toast', message: 'Select one or more pending loan requests to reject.', type: 'warning');

            return;
        }

        $count = DB::transaction(function () use ($ids): ?int {
            $loans = Loan::query()
                ->whereKey($ids)
                ->where('status', LoanStatus::PROCESSING->value)
                ->lockForUpdate()
                ->get();

            // Reject all selected rows or none if any request has already changed status.
            if ($loans->count() !== count($ids)) {
                return null;
            }

            foreach ($loans as $loan) {
                $loan->update([
                    'status' => LoanStatus::REJECTED->value,
                    'rejection_reason' => (string) config('settings.loan.rejection_reason'),
                ]);
            }

            return $loans->count();
        });

        if ($count === null) {
            $this->dispatch('toast', message: 'The selection changed; no loan request was rejected.', type: 'warning');

            return;
        }

        $this->reset('selected', 'selectPage', 'detailId', 'showDetail');
        $this->dispatch('toast', message: $count.' loan request(s) rejected.', type: 'success');
    }

    protected function normalizedSelection(): array
    {
        if (count($this->selected) > LoanDisbursementService::MAX_SELECTED_LOANS) {
            throw ValidationException::withMessages(['selection' => 'Reject no more than '.LoanDisbursementService::MAX_SELECTED_LOANS.' loan requests at a time.']);
        }

        $ids = [];
        foreach ($this->selected as $id) {
            if (! is_string($id) || trim($id) === '') {
                throw ValidationException::withMessages(['selection' => 'The loan selection contains an invalid ID.']);
            }

            $ids[] = trim($id);
        }

        return array_values(array_unique($ids));
    }

    public function extendDue(string $id): void
    {
        $this->authorizeWrite();
        $updated = DB::transaction(function () use ($id): bool {
            $loan = Loan::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($loan->status !== LoanStatus::APPROVED) {
                return false;
            }

            $base = $loan->due_date ?? today();
            $loan->update(['due_date' => $base->copy()->addDays(30)]);

            return true;
        });

        $this->dispatch('toast',
            message: $updated ? 'Due date extended successfully' : 'Only approved, unpaid loans can have their due date extended.',
            type: $updated ? 'success' : 'warning');
        $this->showDetail = false;
    }

    public function openMarkPaid(string $id): void
    {
        $this->authorizeWrite();
        $loan = Loan::query()->findOrFail($id);
        if ($loan->status !== LoanStatus::APPROVED) {
            $this->dispatch('toast', message: 'Only approved, unpaid loans can be marked as paid.', type: 'warning');

            return;
        }

        if (! $loan->hasCompletedGuarantorForm()) {
            $this->dispatch('toast', message: 'The applicant must pay the guarantor-form fee and submit the form before disbursement.', type: 'warning');

            return;
        }

        $this->markPaidId = $loan->id;
        $this->resetValidation();
        $this->dispatch('open-loan-markpaid');
    }

    public function confirmMarkPaid(): void
    {
        $this->authorizeWrite();
        try {
            $count = app(LoanDisbursementService::class)->markPaid(
                $this->markPaidId ? [$this->markPaidId] : [],
                (string) auth('admin')->id(),
            );
        } catch (ValidationException $exception) {
            $this->dispatch('close-loan-markpaid');
            $this->reset('markPaidId');
            $this->dispatch('toast', message: $exception->validator->errors()->first() ?: 'Could not mark this loan paid.', type: 'warning');

            return;
        }

        $this->reset('markPaidId', 'detailId', 'showDetail');
        $this->dispatch('close-loan-markpaid');
        $this->dispatch('toast',
            message: $count ? 'Loan marked as paid. No bank transfer was initiated.' : 'This loan is no longer awaiting disbursement.',
            type: $count ? 'success' : 'warning');
    }

    public function openExport(?string $id = null): void
    {
        $this->authorizeWrite();

        if ($id !== null) {
            $loan = Loan::query()->findOrFail($id);
            if ($loan->status !== LoanStatus::APPROVED) {
                $this->dispatch('toast', message: 'Only approved loans can be exported for disbursement.', type: 'warning');

                return;
            }

            $this->selected = [$loan->id];
        }

        if ($this->selected === []) {
            $this->dispatch('toast', message: 'Select one or more approved loans to export.', type: 'warning');

            return;
        }

        $this->dispatch('open-loan-export');
    }

    public function exportSelected(): ?StreamedResponse
    {
        $this->authorizeWrite();

        try {
            $result = app(LoanDisbursementService::class)->exportApproved(
                $this->selected,
                (string) auth('admin')->id(),
            );
        } catch (ValidationException $exception) {
            $this->dispatch('toast', message: $exception->validator->errors()->first() ?: 'Could not export loan details.', type: 'warning');

            return null;
        }

        if ($result === null) {
            $this->dispatch('toast', message: 'The selection changed or is no longer approved. No loan was marked as paid.', type: 'warning');

            return null;
        }

        $this->reset('selected', 'selectPage', 'detailId', 'showDetail');
        $this->dispatch('close-loan-export');
        $this->dispatch('toast', message: $result['count'].' loan(s) exported and marked as paid.', type: 'success');

        return response()->streamDownload(
            fn () => print ($result['content']),
            'nextel-loan-disbursements-'.now()->format('Ymd-His').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function openBulkMarkPaid(): void
    {
        $this->authorizeWrite();
        if ($this->selected === []) {
            $this->dispatch('toast', message: 'Select one or more approved loans to mark as paid.', type: 'warning');

            return;
        }

        $this->dispatch('open-loan-bulk-paid');
    }

    public function markSelectedPaid(): void
    {
        $this->authorizeWrite();
        try {
            $count = app(LoanDisbursementService::class)->markPaid(
                $this->selected,
                (string) auth('admin')->id(),
            );
        } catch (ValidationException $exception) {
            $this->dispatch('toast', message: $exception->validator->errors()->first() ?: 'Could not mark the selected loans paid.', type: 'warning');

            return;
        }

        $this->reset('selected', 'selectPage', 'detailId', 'showDetail');
        $this->dispatch('close-loan-bulk-paid');
        $this->dispatch('toast',
            message: $count ? $count.' loan(s) marked as paid. No bank transfer was initiated.' : 'The selection changed or is no longer awaiting disbursement.',
            type: $count ? 'success' : 'warning');
    }

    #[Computed]
    public function detailLoan(): ?Loan
    {
        return $this->detailId
            ? Loan::query()->with(['user', 'applicationFeeCoupon', 'paidBy'])->find($this->detailId)
            : null;
    }

    #[Computed]
    public function markPaidTarget(): ?Loan
    {
        return $this->markPaidId
            ? Loan::query()->with('user')->find($this->markPaidId)
            : null;
    }

    #[Computed]
    public function selectedApprovedPreview(): array
    {
        return $this->selectedLoanPreview(LoanStatus::APPROVED);
    }

    #[Computed]
    public function selectedProcessingPreview(): array
    {
        return $this->selectedLoanPreview(LoanStatus::PROCESSING);
    }

    protected function selectedLoanPreview(LoanStatus $status): array
    {
        if (count($this->selected) > LoanDisbursementService::MAX_SELECTED_LOANS) {
            return ['rows' => collect(), 'count' => 0, 'amount' => 0.0];
        }

        $rows = $this->selected === []
            ? collect()
            : Loan::query()->with('user')
                ->whereKey($this->selected)
                ->where('status', $status->value)
                ->orderBy('created_at')
                ->get();

        return [
            'rows' => $rows,
            'count' => $rows->count(),
            'amount' => (float) $rows->sum(fn (Loan $loan): float => (float) $loan->amount),
        ];
    }

    public function render(): View
    {
        $loans = $this->baseQuery()->paginate($this->pageSize());
        $rawStatusCounts = Loan::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $statusCounts = collect(LoanStatus::cases())
            ->mapWithKeys(fn (LoanStatus $loanStatus): array => [$loanStatus->value => (int) ($rawStatusCounts[$loanStatus->value] ?? 0)])
            ->all();

        // Cloud subscribers by plan — all-time count of currently-active packs per plan.
        $cloudStoragePlanDistribution = CloudStoragePlan::query()
            ->withCount(['packs as subs_count' => fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner->whereNull('expires_at')->orWhere('expires_at', '>', now())
            )])
            ->ordered()->get(['id', 'name'])
            ->map(fn (CloudStoragePlan $plan): array => ['name' => $plan->name, 'count' => (int) $plan->subs_count])
            ->all();
        $cloudSubTotal = array_sum(array_column($cloudStoragePlanDistribution, 'count'));
        $cloudStoragePlanDistribution = array_map(fn (array $row): array => $row + [
            'pct' => $cloudSubTotal > 0 ? (int) round($row['count'] / $cloudSubTotal * 100) : 0,
        ], $cloudStoragePlanDistribution);

        return view('livewire.backoffice.loans.list-loans', [
            'loans' => $loans,
            'statuses' => LoanStatus::cases(),
            'statusCounts' => $statusCounts,
            'cloudStoragePlanDistribution' => $cloudStoragePlanDistribution,
        ]);
    }
}
