<?php

namespace App\Livewire\Backoffice\Users;

use App\Application\Actions\Admin\AdjustWalletBalance;
use App\Enums\UserStatus;
use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\AuditLog;
use App\Models\ManualDepositSubmission;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Support\BackofficeFormat;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.backoffice')]
#[Title('User Details')]
class ShowUser extends Component
{
    use AuthorizesAdminActions;

    public int $userId;

    // Account-status form
    public string $status = '';
    public string $banReason = '';
    public string $resetReason = '';

    // Wallet adjustment form
    public string $adjustWalletType = 'review';
    public string $adjustDirection = 'credit';
    public string $adjustAmount = '';
    public string $adjustReason = '';
    public string $adjustIdempotencyKey = '';

    // Reset password / lifecycle
    public string $temporaryPassword = '';
    public string $deleteReason = '';

    public function mount(int $user): void
    {
        $this->authorizeAdmin();
        // withTrashed so admins can review/restore soft-deleted accounts.
        $account = User::withTrashed()->findOrFail($user);
        $this->userId = $account->id;
        $this->status = $account->status->value;
        $this->banReason = (string) $account->ban_reason;
        $this->adjustIdempotencyKey = (string) Str::uuid();
    }

    private function account(): User
    {
        return User::withTrashed()->findOrFail($this->userId);
    }

    public function saveStatus(): void
    {
        $admin = $this->authorizeAdmin();
        $account = $this->account();

        $data = $this->validate([
            'status' => ['required', 'in:active,restricted,suspended,pending,banned'],
            'banReason' => ['required_if:status,banned', 'nullable', 'string', 'max:2000'],
        ]);

        $before = ['status' => $account->status->value, 'is_banned' => $account->is_banned, 'ban_reason' => $account->ban_reason];

        $account->status = UserStatus::from($data['status']);
        if ($data['status'] === 'banned') {
            $account->is_banned = true;
            $account->ban_reason = $data['banReason'];
        } else {
            $account->is_banned = false;
            $account->ban_reason = null;
        }
        $account->save();

        AuditLog::query()->create([
            'actor_admin_id' => $admin->id,
            'action' => 'user.updated',
            'entity_type' => 'user',
            'entity_id' => (string) $account->id,
            'before_values' => $before,
            'after_values' => ['status' => $account->status->value, 'is_banned' => $account->is_banned, 'ban_reason' => $account->ban_reason],
            'created_at' => now(),
        ]);

        $this->dispatch('toast', message: 'Account status updated.', type: 'success');
    }

    public function applyWalletAdjustment(): void
    {
        $admin = $this->authorizeAdmin();
        $account = $this->account();

        $validated = $this->validate([
            'adjustWalletType' => ['required', Rule::in(AdjustWalletBalance::WALLET_TYPES)],
            'adjustDirection' => ['required', 'in:credit,debit'],
            'adjustAmount' => ['required', 'string', 'max:24'],
            'adjustReason' => ['required', 'string', 'min:4', 'max:500'],
        ]);

        $currency = strtoupper($account->wallet_currency ?: $account->preferred_currency ?: 'USD');
        $amountMinor = BackofficeFormat::inputToMinor($validated['adjustAmount'], $currency);
        if ($amountMinor === null || $amountMinor < 1) {
            $this->addError('adjustAmount', 'Enter a valid amount greater than zero.');

            return;
        }

        try {
            app(AdjustWalletBalance::class)->handle(
                $admin, $account, $validated['adjustWalletType'], $currency,
                $validated['adjustDirection'], $amountMinor, $validated['adjustReason'],
                $this->adjustIdempotencyKey,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError(in_array($field, ['amount_minor'], true) ? 'adjustAmount' : 'adjustReason', $messages[0]);
            }

            return;
        }

        // Rotate the idempotency key so a double click can never re-apply,
        // and the next adjustment is intentionally a new operation.
        $this->reset(['adjustAmount', 'adjustReason']);
        $this->adjustIdempotencyKey = (string) Str::uuid();

        $this->dispatch('toast', message: 'Wallet adjustment posted to the ledger.', type: 'success');
    }

    public function resetPassword(): void
    {
        $admin = $this->authorizeAdmin();
        $account = $this->account();
        if ($account->trashed()) {
            $this->dispatch('toast', message: 'A deleted account cannot have its password reset.', type: 'warning');

            return;
        }

        $temporaryPassword = Str::password(18);
        $account->forceFill(['password' => $temporaryPassword])->save();
        $account->tokens()->delete();

        AuditLog::query()->create([
            'actor_admin_id' => $admin->id,
            'action' => 'user.password_reset_by_admin',
            'entity_type' => 'user',
            'entity_id' => (string) $account->id,
            'after_values' => ['reason' => $data['resetReason'], 'tokens_revoked' => true],
            'created_at' => now(),
        ]);

        $this->temporaryPassword = $temporaryPassword;
        $this->reset('resetReason');
        $this->dispatch('toast', message: 'Password reset. The temporary password is shown once below.', type: 'success');
    }

    public function dismissTemporaryPassword(): void
    {
        $this->authorizeAdmin();
        $this->temporaryPassword = '';
    }

    public function softDelete(): void
    {
        $admin = $this->authorizeAdmin();
        $account = $this->account();
        $data = $this->validate([
            'deleteReason' => ['required', 'string', 'min:4', 'max:500'],
        ]);

        if ($account->trashed()) {
            $this->dispatch('toast', message: 'Account is already deleted.', type: 'warning');

            return;
        }

        $before = ['id' => $account->id, 'email' => $account->email, 'status' => $account->status->value];
        $account->tokens()->delete();
        $account->delete();

        AuditLog::query()->create([
            'actor_admin_id' => $admin->id,
            'action' => 'user.deleted',
            'entity_type' => 'user',
            'entity_id' => (string) $account->id,
            'before_values' => $before,
            'after_values' => ['reason' => $data['deleteReason']],
            'created_at' => now(),
        ]);

        $this->reset('deleteReason');
        $this->dispatch('toast', message: 'Account soft-deleted and sessions revoked.', type: 'success');
    }

    public function restore(): void
    {
        $admin = $this->authorizeAdmin();
        $account = $this->account();
        if (! $account->trashed()) {
            $this->dispatch('toast', message: 'Account is not deleted.', type: 'info');

            return;
        }
        $account->restore();

        AuditLog::query()->create([
            'actor_admin_id' => $admin->id,
            'action' => 'user.restored',
            'entity_type' => 'user',
            'entity_id' => (string) $account->id,
            'created_at' => now(),
        ]);

        $this->dispatch('toast', message: 'Account restored.', type: 'success');
    }

    public function render(): View
    {
        $this->authorizeAdmin();

        $account = $this->account();
        $currency = strtoupper($account->wallet_currency ?: $account->preferred_currency ?: 'USD');

        $wallets = $account->wallets()->orderBy('wallet_type')->orderBy('currency')->get(['id', 'wallet_type', 'currency', 'balance_minor', 'status']);

        $transactions = $account->transactions()->latest('created_at')->latest('id')->limit(15)
            ->get(['id', 'type', 'direction', 'amount', 'fee', 'wallet', 'status', 'reference', 'created_at']);

        $withdrawals = WithdrawalRequest::query()->where('user_id', $account->id)
            ->latest('id')->limit(10)->get(['id', 'amount_minor', 'currency', 'status', 'requested_at', 'provider_reference']);

        $deposits = ManualDepositSubmission::query()->where('user_id', $account->id)
            ->latest('id')->limit(10)->get(['id', 'amount_minor', 'currency', 'status', 'submitted_at', 'bank_reference']);

        return view('livewire.backoffice.users.show-user', [
            'account' => $account,
            'walletCurrency' => $currency,
            'wallets' => $wallets,
            'transactions' => $transactions,
            'withdrawals' => $withdrawals,
            'deposits' => $deposits,
            'stats' => [
                'transactions' => $account->transactions()->count(),
                'withdrawals' => WithdrawalRequest::query()->where('user_id', $account->id)->count(),
                'deposits' => ManualDepositSubmission::query()->where('user_id', $account->id)->count(),
                'review_tasks' => method_exists($account, 'reviewTasks') ? $account->reviewTasks()->count() : 0,
            ],
        ]);
    }
}
