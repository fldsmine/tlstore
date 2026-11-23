<?php

namespace App\Livewire\Backoffice\Users;

use App\Contracts\BankVerifier;
use App\Enums\BankAccountStatus;
use App\Enums\RewardTargetStatus;
use App\Enums\TransactionType;
use App\Enums\UserGender;
use App\Enums\UserStatus;
use App\Enums\WithdrawalStatus;
use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\AllocatedNumber;
use App\Models\Bank;
use App\Models\CloudStoragePlan;
use App\Models\Coupon;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ImageCompressionService;
use App\Services\Payments\RevenueCodeService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('User — Backoffice')]
class ShowUser extends Component
{
    use AuthorizesAdminActions, WithFileUploads, WithPagination;

    public string $userId;

    #[Url]
    public string $tab = 'overview';

    public string $txSearch = '';

    public string $txType = 'all';

    public string $txStatus = 'all';

    public ?string $selectedTxId = null;

    public string $refSearch = '';

    #[Url]
    public string $refLevel = 'direct';

    public string $refStatus = 'all';

    public ?string $selectedRefId = null;

    public string $numSearch = '';

    public string $numNetwork = 'all';

    public string $numKind = 'all';

    public string $wdSearch = '';

    #[Url]
    public string $creditTab = 'loans';

    public string $tokenSearch = '';

    /** Issue-a-revenue-code form (super admins only — the support fallback for card payments). */
    public string $revenuePlanId = '';

    public string $revenueReason = '';

    public ?string $issuedRevenueCode = null;

    public ?string $selectedWithdrawalId = null;

    public ?string $selectedClaimId = null;

    public ?string $selectedLoanId = null;

    public ?string $selectedCreditId = null;

    public string $adjustWallet = 'shareholder';

    public string $adjustDirection = 'credit';

    public string $adjustAmount = '';

    public string $adjustReason = '';

    public bool $adjustReviewing = false;

    public bool $showProfileModal = false;

    public $photo = null;

    public string $first_name = '';

    public string $last_name = '';

    public string $username = '';

    public string $email = '';

    public string $phone = '';

    public string $country = '';

    public string $gender = '';

    public string $biography = '';

    public ?string $generatedPassword = null;

    public bool $showBankModal = false;

    public string $bankId = '';

    public string $bankAccountNumber = '';

    public string $bankAccountName = '';

    public bool $bankVerified = false;

    public bool $bankVerifying = false;

    public string $bankVerifyError = '';

    public function mount(User $user): void
    {
        $this->userId = $user->id;
    }

    protected function user(): User
    {
        return User::query()->findOrFail($this->userId);
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetPage();
    }

    public function updatedTxSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTxType(): void
    {
        $this->resetPage();
    }

    public function updatedTxStatus(): void
    {
        $this->resetPage();
    }

    public function updatedRefSearch(): void
    {
        $this->resetPage();
    }

    public function setRefStatus(string $status): void
    {
        $this->refStatus = in_array($status, ['all', 'activated', 'pending'], true) ? $status : 'all';
        $this->resetPage();
    }

    public function setRefLevel(string $level): void
    {
        $this->refLevel = in_array($level, ['direct', 'indirect'], true) ? $level : 'direct';
        $this->selectedRefId = null;
        $this->resetPage();
    }

    public function showReferral(string $id): void
    {
        $this->selectedRefId = $id;
    }

    public function closeReferral(): void
    {
        $this->selectedRefId = null;
    }

    protected function referralsTabData(User $user): array
    {
        $directIds = $user->referrals()->pluck('id');

        $query = $this->refLevel === 'indirect'
            ? User::query()->whereIn('referrer_id', $directIds)
                ->with(['package', 'referrer:id,username,first_name,last_name'])
            : $user->referrals()->with('package');

        $query->latest()
            ->when($this->refStatus === 'activated', fn (Builder $q) => $q->whereNotNull('package_id'))
            ->when($this->refStatus === 'pending', fn (Builder $q) => $q->whereNull('package_id'));

        if (trim($this->refSearch) !== '') {
            $term = '%'.trim($this->refSearch).'%';
            $query->where(fn (Builder $q) => $q
                ->where('username', 'like', $term)
                ->orWhere('email', 'like', $term)
                ->orWhere('first_name', 'like', $term)
                ->orWhere('last_name', 'like', $term));
        }

        $selected = $this->selectedRefId
            ? User::query()->with(['package', 'referrer:id,username,first_name,last_name'])
                ->whereKey($this->selectedRefId)
                ->where(fn (Builder $q) => $q
                    ->where('referrer_id', $user->id)
                    ->orWhereIn('referrer_id', $directIds))
                ->first()
            : null;

        return [
            'referees' => $query->paginate(12),
            'selectedReferral' => $selected,
            'directCount' => $directIds->count(),
            'indirectCount' => User::query()->whereIn('referrer_id', $directIds)->count(),
        ];
    }

    public function showTransaction(string $id): void
    {
        $this->selectedTxId = $id;
    }

    public function closeDetails(): void
    {
        $this->selectedTxId = null;
    }

    protected function transactionsTabData(User $user): array
    {
        $query = $user->transactions()->latest()
            ->when($this->txType !== 'all', fn ($q) => $q->where('type', $this->txType))
            ->when($this->txStatus !== 'all', fn ($q) => $q->where('status', $this->txStatus));

        if (trim($this->txSearch) !== '') {
            $term = '%'.trim($this->txSearch).'%';
            $query->where(fn ($q) => $q->where('reference', 'like', $term)->orWhere('description', 'like', $term));
        }

        return [
            'transactions' => $query->paginate(12),
            'selectedTx' => $this->selectedTxId ? $user->transactions()->find($this->selectedTxId) : null,
            'txTypes' => TransactionType::cases(),
        ];
    }

    public function updatedNumSearch(): void
    {
        $this->resetPage();
    }

    public function updatedNumNetwork(): void
    {
        $this->resetPage();
    }

    public function updatedNumKind(): void
    {
        $this->resetPage();
    }

    protected function numbersTabData(User $user): array
    {
        $query = $user->allocatedNumbers()->latest();

        match ($this->numKind) {
            'permanent' => $query->whereNull('expires_at'),
            'temporary' => $query->whereNotNull('expires_at'),
            'expired' => $query->whereNotNull('expires_at')->where('expires_at', '<=', now()),
            default => null,
        };

        if ($this->numNetwork !== 'all' && isset(AllocatedNumber::NETWORKS[$this->numNetwork])) {
            $query->whereIn('prefix', AllocatedNumber::NETWORKS[$this->numNetwork]['prefixes']);
        }

        if (($digits = preg_replace('/\D/', '', $this->numSearch)) !== '') {
            $query->where(fn (Builder $q) => $q->where('prefix', 'like', '%'.$digits.'%')->orWhere('suffix', 'like', '%'.$digits.'%'));
        }

        return [
            'numbers' => $query->paginate(15),
            'numNetworks' => array_keys(AllocatedNumber::NETWORKS),
        ];
    }

    public function revokeNumber(string $id): void
    {
        $this->authorizeWrite();
        $number = $this->user()->allocatedNumbers()->whereKey($id)->firstOrFail();
        $this->safeDelete(fn () => $number->delete(), 'call ID', 'Call ID revoked successfully');
    }

    public function showWithdrawal(string $id): void
    {
        $this->selectedWithdrawalId = $id;
    }

    public function closeWithdrawal(): void
    {
        $this->selectedWithdrawalId = null;
    }

    public function showClaim(string $id): void
    {
        $this->selectedClaimId = $id;
    }

    public function closeClaim(): void
    {
        $this->selectedClaimId = null;
    }

    public function showLoan(string $id): void
    {
        $this->selectedLoanId = $id;
    }

    public function closeLoan(): void
    {
        $this->selectedLoanId = null;
    }

    public function showCredit(string $id): void
    {
        $this->selectedCreditId = $id;
    }

    public function closeCredit(): void
    {
        $this->selectedCreditId = null;
    }

    public function updatedWdSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTokenSearch(): void
    {
        $this->resetPage();
    }

    public function setCreditTab(string $tab): void
    {
        $this->creditTab = in_array($tab, ['loans', 'paylater'], true) ? $tab : 'loans';
        $this->selectedLoanId = null;
        $this->selectedCreditId = null;
        $this->resetPage();
    }

    protected function withdrawalsTabData(User $user): array
    {
        $query = $user->withdrawals()->latest();

        if (trim($this->wdSearch) !== '') {
            $term = '%'.trim($this->wdSearch).'%';
            $query->where(fn (Builder $q) => $q
                ->where('reference', 'like', $term)
                ->orWhere('meta->bank_name', 'like', $term)
                ->orWhere('meta->account_number', 'like', $term)
                ->orWhere('meta->account_name', 'like', $term));
        }

        return [
            'withdrawals' => $query->paginate(12),
            'selectedWithdrawal' => $this->selectedWithdrawalId ? $user->withdrawals()->find($this->selectedWithdrawalId) : null,
        ];
    }

    protected function claimsTabData(User $user): array
    {
        return [
            'claims' => $user->callClaims()->latest('claimed_at')->paginate(15),
            'selectedClaim' => $this->selectedClaimId ? $user->callClaims()->with(['callType', 'callRecord'])->find($this->selectedClaimId) : null,
        ];
    }

    protected function creditTabData(User $user): array
    {

        $loans = $this->creditTab === 'loans'
            ? $user->loans()->latest()->paginate(15)
            : null;
        $payLaters = $this->creditTab === 'paylater'
            ? $user->minuteCredits()->with('plan')->latest()->paginate(15)
            : null;

        return [
            'creditTab' => $this->creditTab,
            'loansCount' => (int) $user->loans_count,
            'payLaterCount' => (int) $user->minute_credits_count,
            'loans' => $loans,
            'payLaters' => $payLaters,
            'selectedLoan' => $this->selectedLoanId ? $user->loans()->find($this->selectedLoanId) : null,
            'selectedCredit' => $this->selectedCreditId ? $user->minuteCredits()->with('plan')->find($this->selectedCreditId) : null,
        ];
    }

    protected function tokensTabData(User $user): array
    {
        if (! $user->is_agent) {
            return ['batches' => null];
        }

        $query = $user->couponBatches()
            ->withCount(['coupons', 'coupons as sold_count' => fn (Builder $q) => $q->whereNotNull('user_id')])
            ->latest();

        if (trim($this->tokenSearch) !== '') {
            $query->where('reference', 'like', '%'.trim($this->tokenSearch).'%');
        }

        return [
            'batches' => $query->paginate(12),
            'tokensTotal' => (int) $user->issued_coupons_count,
        ];
    }

    /**
     * Hand a user a Revenue Authentication code against payment proof (support fallback).
     *
     * This is the manual half of the "Pay directly with card" flow: the automatic path issues the
     * code the moment a charge verifies, and this covers what it cannot — a payment the gateway
     * flagged for review, a transfer that never reached the checkout, a code the buyer lost.
     *
     * Super admins only: the code is bound to the user, applies its storage bonus immediately, and
     * authorises a real N-Cloud withdrawal, so it is never issued on an ordinary admin's say-so.
     */
    public function issueRevenueCode(): void
    {
        $this->authorizeWrite();

        abort_unless(auth('admin')->user()?->isSuper() ?? false, 403);

        $data = $this->validate([
            'revenuePlanId' => ['required', 'string', Rule::exists('cloud_storage_plans', 'id')],
            'revenueReason' => ['nullable', 'string', 'max:200'],
        ], [
            'revenuePlanId.required' => 'Choose the storage plan this code grants.',
        ]);

        $user = User::query()->findOrFail($this->userId);
        $plan = CloudStoragePlan::query()->findOrFail($data['revenuePlanId']);

        try {
            // Same service the card purchase settles through, so a manually issued code and a bought
            // one are the same object: bound, storage applied, spendable on a withdrawal.
            $issued = app(RevenueCodeService::class)->issueFor(
                $user,
                $plan,
                Coupon::SOURCE_ADMIN,
                null,
                auth('admin')->user(),
                filled($data['revenueReason'] ?? null) ? (string) $data['revenueReason'] : null,
            );
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return;
        }

        $this->issuedRevenueCode = $issued['code'];
        $this->revenueReason = '';

        $this->dispatch('toast', message: 'Revenue code issued to '.$user->username.' — its storage is already active on their account.', type: 'success');
    }

    public function setStatus(string $status): void
    {
        $this->authorizeWrite();
        $value = UserStatus::tryFrom($status);
        abort_if($value === null, 422);
        $this->user()->update(['status' => $value->value]);
        $this->dispatch('toast', message: $value === UserStatus::SUSPENDED ? 'User suspended successfully' : 'User activated successfully', type: 'success');
    }

    public function toggleFlag(string $flag): void
    {
        $this->authorizeWrite();
        abort_unless(in_array($flag, ['withdrawals_disabled', 'is_agent', 'show_on_agents_page', 'is_special'], true), 422);
        $user = $this->user();
        $newState = ! $user->{$flag};
        $user->update([$flag => $newState]);

        $messages = [
            'withdrawals_disabled' => $newState ? 'Withdrawals blocked successfully' : 'Withdrawals unblocked successfully',
            'is_agent' => $newState ? 'Agent access granted successfully' : 'Agent access revoked successfully',
            'show_on_agents_page' => $newState ? 'Agent listed on the public page successfully' : 'Agent hidden from the public page successfully',
            'is_special' => $newState ? 'Marked as a special vendor (can transfer funds)' : 'Special-vendor access revoked',
        ];
        $this->dispatch('toast', message: $messages[$flag], type: 'success');
    }

    public function openEditProfile(): void
    {
        $this->authorizeWrite();
        $user = $this->user();
        $this->first_name = (string) $user->first_name;
        $this->last_name = (string) $user->last_name;
        $this->username = (string) $user->username;
        $this->email = (string) $user->email;
        $this->phone = (string) $user->phone;
        $this->country = (string) $user->country;
        $this->gender = $user->gender?->value ?? '';
        $this->biography = (string) $user->biography;
        $this->photo = null;
        $this->resetValidation();
        $this->showProfileModal = true;
    }

    public function updatedPhoto(): void
    {
        $this->authorizeWrite();
        $this->validateOnly('photo', ['photo' => ['nullable', 'image', 'max:4096']]);
    }

    public function saveProfile(): void
    {
        $this->authorizeWrite();

        $this->country = strtoupper(trim((string) $this->country));

        $this->validate([
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['nullable', 'string', 'max:50'],
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($this->userId)],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->userId)],
            'phone' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', Rule::exists('countries', 'code')->where('is_active', true)],
            'gender' => ['nullable', Rule::in(UserGender::values())],
            'biography' => ['nullable', 'string', 'max:300'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);

        $data = [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'country' => $this->country,
            'gender' => $this->gender ?: null,
            'biography' => $this->biography ?: null,
        ];

        if ($this->photo) {

            $data['avatar'] = app(ImageCompressionService::class)->compressAndStore($this->photo, 'avatars', null, 82, 512, 512);
        }

        $this->user()->update($data);

        $this->photo = null;
        $this->showProfileModal = false;
        $this->dispatch('toast', message: 'Profile updated successfully', type: 'success');
    }

    public function resetPassword(): void
    {
        $this->authorizeWrite();
        $password = Str::password(12);
        $this->user()->update(['password' => Hash::make($password)]);
        $this->generatedPassword = $password;
        $this->dispatch('toast', message: 'Password reset successfully', type: 'success');
    }

    protected function adjustmentRules(): array
    {

        $allowedWallets = array_keys(User::WALLET_COLUMNS);
        if (! $this->user()->is_agent) {
            $allowedWallets = array_values(array_diff($allowedWallets, ['agent']));
        }

        return [
            'adjustWallet' => ['required', 'in:'.implode(',', $allowedWallets)],
            'adjustDirection' => ['required', 'in:credit,debit'],
            'adjustAmount' => ['required', 'numeric', 'min:0.01'],
            'adjustReason' => ['required', 'string', 'max:200'],
        ];
    }

    public function reviewAdjustment(): void
    {
        $this->authorizeWrite();
        $this->validate($this->adjustmentRules());
        $this->adjustReviewing = true;
    }

    public function saveAdjustment(): void
    {
        $this->authorizeWrite();
        $this->validate($this->adjustmentRules());

        $column = User::WALLET_COLUMNS[$this->adjustWallet];
        $amount = round((float) $this->adjustAmount, 6);

        DB::transaction(function () use ($column, $amount): void {

            $user = User::query()->whereKey($this->userId)->lockForUpdate()->firstOrFail();

            if ($this->adjustDirection === 'debit') {
                if ((float) $user->{$column} < $amount) {
                    throw ValidationException::withMessages([
                        'adjustAmount' => 'Insufficient balance in this wallet ('.money((float) $user->{$column}).').',
                    ]);
                }
                $user->decrement($column, $amount);
            } else {
                $user->creditEarnings($column, $amount);
            }

            Transaction::create([
                'user_id' => $user->id,
                'type' => TransactionType::ADJUSTMENT->value,
                'direction' => $this->adjustDirection,
                'amount' => $amount,
                'wallet' => $this->adjustWallet,
                'status' => 'completed',
                'reference' => 'ADJ-'.strtoupper(Str::random(10)),
                'description' => $this->adjustReason,
                'meta' => ['admin_id' => auth('admin')->id(), 'admin' => auth('admin')->user()->name],
            ]);
        });

        $this->reset(['adjustAmount', 'adjustReason']);
        $this->adjustReviewing = false;
        $this->dispatch('adjustment-saved');
        $this->dispatch('toast', message: 'Wallet adjusted successfully', type: 'success');
    }

    /**
     * Reverse a completed adjustment: undo its wallet + lifetime-earnings effect and
     * mark it failed. The inverse of saveAdjustment() — a credit is pulled back out
     * (never below zero) and its lifetime bump unwound; a debit is returned.
     */
    public function reverseAdjustment(string $transactionId): void
    {
        $this->authorizeWrite();

        DB::transaction(function () use ($transactionId): void {
            $tx = Transaction::query()
                ->whereKey($transactionId)
                ->where('user_id', $this->userId)
                ->where('type', TransactionType::ADJUSTMENT->value)
                ->lockForUpdate()
                ->firstOrFail();

            if ($tx->status !== 'completed') {
                throw ValidationException::withMessages(['reverse' => 'Only a completed adjustment can be reversed.']);
            }

            $column = User::WALLET_COLUMNS[$tx->wallet] ?? null;

            if (! $column) {
                throw ValidationException::withMessages(['reverse' => 'This adjustment has no wallet to reverse.']);
            }

            $user = User::query()->whereKey($this->userId)->lockForUpdate()->firstOrFail();
            $amount = round((float) $tx->amount, 6);

            if ($tx->direction === 'credit') {
                // Pull the credited amount back out — the balance may go negative if it was already spent.
                $user->decrement($column, $amount);

                // Unwind the lifetime-earnings bump (creditEarnings skips agent/bills wallets).
                if (! in_array($column, ['agent_balance', 'bills_balance'], true)) {
                    $user->decrement('lifetime_earnings', $amount);
                }
            } else {
                // Return a debited amount. Debits never touched lifetime_earnings.
                $user->increment($column, $amount);
            }

            $tx->forceFill([
                'status' => 'failed',
                'meta' => array_merge((array) $tx->meta, [
                    'reversed_by' => auth('admin')->id(),
                    'reversed_by_name' => auth('admin')->user()?->name,
                    'reversed_at' => now()->toIso8601String(),
                ]),
            ])->save();
        });

        $this->selectedTxId = null;
        $this->dispatch('toast', message: 'Adjustment reversed — wallet and earnings restored', type: 'success');
    }

    public function openBank(): void
    {
        $this->authorizeWrite();
        $account = $this->user()->bankAccount;
        $this->bankId = $account?->bank_id ?? '';
        $this->bankAccountNumber = $account?->account_number ?? '';
        $this->bankAccountName = $account?->account_name ?? '';
        $this->bankVerified = (bool) $account;
        $this->bankVerifyError = '';
        $this->resetValidation();
        $this->showBankModal = true;
    }

    public function updatedBankId(): void
    {
        $this->resetBankVerification();
        $this->maybeVerifyBank();
    }

    public function updatedBankAccountNumber(): void
    {
        $this->bankAccountNumber = preg_replace('/\D/', '', (string) $this->bankAccountNumber);
        $this->resetBankVerification();
        $this->maybeVerifyBank();
    }

    private function resetBankVerification(): void
    {
        $this->bankVerified = false;
        $this->bankAccountName = '';
        $this->bankVerifyError = '';
    }

    private function maybeVerifyBank(): void
    {
        if ($this->bankId && strlen($this->bankAccountNumber) === 10) {
            $this->verifyBank();
        }
    }

    public function verifyBank(): void
    {
        $this->authorizeWrite();
        $this->validate([
            'bankId' => ['required', 'exists:banks,id'],
            'bankAccountNumber' => ['required', 'digits:10'],
        ]);

        $this->bankVerifying = true;
        $this->bankVerified = false;
        $this->bankAccountName = '';
        $this->bankVerifyError = '';

        $bank = Bank::findOrFail($this->bankId);
        $result = app(BankVerifier::class)->resolveBankAccount($this->bankAccountNumber, $bank->code);

        if (($result['status'] ?? '') === 'success' && ! empty($result['data']['account_name'])) {
            $this->bankAccountName = $result['data']['account_name'];
            $this->bankVerified = true;
        } else {
            $this->bankVerifyError = $result['message'] ?? 'Could not verify this account.';
        }

        $this->bankVerifying = false;
    }

    public function saveBank(): void
    {
        $this->authorizeWrite();

        if (! $this->bankVerified) {
            $this->addError('bankAccountNumber', 'Verify the account before saving.');

            return;
        }

        $this->validate([
            'bankId' => ['required', 'exists:banks,id'],
            'bankAccountNumber' => ['required', 'digits:10'],
            'bankAccountName' => ['required', 'string'],
        ]);

        $this->user()->bankAccounts()->updateOrCreate(
            [],
            [
                'bank_id' => $this->bankId,
                'account_number' => $this->bankAccountNumber,
                'account_name' => $this->bankAccountName,
                'status' => BankAccountStatus::VERIFIED,
            ]
        );

        $this->showBankModal = false;
        $this->dispatch('toast', message: 'Bank account saved successfully', type: 'success');
    }

    public function render(): View
    {
        $user = User::query()
            ->with(['package', 'referrer', 'bankAccount.bank', 'debitCard', 'telegramIdentity'])
            ->withCount([
                'allocatedNumbers',
                'referrals',
                'transactions',
                'callClaims',
                'withdrawals',
                'loans',
                'minuteCredits',
                'adShares',
                'couponBatches',
                'issuedCoupons',
                'issuedCoupons as tokens_sold_count' => fn (Builder $q) => $q->whereNotNull('user_id'),
                'rewardTargets as reward_targets_hit_count' => fn (Builder $q) => $q->whereIn('status', [RewardTargetStatus::ACHIEVED->value, RewardTargetStatus::CLAIMED->value]),
            ])
            ->findOrFail($this->userId);

        $withdrawnTotal = (float) $user->withdrawals()
            ->where('status', WithdrawalStatus::COMPLETED->value)
            ->sum('amount_paid');

        $tabData = match ($this->tab) {
            'transactions' => $this->transactionsTabData($user),
            'referrals' => $this->referralsTabData($user),
            'numbers' => $this->numbersTabData($user),
            'withdrawals' => $this->withdrawalsTabData($user),
            'claims' => $this->claimsTabData($user),
            'credit' => $this->creditTabData($user),
            'tokens' => $this->tokensTabData($user),
            default => [],
        };

        // Revenue codes are a super-admin instrument: offer the form only to them, and only for the
        // plans published for this user's tier (the service re-checks the tier anyway).
        $admin = auth('admin')->user();
        $isSuper = $admin?->isSuper() ?? false;
        $revenueTier = $user->package?->tier_level;

        $revenuePlans = $isSuper && $revenueTier !== null
            ? CloudStoragePlan::query()->active()->forTier((int) $revenueTier)->ordered()->get()
            : collect();

        return view('livewire.backoffice.users.show-user', array_merge([
            'isSuper' => $isSuper,
            'revenuePlans' => $revenuePlans,
            'revenueTier' => $revenueTier,
            'user' => $user,
            'withdrawnTotal' => $withdrawnTotal,
            'statuses' => UserStatus::cases(),
            'genders' => UserGender::cases(),

            'selectedWithdrawal' => null,
            'selectedClaim' => null,
            'selectedLoan' => null,
            'selectedCredit' => null,

            'walletLabels' => $user->is_agent ? User::WALLET_LABELS : Arr::except(User::WALLET_LABELS, 'agent'),
            'banks' => Bank::query()->where('is_active', true)->orderBy('name')->get(),
        ], $tabData));
    }
}
