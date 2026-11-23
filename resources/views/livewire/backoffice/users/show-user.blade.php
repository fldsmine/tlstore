<div>
    <x-backoffice.page :title="$account->full_name" :subtitle="'@'.$account->username.' · #'.$account->id" :back="route('backoffice.users.index')">
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 lg:gap-6">
            {{-- ── Left: profile + wallets ─────────────────────────── --}}
            <div class="flex flex-col gap-4 lg:gap-6">
                <x-backoffice.card>
                    <div class="flex items-start gap-4">
                        <span class="size-16 rounded-2xl bg-primary/10 flex items-center justify-center font-heading font-semibold text-primary text-[20px] shrink-0">
                            {{ strtoupper(mb_substr($account->full_name ?: $account->username, 0, 2)) }}
                        </span>
                        <div class="min-w-0 flex-1 flex flex-col gap-1">
                            <p class="font-heading font-semibold text-text text-[17px] leading-tight">{{ $account->full_name }}
                                @if ($account->trashed())
                                    <x-backoffice.badge tone="danger">Deleted</x-backoffice.badge>
                                @endif
                            </p>
                            <p class="font-sans text-muted text-[12px] truncate">{{ '@'.$account->username }} · {{ $account->email }}</p>
                            <p class="font-sans text-muted text-[12px] truncate">{{ $account->phone ?: 'No phone' }} · {{ $account->country_code ?: '—' }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1.5 mt-4">
                        <x-backoffice.badge :tone="match ($account->status->value) { 'active' => 'success', 'restricted', 'suspended' => 'warning', 'pending' => 'info', 'banned' => 'danger', default => 'muted' }">{{ \Illuminate\Support\Str::headline($account->status->value) }}</x-backoffice.badge>
                        <x-backoffice.badge tone="info">{{ \Illuminate\Support\Str::headline($account->role?->value ?? 'reviewer') }}</x-backoffice.badge>
                        <x-backoffice.badge :tone="$account->two_factor ? 'success' : 'muted'">2FA {{ $account->two_factor ? 'on' : 'off' }}</x-backoffice.badge>
                        <x-backoffice.badge :tone="$account->is_banned ? 'danger' : 'muted'">{{ $account->is_banned ? 'Banned' : 'Not banned' }}</x-backoffice.badge>
                    </div>
                    @if ($account->is_banned && $account->ban_reason)
                        <div class="mt-3 rounded-[12px] bg-danger/8 border border-danger/20 px-3 py-2 font-sans text-[12px] text-danger">Ban reason: {{ $account->ban_reason }}</div>
                    @endif
                    <dl class="grid grid-cols-2 gap-3 mt-4 text-[12px]">
                        <div><dt class="text-muted">Joined</dt><dd class="text-text font-medium">{{ $account->created_at?->format('j M Y, H:i') }}</dd></div>
                        <div><dt class="text-muted">Wallet currency</dt><dd class="text-text font-medium">{{ $walletCurrency }}</dd></div>
                        <div><dt class="text-muted">Transactions</dt><dd class="text-text font-medium">{{ number_format($stats['transactions']) }}</dd></div>
                        <div><dt class="text-muted">Withdrawals</dt><dd class="text-text font-medium">{{ number_format($stats['withdrawals']) }}</dd></div>
                        <div><dt class="text-muted">Deposits</dt><dd class="text-text font-medium">{{ number_format($stats['deposits']) }}</dd></div>
                        <div><dt class="text-muted">Review tasks</dt><dd class="text-text font-medium">{{ number_format($stats['review_tasks']) }}</dd></div>
                    </dl>
                </x-backoffice.card>

                <x-backoffice.card title="Wallets">
                    @forelse ($wallets as $wallet)
                        <div class="flex items-center justify-between gap-3 py-2.5 border-b border-muted/10 last:border-0">
                            <span class="min-w-0">
                                <span class="block font-sans text-text text-[13px]">{{ \Illuminate\Support\Str::headline($wallet->wallet_type) }}</span>
                                <span class="block font-sans text-muted text-[11px]">{{ strtoupper($wallet->currency) }} · {{ $wallet->status }}</span>
                            </span>
                            <x-backoffice.money :minor="$wallet->balance_minor" :currency="$wallet->currency" class="font-sans text-text text-[13px] tabular-nums" />
                        </div>
                    @empty
                        <p class="font-sans text-muted text-[13px] py-3 text-center">No wallets yet.</p>
                    @endforelse
                </x-backoffice.card>
            </div>

            {{-- ── Middle + right: activity and actions ─────────────── --}}
            <div class="xl:col-span-2 flex flex-col gap-4 lg:gap-6">
                {{-- Account status --}}
                <x-backoffice.card title="Account status">
                    <form wire:submit="saveStatus" class="flex flex-col gap-3">
                        <div class="flex flex-col sm:flex-row gap-3">
                            <div class="flex-1 flex flex-col gap-1.5">
                                <label for="status" class="label">Status</label>
                                <select id="status" wire:model="status" class="input">
                                    @foreach (['active', 'restricted', 'suspended', 'pending', 'banned'] as $option)
                                        <option value="{{ $option }}">{{ \Illuminate\Support\Str::headline($option) }}</option>
                                    @endforeach
                                </select>
                                @error('status')<span class="error">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        @if ($status === 'banned')
                            <div class="flex flex-col gap-1.5">
                                <label for="banReason" class="label">Ban reason (required)</label>
                                <textarea id="banReason" wire:model="banReason" rows="2" class="input" placeholder="Why is this account banned?"></textarea>
                                @error('banReason')<span class="error">{{ $message }}</span>@enderror
                            </div>
                        @endif
                        <div class="flex items-center gap-2">
                            <button type="submit" class="btn btn-primary btn-md">
                                <i class="ri-save-line"></i> Save status
                            </button>
                            <span class="font-sans text-muted text-[12px]">Writes an audit entry.</span>
                        </div>
                    </form>
                </x-backoffice.card>

                {{-- Wallet adjustment --}}
                <x-backoffice.card title="Wallet adjustment (ledger)">
                    <p class="font-sans text-muted text-[12px] mb-3">Posts a signed ledger entry — never edits a balance directly. Idempotent per submission; double-submitting replays the same entry.</p>
                    <form wire:submit="applyWalletAdjustment" class="flex flex-col gap-3">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div class="col-span-2 sm:col-span-1 flex flex-col gap-1.5">
                                <label for="adjustWalletType" class="label">Wallet</label>
                                <select id="adjustWalletType" wire:model="adjustWalletType" class="input">
                                    @foreach (['review', 'review_group_b', 'review_group_c', 'share', 'referral', 'topup_fund', 'bonus'] as $type)
                                        <option value="{{ $type }}">{{ \Illuminate\Support\Str::headline(str_replace('_', ' ', $type)) }}</option>
                                    @endforeach
                                </select>
                                @error('adjustWalletType')<span class="error">{{ $message }}</span>@enderror
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label for="adjustDirection" class="label">Direction</label>
                                <select id="adjustDirection" wire:model="adjustDirection" class="input">
                                    <option value="credit">Credit</option>
                                    <option value="debit">Debit</option>
                                </select>
                                @error('adjustDirection')<span class="error">{{ $message }}</span>@enderror
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label for="adjustAmount" class="label">Amount ({{ $walletCurrency }})</label>
                                <input id="adjustAmount" type="number" min="0" step="0.01" wire:model="adjustAmount" inputmode="decimal" class="input" placeholder="0.00">
                                @error('adjustAmount')<span class="error">{{ $message }}</span>@enderror
                            </div>
                            <div class="col-span-2 sm:col-span-4 flex flex-col gap-1.5">
                                <label for="adjustReason" class="label">Reason (audit)</label>
                                <input id="adjustReason" type="text" wire:model="adjustReason" class="input" placeholder="Why is this adjustment needed?">
                                @error('adjustReason')<span class="error">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="submit" class="btn btn-primary btn-md">
                                <i class="ri-exchange-line"></i> Post adjustment
                            </button>
                            <span wire:loading wire:target="applyWalletAdjustment" class="font-sans text-muted text-[12px]">Posting…</span>
                        </div>
                    </form>
                </x-backoffice.card>

                {{-- Danger zone --}}
                <x-backoffice.card title="Lifecycle">
                    <div class="flex flex-col gap-3">
                        @if ($temporaryPassword)
                            <div class="rounded-[14px] border border-success/30 bg-success/8 px-4 py-3">
                                <p class="font-sans text-[12px] text-success font-semibold mb-1">Temporary password (shown once)</p>
                                <code class="block font-mono text-[13px] text-text select-all bg-surface rounded-[10px] px-3 py-2 break-all">{{ $temporaryPassword }}</code>
                                <div class="flex items-center justify-between mt-2 gap-2">
                                    <p class="font-sans text-[11px] text-muted">Share it through a secure channel. It is not stored in plain text and cannot be retrieved again.</p>
                                    <button type="button" wire:click="dismissTemporaryPassword" class="btn btn-quiet btn-sm shrink-0">Dismiss</button>
                                </div>
                            </div>
                        @endif

                        <div class="flex flex-wrap items-center gap-2">
                            <div class="flex items-center gap-2 w-full sm:w-auto">
                                <input type="text" wire:model="resetReason" class="input !h-[46px] flex-1 min-w-0" placeholder="Reason for password reset…" aria-label="Password reset reason">
                                <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'resetPassword', params: [], title: 'Reset password?', message: 'A new temporary password is generated and all existing sessions/tokens are revoked.', confirmText: 'Reset password', tone: 'warning' })"
                                        class="btn btn-quiet btn-md shrink-0">
                                    <i class="ri-key-line"></i> Reset password
                                </button>
                            </div>
                            @error('resetReason')<span class="error w-full">{{ $message }}</span>@enderror

                            @if ($account->trashed())
                                <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'restore', params: [], title: 'Restore account?', message: 'The account and its history will be visible again.', confirmText: 'Restore', tone: 'primary' })"
                                        class="btn btn-quiet btn-md">
                                    <i class="ri-arrow-go-back-line"></i> Restore account
                                </button>
                            @else
                                <div class="flex items-center gap-2 w-full sm:w-auto">
                                    <input type="text" wire:model="deleteReason" class="input !h-[46px] flex-1 min-w-0" placeholder="Reason for deletion…" aria-label="Deletion reason">
                                    <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'softDelete', params: [], title: 'Soft-delete account?', message: 'The account cannot sign in or use the app. Data is retained and the action is audited.', confirmText: 'Delete', tone: 'danger' })"
                                            class="btn btn-danger btn-md shrink-0">
                                        <i class="ri-delete-bin-line"></i> Delete
                                    </button>
                                </div>
                                @error('deleteReason')<span class="error">{{ $message }}</span>@enderror
                            @endif
                        </div>
                    </div>
                </x-backoffice.card>

                {{-- Recent activity --}}
                <x-backoffice.card title="Recent transactions">
                    @forelse ($transactions as $t)
                        <div class="flex items-center gap-3 py-2 border-b border-muted/10 last:border-0">
                            <span class="size-8 rounded-full bg-background flex items-center justify-center text-muted shrink-0"><i class="ri-exchange-line text-[15px]"></i></span>
                            <span class="flex-1 min-w-0">
                                <span class="block font-sans text-text text-[12px] truncate">{{ $t->type->getName() }} · {{ \Illuminate\Support\Str::headline($t->direction) }}</span>
                                <span class="block font-sans text-muted text-[11px] truncate">{{ $t->wallet }} · {{ $t->status }} · {{ $t->reference }}</span>
                            </span>
                            <span class="font-sans text-[12px] tabular-nums {{ $t->direction === 'credit' ? 'text-success' : 'text-text' }} shrink-0">{{ $t->direction === 'credit' ? '+' : '−' }}{{ \App\Support\BackofficeFormat::legacyDecimal($t->amount, $walletCurrency) }}</span>
                        </div>
                    @empty
                        <p class="font-sans text-muted text-[13px] py-4 text-center">No transactions yet.</p>
                    @endforelse
                </x-backoffice.card>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
                    <x-backoffice.card title="Recent withdrawals">
                        @forelse ($withdrawals as $w)
                            <div class="flex items-center gap-3 py-2 border-b border-muted/10 last:border-0">
                                <span class="flex-1 min-w-0">
                                    <span class="block font-sans text-text text-[12px]"><x-backoffice.money :minor="$w->amount_minor" :currency="$w->currency" /></span>
                                    <span class="block font-sans text-muted text-[11px] truncate">{{ \Illuminate\Support\Carbon::parse($w->requested_at)->format('j M, H:i') }}{{ $w->provider_reference ? ' · '.$w->provider_reference : '' }}</span>
                                </span>
                                <x-backoffice.badge :tone="match ($w->status->value) { 'pending', 'processing' => 'warning', 'paid' => 'success', 'rejected', 'failed' => 'danger', default => 'muted' }">{{ \Illuminate\Support\Str::headline($w->status->value) }}</x-backoffice.badge>
                            </div>
                        @empty
                            <p class="font-sans text-muted text-[13px] py-3 text-center">No withdrawals.</p>
                        @endforelse
                    </x-backoffice.card>

                    <x-backoffice.card title="Recent deposits">
                        @forelse ($deposits as $d)
                            <div class="flex items-center gap-3 py-2 border-b border-muted/10 last:border-0">
                                <span class="flex-1 min-w-0">
                                    <span class="block font-sans text-text text-[12px]"><x-backoffice.money :minor="$d->amount_minor" :currency="$d->currency" /></span>
                                    <span class="block font-sans text-muted text-[11px] truncate">{{ \Illuminate\Support\Carbon::parse($d->submitted_at)->format('j M, H:i') }}{{ $d->bank_reference ? ' · '.$d->bank_reference : '' }}</span>
                                </span>
                                <x-backoffice.badge :tone="match ($d->status) { 'submitted' => 'warning', 'approved' => 'success', 'rejected' => 'danger', default => 'muted' }">{{ \Illuminate\Support\Str::headline($d->status) }}</x-backoffice.badge>
                            </div>
                        @empty
                            <p class="font-sans text-muted text-[13px] py-3 text-center">No deposits.</p>
                        @endforelse
                    </x-backoffice.card>
                </div>
            </div>
        </div>
    </x-backoffice.page>
</div>
