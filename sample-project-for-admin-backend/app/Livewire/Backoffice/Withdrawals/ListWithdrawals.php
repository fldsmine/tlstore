<?php

namespace App\Livewire\Backoffice\Withdrawals;

use App\Enums\CouponType;
use App\Enums\TransactionType;
use App\Enums\WithdrawalStatus;
use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\Bank;
use App\Models\Coupon;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\WithdrawalPaymentSheetService;
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
#[Title('Withdrawals — Backoffice')]
class ListWithdrawals extends Component
{
    use AuthorizesAdminActions, WithPagination;

    #[Url]
    public string $status = 'pending';

    #[Url]
    public string $search = '';

    #[Url]
    public string $currencyFilter = '';

    #[Url]
    public string $bankTypeFilter = '';

    #[Url]
    public int $perPage = 100;

    public array $selected = [];

    public bool $selectPage = false;

    public ?string $rejectId = null;

    public string $rejectReason = '';

    public string $bulkRejectReason = '';

    public ?string $approveId = null;

    public string $amountToDeduct = '';

    public string $amountToPay = '';

    protected float $approveShortfall = 0.0;

    public ?string $detailId = null;

    public ?string $markPaidId = null;

    public ?string $markExportedId = null;

    public string $sheetProcessor = 'general';

    public string $sheetFormat = 'csv';

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

    /**
     * Clamp the admin-chosen page size to a safe allow-list (guards ?perPage= tampering).
     */
    protected function pageSize(): int
    {
        return in_array($this->perPage, [10, 20, 50, 100, 250, 500], true) ? $this->perPage : 20;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
        $this->reset('selected', 'selectPage');
    }

    public function updatedSelectPage(bool $value): void
    {
        $this->selected = $value
            ? $this->baseQuery()->paginate($this->pageSize())->pluck('id')->all()
            : [];
    }

    protected function baseQuery(): Builder
    {
        return Withdrawal::query()
            ->with('user')

            ->when($this->status === 'processing', fn (Builder $q) => $q->where('status', WithdrawalStatus::PROCESSING->value)->whereNull('exported_at'))
            ->when($this->status === 'exported', fn (Builder $q) => $q->where('status', WithdrawalStatus::PROCESSING->value)->whereNotNull('exported_at'))
            ->when(! in_array($this->status, ['all', 'processing', 'exported'], true), fn (Builder $q) => $q->where('status', $this->status))

            ->when(trim($this->currencyFilter) !== '', fn (Builder $q) => $q->where('local_currency', $this->currencyFilter))

            ->when($this->bankTypeFilter === 'commercial', fn (Builder $q) => $q->whereIn('meta->bank_code', Bank::query()->where('type', 'commercial')->pluck('code')))
            ->when($this->bankTypeFilter === 'non_commercial', fn (Builder $q) => $q->whereIn('meta->bank_code', Bank::query()->where('type', '!=', 'commercial')->pluck('code')))
            ->when(trim($this->search) !== '', function (Builder $q): void {
                $term = '%'.trim($this->search).'%';
                $q->where(fn (Builder $sub) => $sub
                    ->where('reference', 'like', $term)
                    ->orWhere('meta->account_name', 'like', $term)
                    ->orWhere('meta->account_number', 'like', $term)
                    ->orWhereHas('user', fn (Builder $u) => $u->where('username', 'like', $term)->orWhere('email', 'like', $term)));
            })
            ->latest();
    }

    public function openApprove(string $id): void
    {
        $this->authorizeWrite();
        $w = Withdrawal::query()->findOrFail($id);
        if ($w->status !== WithdrawalStatus::PENDING) {
            return;
        }
        $this->approveId = $w->id;
        $this->amountToDeduct = (string) $w->amount;
        $this->amountToPay = (string) $w->amount_paid;
        $this->resetValidation();
        $this->dispatch('open-approve');
    }

    public function confirmApprove(): void
    {
        $this->authorizeWrite();
        $this->validate(
            ['amountToDeduct' => ['required', 'numeric', 'min:0'], 'amountToPay' => ['required', 'numeric', 'min:0']],
            ['amountToDeduct.required' => 'Enter the amount to deduct.', 'amountToPay.required' => 'Enter the amount to pay.']
        );

        $this->approveShortfall = 0.0;
        $approved = $this->approveWithdrawal($this->approveId, (float) $this->amountToDeduct, (float) $this->amountToPay);
        $short = $approved && $this->approveShortfall > 0;

        $this->reset('approveId', 'amountToDeduct', 'amountToPay', 'detailId');
        $this->dispatch('close-approve');
        $this->dispatch('close-detail');
        $this->dispatch('toast',
            message: match (true) {
                $short => 'Approved — the payout was reduced to the amount actually debited from the wallet.',
                $approved => 'Withdrawal approved successfully',
                default => 'This withdrawal can no longer be approved.',
            },
            type: $approved && ! $short ? 'success' : 'warning');
    }

    public function openDetail(string $id): void
    {

        $this->detailId = $id;
        $this->dispatch('open-detail');
    }

    public function openMarkPaid(string $id): void
    {
        $this->authorizeWrite();
        $this->markPaidId = $id;
        $this->dispatch('open-markpaid');
    }

    public function confirmMarkPaid(): void
    {
        $this->authorizeWrite();
        $this->changeStatus($this->markPaidId, WithdrawalStatus::PROCESSING, WithdrawalStatus::COMPLETED, ['processed_at' => now()]);
        $this->reset('markPaidId', 'detailId');
        $this->dispatch('close-markpaid');
        $this->dispatch('close-detail');
        $this->dispatch('toast', message: 'Marked as paid successfully', type: 'success');
    }

    public function openMarkExported(string $id): void
    {
        $this->authorizeWrite();
        $this->markExportedId = $id;
        $this->dispatch('open-markexported');
    }

    public function confirmMarkExported(): void
    {
        $this->authorizeWrite();
        DB::transaction(function (): void {
            $w = Withdrawal::query()->whereKey($this->markExportedId)->lockForUpdate()->first();
            if ($w && $w->status === WithdrawalStatus::PROCESSING && $w->exported_at === null) {
                $w->update(['exported_at' => now()]);
            }
        });
        $this->reset('markExportedId', 'detailId');
        $this->dispatch('close-markexported');
        $this->dispatch('close-detail');
        $this->dispatch('toast', message: 'Marked as exported.', type: 'success');
    }

    public function markExportedSelected(): void
    {
        $this->authorizeWrite();
        $count = Withdrawal::query()
            ->whereKey($this->selected)
            ->where('status', WithdrawalStatus::PROCESSING->value)
            ->whereNull('exported_at')
            ->update(['exported_at' => now()]);

        $this->reset('selected', 'selectPage');
        $this->dispatch('close-markexported-bulk');
        $this->dispatch('toast',
            message: $count ? "{$count} marked as exported." : 'No withdrawals to mark as exported.',
            type: $count ? 'success' : 'warning');
    }

    public function approveSelected(): void
    {
        $this->authorizeWrite();

        $this->approveShortfall = 0.0;
        foreach ($this->selected as $id) {
            $this->approveWithdrawal($id, null, null);
        }
        $short = $this->approveShortfall > 0;
        $this->reset('selected', 'selectPage');
        $this->dispatch('close-approve-bulk');
        $this->dispatch('toast',
            message: $short
                ? 'Approved — some payouts were reduced to the amount actually debited from the wallet.'
                : 'Withdrawals approved successfully',
            type: $short ? 'warning' : 'success');
    }

    protected function approveWithdrawal(string $id, ?float $finalDeduct, ?float $amountToPay): bool
    {
        return (bool) DB::transaction(function () use ($id, $finalDeduct, $amountToPay): bool {
            $w = Withdrawal::query()->whereKey($id)->lockForUpdate()->first();
            if (! $w || $w->status !== WithdrawalStatus::PENDING) {
                return false;
            }

            $requested = (float) $w->amount;
            $finalDeduct = round($finalDeduct ?? $requested, 2);
            $amountToPay = round($amountToPay ?? (float) $w->amount_paid, 2);
            $column = User::WALLET_COLUMNS[$w->wallet] ?? null;
            $user = $column ? User::query()->whereKey($w->user_id)->lockForUpdate()->first() : null;

            $deducted = $user && $column ? round(min($finalDeduct, (float) $user->{$column}), 2) : $finalDeduct;

            $this->approveShortfall += max(0.0, round($finalDeduct - $deducted, 2))
                + max(0.0, round($amountToPay - $deducted, 2));
            $amountToPay = min($amountToPay, $deducted);
            $fee = max(0.0, round($deducted - $amountToPay, 2));
            $transactionId = $w->transaction_id;
            if ($user && $column) {
                if ($deducted > 0) {
                    $user->decrement($column, $deducted);
                }

                if ($transactionId === null) {
                    $meta = $w->meta ?? [];
                    $transactionId = Transaction::create([
                        'user_id' => $user->id,
                        'type' => TransactionType::WITHDRAWAL->value,
                        'direction' => 'debit',
                        'amount' => $deducted,
                        'fee' => $fee,
                        'wallet' => $w->wallet,
                        'status' => 'completed',
                        'reference' => $w->reference,
                        'description' => 'Withdrawal to '.($meta['bank_name'] ?? 'bank').' ('.($meta['account_number'] ?? '').')',
                        'meta' => array_merge($meta, [
                            'wallet_label' => User::WALLET_LABELS[$w->wallet] ?? null,
                            'net' => $amountToPay,
                            'approved_by' => auth('admin')->id(),
                        ]),
                    ])->id;
                }
            }

            $w->update([
                'amount' => $deducted,
                'amount_paid' => $amountToPay,

                'local_amount' => $w->toLocal($deducted),
                'local_amount_paid' => $w->toLocal($amountToPay),
                'fee' => $fee,
                'status' => WithdrawalStatus::PROCESSING->value,
                'processed_at' => now(),
                'transaction_id' => $transactionId,
                'meta' => array_merge($w->meta ?? [], [
                    'approved_by' => auth('admin')->id(),
                    'requested_amount' => $requested,
                    'final_deduct' => $finalDeduct,
                    'deducted' => $deducted,
                    'amount_to_pay' => $amountToPay,
                ]),
            ]);

            return true;
        });
    }

    protected function adjustmentTxn(User $user, Withdrawal $w, float $amount, string $direction, string $description, array $extraMeta = []): void
    {
        if ($amount <= 0) {
            return;
        }

        Transaction::create([
            'user_id' => $user->id,
            'type' => TransactionType::ADJUSTMENT->value,
            'direction' => $direction,
            'amount' => $amount,
            'wallet' => $w->wallet,
            'status' => 'completed',
            'reference' => 'ADJ-'.strtoupper(Str::random(10)),
            'description' => $description,
            'meta' => array_merge(['withdrawal_id' => $w->id, 'admin_id' => auth('admin')->id()], $extraMeta),
        ]);
    }

    public function markCompletedSelected(): void
    {
        $this->authorizeWrite();
        foreach ($this->selected as $id) {
            $this->changeStatus($id, WithdrawalStatus::PROCESSING, WithdrawalStatus::COMPLETED, ['processed_at' => now()]);
        }
        $this->reset('selected', 'selectPage');
        $this->dispatch('close-markpaid-bulk');
        $this->dispatch('toast', message: 'Withdrawals marked as paid successfully', type: 'success');
    }

    protected function changeStatus(string $id, WithdrawalStatus $from, WithdrawalStatus $to, array $extra = []): void
    {
        DB::transaction(function () use ($id, $from, $to, $extra): void {
            $w = Withdrawal::query()->whereKey($id)->lockForUpdate()->first();
            if ($w && $w->status === $from) {
                $w->update(array_merge(['status' => $to->value], $extra));
            }
        });
    }

    public function openReject(string $id): void
    {
        $this->authorizeWrite();
        $this->rejectId = $id;
        $this->rejectReason = '';
        $this->dispatch('open-reject');
    }

    public function confirmReject(): void
    {
        $this->authorizeWrite();
        $this->validate(['rejectReason' => ['nullable', 'string', 'max:200']]);

        $rejected = $this->rejectWithdrawal($this->rejectId, $this->rejectReason);

        $this->reset('rejectId', 'rejectReason', 'detailId');
        $this->dispatch('close-reject');
        $this->dispatch('close-detail');
        $this->dispatch('toast',
            message: $rejected ? 'Withdrawal rejected.' : 'This withdrawal can no longer be rejected.',
            type: $rejected ? 'success' : 'warning');
    }

    public function rejectSelected(): void
    {
        $this->authorizeWrite();
        $this->validate(['bulkRejectReason' => ['nullable', 'string', 'max:200']]);

        $count = 0;
        foreach ($this->selected as $id) {
            if ($this->rejectWithdrawal($id, $this->bulkRejectReason)) {
                $count++;
            }
        }

        $this->reset('selected', 'selectPage', 'bulkRejectReason');
        $this->dispatch('close-reject-bulk');
        $this->dispatch('toast',
            message: $count ? "{$count} withdrawal(s) rejected." : 'No withdrawals could be rejected.',
            type: $count ? 'success' : 'warning');
    }

    protected function rejectWithdrawal(?string $id, string $reason): bool
    {
        return (bool) DB::transaction(function () use ($id, $reason): bool {
            $w = Withdrawal::query()->whereKey($id)->lockForUpdate()->first();

            if (! $w || ! $w->status->isInProgress() || $w->exported_at !== null) {
                return false;
            }

            $deductAt = $w->meta['deduct_at'] ?? config("settings.withdrawal.wallets.{$w->wallet}.deduct_at", 'request');
            $isDeducted = ! ($deductAt === 'approval' && $w->status === WithdrawalStatus::PENDING);

            $user = User::query()->whereKey($w->user_id)->lockForUpdate()->first();
            $column = User::WALLET_COLUMNS[$w->wallet] ?? null;

            if ($isDeducted && $user && $column && (float) $w->amount > 0) {
                $user->increment($column, (float) $w->amount);
                $this->adjustmentTxn($user, $w, (float) $w->amount, 'credit', 'Refund: withdrawal '.$w->reference.' rejected', ['kind' => 'reject_refund', 'reason' => $reason]);
            }

            $meta = $w->meta ?? [];
            $authorizationCoupon = $this->authorizationCouponForWithdrawal($w);
            if ($authorizationCoupon !== null && $authorizationCoupon->used_at !== null) {
                // Releasing used_at makes this activated code available for a replacement
                // withdrawal. Keep user_id and expires_at intact: storage activation remains,
                // and an expired code does not become valid again.
                $authorizationCoupon->update(['used_at' => null]);
                $meta['authorization_coupon_id'] = $authorizationCoupon->id;
                $meta['authorization_coupon_released_at'] = now()->toIso8601String();
                $meta['authorization_coupon_released_by'] = auth('admin')->id();
            }

            $w->update([
                'status' => WithdrawalStatus::REJECTED->value,
                'processed_at' => now(),
                'meta' => array_merge($meta, ['rejection_reason' => $reason, 'rejected_by' => auth('admin')->id()]),
            ]);

            return true;
        });
    }

    /**
     * Return the exact special coupon consumed by a withdrawal, locking it within the rejection
     * transaction. Legacy requests predate the coupon-id snapshot; recover only a unique code used
     * at the request timestamp, and leave ambiguous histories untouched rather than freeing another
     * withdrawal's authorization code.
     */
    protected function authorizationCouponForWithdrawal(Withdrawal $withdrawal): ?Coupon
    {
        $query = Coupon::query()
            ->where('user_id', $withdrawal->user_id)
            ->where('type', CouponType::STORAGE_ACTIVATION->value)
            ->whereNotNull('used_at');

        $couponId = $withdrawal->meta['authorization_coupon_id'] ?? null;
        if (is_string($couponId) && $couponId !== '') {
            return $query->whereKey($couponId)->lockForUpdate()->first();
        }

        if (! in_array($withdrawal->wallet, ['cloud_call', 'cloud_sms'], true) || $withdrawal->created_at === null) {
            return null;
        }

        $windowStart = $withdrawal->created_at->copy()->subSecond();
        $windowEnd = $withdrawal->created_at->copy()->addSeconds(5);
        $candidates = $query
            ->whereBetween('used_at', [$windowStart, $windowEnd])
            ->lockForUpdate()
            ->limit(2)
            ->get();

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    public function generateSheet(): ?StreamedResponse
    {
        $this->authorizeWrite();
        $this->validate([
            'sheetProcessor' => ['required', 'in:'.implode(',', array_keys(WithdrawalPaymentSheetService::PROCESSORS))],
            'sheetFormat' => ['required', 'in:'.implode(',', array_keys(WithdrawalPaymentSheetService::FORMATS))],
        ]);

        // From the Completed tab this is a read-only export of already-paid payouts (no batch record,
        // no exported_at change); every other tab drives the real payment-batch flow.
        $completed = $this->status === 'completed';

        try {
            $service = app(WithdrawalPaymentSheetService::class);
            $result = $completed
                ? $service->generateCompleted($this->selected, $this->sheetProcessor, $this->sheetFormat)
                : $service->generate($this->selected, $this->sheetProcessor, $this->sheetFormat);
        } catch (ValidationException $e) {

            $this->dispatch('toast', message: $e->validator->errors()->first() ?: 'Could not generate the sheet.', type: 'warning');

            return null;
        }

        if ($result === null) {
            $this->dispatch('toast', message: $completed ? 'Select completed withdrawals to export.' : 'Select processing withdrawals to export.', type: 'warning');

            return null;
        }

        $this->reset('selected', 'selectPage');
        $this->dispatch('close-sheet');
        $this->dispatch('toast', message: $completed ? 'Sheet downloaded successfully' : 'Payment sheet generated successfully', type: 'success');

        return response()->streamDownload(
            fn () => print ($result['content']),
            $result['file_name'],
            ['Content-Type' => $result['mime_type']],
        );
    }

    #[Computed]
    public function approveTarget(): ?Withdrawal
    {
        return $this->approveId
            ? Withdrawal::query()->with('user')->find($this->approveId)
            : null;
    }

    #[Computed]
    public function approveDeductAt(): string
    {
        $w = $this->approveTarget;

        return $w
            ? ($w->meta['deduct_at'] ?? config("settings.withdrawal.wallets.{$w->wallet}.deduct_at", 'request'))
            : 'request';
    }

    #[Computed]
    public function rejectTarget(): ?Withdrawal
    {
        return $this->rejectId
            ? Withdrawal::query()->with('user')->find($this->rejectId)
            : null;
    }

    #[Computed]
    public function rejectRefund(): array
    {
        $w = $this->rejectTarget;

        if (! $w) {
            return ['amount' => 0.0, 'will_refund' => false];
        }

        $deductAt = $w->meta['deduct_at'] ?? config("settings.withdrawal.wallets.{$w->wallet}.deduct_at", 'request');
        $isDeducted = ! ($deductAt === 'approval' && $w->status === WithdrawalStatus::PENDING);

        return ['amount' => (float) $w->amount, 'will_refund' => $isDeducted && (float) $w->amount > 0];
    }

    #[Computed]
    public function bulkApprovePreview(): array
    {
        $rows = empty($this->selected)
            ? collect()
            : Withdrawal::query()->with('user')
                ->whereKey($this->selected)
                ->where('status', WithdrawalStatus::PENDING->value)
                ->get();

        return [
            'rows' => $rows,
            'count' => $rows->count(),
            'deduct' => (float) $rows->sum(fn (Withdrawal $w) => (float) $w->amount),
            'pay' => (float) $rows->sum(fn (Withdrawal $w) => (float) $w->amount_paid),
        ];
    }

    #[Computed]
    public function bulkRejectPreview(): array
    {
        $rows = empty($this->selected)
            ? collect()
            : Withdrawal::query()->with('user')
                ->whereKey($this->selected)
                ->get()
                ->filter(fn (Withdrawal $w) => $w->status->isInProgress() && $w->exported_at === null)
                ->values();

        $refund = (float) $rows->sum(function (Withdrawal $w): float {
            $deductAt = $w->meta['deduct_at'] ?? config("settings.withdrawal.wallets.{$w->wallet}.deduct_at", 'request');
            $isDeducted = ! ($deductAt === 'approval' && $w->status === WithdrawalStatus::PENDING);

            return $isDeducted ? (float) $w->amount : 0.0;
        });

        return [
            'rows' => $rows,
            'count' => $rows->count(),
            'amount' => (float) $rows->sum(fn (Withdrawal $w) => (float) $w->amount),
            'refund' => $refund,
        ];
    }

    #[Computed]
    public function detailTarget(): ?Withdrawal
    {
        return $this->detailId
            ? Withdrawal::query()->with('user')->find($this->detailId)
            : null;
    }

    #[Computed]
    public function markPaidTarget(): ?Withdrawal
    {
        return $this->markPaidId
            ? Withdrawal::query()->with('user')->find($this->markPaidId)
            : null;
    }

    #[Computed]
    public function bulkMarkPaidPreview(): array
    {
        $rows = empty($this->selected)
            ? collect()
            : Withdrawal::query()->with('user')
                ->whereKey($this->selected)
                ->where('status', WithdrawalStatus::PROCESSING->value)
                ->get();

        return [
            'rows' => $rows,
            'count' => $rows->count(),
            'pay' => (float) $rows->sum(fn (Withdrawal $w) => (float) $w->amount_paid),
        ];
    }

    #[Computed]
    public function markExportedTarget(): ?Withdrawal
    {
        return $this->markExportedId
            ? Withdrawal::query()->with('user')->find($this->markExportedId)
            : null;
    }

    #[Computed]
    public function bulkMarkExportedPreview(): array
    {
        $rows = empty($this->selected)
            ? collect()
            : Withdrawal::query()
                ->whereKey($this->selected)
                ->where('status', WithdrawalStatus::PROCESSING->value)
                ->whereNull('exported_at')
                ->get();

        return [
            'count' => $rows->count(),
            'pay' => (float) $rows->sum(fn (Withdrawal $w) => (float) $w->amount_paid),
        ];
    }

    public function render(): View
    {
        $withdrawals = $this->baseQuery()->paginate($this->pageSize());

        $totals = Withdrawal::query()->selectRaw('
            sum(case when status = ? then 1 else 0 end) as pending,
            sum(case when status = ? and exported_at is null then 1 else 0 end) as processing,
            sum(case when status = ? and exported_at is not null then 1 else 0 end) as exported
        ', [WithdrawalStatus::PENDING->value, WithdrawalStatus::PROCESSING->value, WithdrawalStatus::PROCESSING->value])->first();

        return view('livewire.backoffice.withdrawals.list-withdrawals', [
            'withdrawals' => $withdrawals,
            'pendingCount' => (int) $totals->pending,
            'processingCount' => (int) $totals->processing,
            'exportedCount' => (int) $totals->exported,
            'processors' => WithdrawalPaymentSheetService::PROCESSORS,
            'formats' => WithdrawalPaymentSheetService::FORMATS,
            'currencies' => Withdrawal::query()->select('local_currency')->whereNotNull('local_currency')->distinct()->orderBy('local_currency')->pluck('local_currency'),

            'selectedCurrencies' => $this->selected
                ? Withdrawal::query()->whereIn('id', $this->selected)->pluck('local_currency')->map(fn ($c) => $c ?: 'NGN')->unique()->sort()->values()->all()
                : [],
        ]);
    }

    public function updatedCurrencyFilter(): void
    {
        $this->reset('selected', 'selectPage');
        $this->resetPage();
    }

    public function updatedBankTypeFilter(): void
    {
        $this->reset('selected', 'selectPage');
        $this->resetPage();
    }
}
