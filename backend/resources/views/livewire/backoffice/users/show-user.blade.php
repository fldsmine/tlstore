<div>
    <x-backoffice.page title="User profile" :back="route('backoffice.users.index')">
        @php
            $statusTone = ['active' => 'success', 'suspended' => 'warning'];
            $canWrite = $this->canWrite();
            $input = 'h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40';
            $selectCls = 'h-[44px] lg:h-[40px] rounded-[12px] lg:rounded-full bg-surface border-0 px-3.5 font-sans text-[13px] lg:text-[12px] text-muted shadow-sm focus:ring-2 focus:ring-primary/40';
            $flagPrompts = [
                'withdrawals_disabled' => [
                    'on' => ['title' => 'Block withdrawals', 'confirm' => 'Block', 'tone' => 'danger', 'message' => 'This user will not be able to withdraw until you unblock them.'],
                    'off' => ['title' => 'Unblock withdrawals', 'confirm' => 'Unblock', 'tone' => 'primary', 'message' => 'This user will be able to withdraw again.'],
                ],
                'is_agent' => [
                    'on' => ['title' => 'Grant agent access', 'confirm' => 'Grant', 'tone' => 'primary', 'message' => 'Unlocks a vendor float wallet and token issuing for this user.'],
                    'off' => ['title' => 'Revoke agent access', 'confirm' => 'Revoke', 'tone' => 'danger', 'message' => 'Removes vendor abilities from this user.'],
                ],
                'show_on_agents_page' => [
                    'on' => ['title' => 'Show on agents page', 'confirm' => 'Show', 'tone' => 'primary', 'message' => 'List this agent publicly on the agents page.'],
                    'off' => ['title' => 'Hide from agents page', 'confirm' => 'Hide', 'tone' => 'primary', 'message' => 'Remove this agent from the public agents page.'],
                ],
                'is_special' => [
                    'on' => ['title' => 'Make special vendor', 'confirm' => 'Grant', 'tone' => 'primary', 'message' => 'Lets this vendor transfer their vendor wallet to other vendors (any country).'],
                    'off' => ['title' => 'Revoke special vendor', 'confirm' => 'Revoke', 'tone' => 'danger', 'message' => 'This vendor will no longer be able to transfer funds to others.'],
                ],
            ];
        @endphp

        @if ($generatedPassword)
            <div class="flex items-start gap-3 rounded-[16px] bg-success/10 border border-success/25 px-4 py-3.5">
                <i class="ri-key-2-line text-success text-[20px] shrink-0 mt-0.5"></i>
                <div class="flex-1 min-w-0">
                    <p class="font-heading font-medium text-text text-[14px]">New password (shown once)</p>
                    <p class="font-sans text-text text-[13px] mt-1 flex items-center gap-2.5">
                        <span class="font-mono">{{ $generatedPassword }}</span>
                        <button type="button" x-data="{ copied: false }"
                                @click="nextelCopy('{{ $generatedPassword }}').then(ok => { if (!ok) return; copied = true; setTimeout(() => { copied = false }, 1500) })"
                                class="inline-flex items-center gap-1 rounded-full bg-success/15 px-2.5 py-1 font-sans text-[12px] text-success hover:bg-success/25 transition">
                            <i class="ri-file-copy-line"></i><span x-text="copied ? 'Copied' : 'Copy'"></span>
                        </button>
                    </p>
                </div>
                <button type="button" wire:click="$set('generatedPassword', null)" class="shrink-0 text-success"><i class="ri-close-line"></i></button>
            </div>
        @endif

        <x-backoffice.card>
            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                <span class="size-16 rounded-full bg-primary/10 overflow-hidden flex items-center justify-center font-heading text-primary text-[20px] shrink-0">
                    @if ($user->avatar)<img src="{{ $user->avatar }}" alt="" class="size-full object-cover">@else{{ strtoupper(substr($user->first_name ?: $user->username, 0, 2)) }}@endif
                </span>
                <div class="flex-1 min-w-0">
                    <p class="font-heading font-medium text-text text-[18px]">{{ $user->full_name }}</p>
                    <p class="font-sans text-muted text-[13px]">{{ $user->email }} · {{ '@'.$user->username }}</p>
                    <div class="flex flex-wrap items-center gap-2 mt-2">
                        <x-backoffice.badge :tone="$statusTone[$user->status->value] ?? 'muted'">{{ $user->status->getName() }}</x-backoffice.badge>
                        @if ($user->email_verified_at)
                            <x-backoffice.badge tone="success"><i class="ri-mail-check-line"></i> Email Verified</x-backoffice.badge>
                        @else
                            <x-backoffice.badge tone="warning"><i class="ri-mail-close-line"></i> Email Unverified</x-backoffice.badge>
                        @endif
                        @if ($user->is_agent)<x-backoffice.badge tone="info"><i class="ri-store-2-line"></i> Agent</x-backoffice.badge>@endif
                        @if ($user->is_special)<x-backoffice.badge tone="purple"><i class="ri-vip-crown-line"></i> Special</x-backoffice.badge>@endif
                        @if ($user->telegramIdentity)
                            <a href="{{ route('backoffice.telegram-users.show', $user->telegramIdentity) }}" wire:navigate>
                                <x-backoffice.badge tone="blue"><i class="ri-telegram-line"></i> Mini App</x-backoffice.badge>
                            </a>
                        @endif
                        @if ($user->withdrawals_disabled)<x-backoffice.badge tone="danger"><i class="ri-forbid-line"></i> Payout Disabled</x-backoffice.badge>@endif
                    </div>
                </div>
                @if ($canWrite)
                    <div class="flex flex-wrap gap-1.5 sm:flex-col sm:items-stretch">
                        <button type="button" wire:click="openEditProfile" class="h-[34px] px-3.5 rounded-full bg-primary text-white font-sans text-[12px]">Edit profile</button>
                        <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'resetPassword', params: [], title: 'Reset password', message: 'Reset this account password to a new random one?', confirmText: 'Reset password', tone: 'primary' })" class="h-[34px] px-3.5 rounded-full bg-background text-text font-sans text-[12px]">Reset password</button>
                        <button type="button" wire:click="openBank" class="h-[34px] px-3.5 rounded-full bg-background text-text font-sans text-[12px]">Manage bank</button>
                        <form method="POST" action="{{ route('backoffice.users.impersonate', $user) }}" class="contents">
                            @csrf
                            <button type="submit" class="h-[34px] px-3.5 rounded-full bg-background text-text font-sans text-[12px] inline-flex items-center justify-center gap-1.5"><i class="ri-spy-line"></i> Impersonate</button>
                        </form>
                    </div>
                @endif
            </div>
        </x-backoffice.card>

        @php
            $tabLabels = [
                'overview' => 'Overview',
                'transactions' => 'Transactions ('.number_format($user->transactions_count).')',
                'referrals' => 'Referrals ('.number_format($user->referrals_count).')',
                'numbers' => 'Call IDs ('.number_format($user->allocated_numbers_count).')',
                'withdrawals' => 'Withdrawals ('.number_format($user->withdrawals_count).')',
                'claims' => 'Claims ('.number_format($user->call_claims_count).')',
                'credit' => 'Loans & Pay Later ('.number_format($user->loans_count + $user->minute_credits_count).')',
            ];
            if ($user->is_agent) {
                $tabLabels['tokens'] = 'Tokens ('.number_format($user->issued_coupons_count).')';
            }
        @endphp
        <div class="flex items-center gap-1.5 flex-wrap">
            @foreach ($tabLabels as $key => $label)
                <button type="button" wire:click="setTab('{{ $key }}')"
                        class="px-4 h-[36px] rounded-full font-sans text-[12px] transition {{ $tab === $key ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">{{ $label }}</button>
            @endforeach
        </div>

        @if ($tab === 'overview')
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">
                <div class="lg:col-span-2 flex flex-col gap-4 lg:gap-6">
                    <x-backoffice.card>
                        <div class="flex items-center justify-between mb-4">
                            <p class="font-heading font-medium text-text text-[16px]">Wallets</p>
                            @if ($canWrite)
                                <button type="button" @click="$dispatch('open-adjust')" class="inline-flex items-center gap-1.5 h-[34px] px-3.5 rounded-full bg-primary text-white font-sans text-[12px]"><i class="ri-scales-3-line"></i> Adjust</button>
                            @endif
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            @foreach ($walletLabels as $key => $label)
                                <div class="rounded-[14px] bg-background px-3.5 py-3">
                                    <p class="font-sans text-muted text-[12px]">{{ $label }}</p>
                                    <x-figure :value="$user->walletBalance($key)" money class="block font-heading font-semibold text-text text-[16px] mt-0.5" />
                                </div>
                            @endforeach
                            <div class="rounded-[14px] bg-primary/5 px-3.5 py-3">
                                <p class="font-sans text-primary/70 text-[12px]">Total Balance</p>
                                <x-figure :value="(float) $user->lifetime_earnings" money class="block font-heading font-semibold text-primary text-[16px] mt-0.5" />
                            </div>
                        </div>
                    </x-backoffice.card>

                    <x-backoffice.card title="Details">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6">
                            @php
                                $rows = [
                                    ['Phone', $user->phone ?: '—'],
                                    ['Telegram Mini App', $user->telegramIdentity ? ($user->telegramIdentity->handle() ?? 'Linked') : 'Not linked'],
                                    ['Country', $user->country ?: '—'],
                                    ['Gender', $user->gender?->getName() ?? '—'],
                                    ['Package', $user->package?->name ?? 'No package'],
                                    ['Referred by', $user->referrer ? '@'.$user->referrer->username : '—'],
                                    ['Bank', $user->bankAccount ? ($user->bankAccount->bank?->name.' · '.$user->bankAccount->account_number) : 'Not set'],
                                    ['Debit card', $user->debitCard ? ucfirst($user->debitCard->status) : 'None'],
                                    ['Joined', $user->created_at?->format('j M Y, g:i A')],
                                    ['Last login', $user->last_login_at?->format('j M Y, g:i A') ?? 'Never'],
                                ];
                            @endphp
                            @foreach ($rows as [$label, $value])
                                <div class="flex items-center justify-between gap-3 py-2.5 border-b border-muted/10">
                                    <span class="font-sans text-muted text-[13px]">{{ $label }}</span>
                                    <span class="font-sans text-text text-[13px] text-right truncate">{{ $value }}</span>
                                </div>
                            @endforeach
                        </div>
                    </x-backoffice.card>

                    <x-backoffice.card title="Statistics">
                        @php
                            $stats = [
                                ['label' => 'Total Withdrawn', 'value' => $withdrawnTotal, 'money' => true, 'icon' => 'ri-bank-card-line', 'href' => route('backoffice.withdrawals.index', ['search' => $user->username]), 'sub' => number_format($user->withdrawals_count).' requests'],
                                ['label' => 'Transactions', 'value' => $user->transactions_count, 'icon' => 'ri-exchange-dollar-line', 'tab' => 'transactions'],
                                ['label' => 'Calls Claimed', 'value' => $user->call_claims_count, 'icon' => 'ri-phone-line'],
                                ['label' => 'Call IDs', 'value' => $user->allocated_numbers_count, 'icon' => 'ri-hashtag', 'tab' => 'numbers'],
                                ['label' => 'Referrals', 'value' => $user->referrals_count, 'icon' => 'ri-share-forward-line', 'tab' => 'referrals'],
                                ['label' => 'Loans', 'value' => $user->loans_count, 'icon' => 'ri-bank-line', 'href' => route('backoffice.loans.index', ['search' => $user->username])],
                                ['label' => 'Pay Later', 'value' => $user->minute_credits_count, 'icon' => 'ri-hand-coin-line', 'href' => route('backoffice.pay-later.index', ['search' => $user->username])],
                                ['label' => 'Reward Targets Hit', 'value' => $user->reward_targets_hit_count, 'icon' => 'ri-trophy-line'],
                                ['label' => 'Ad Shares', 'value' => $user->ad_shares_count, 'icon' => 'ri-advertisement-line', 'href' => route('backoffice.ads.index', ['tab' => 'shares', 'search' => $user->username])],
                            ];
                        @endphp
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            @foreach ($stats as $s)
                                @php $tag = isset($s['href']) ? 'a' : (isset($s['tab']) ? 'button' : 'div'); $clickable = isset($s['href']) || isset($s['tab']); @endphp
                                <{{ $tag }}
                                    @if (isset($s['href'])) href="{{ $s['href'] }}" wire:navigate @endif
                                    @if (isset($s['tab'])) type="button" wire:click="setTab('{{ $s['tab'] }}')" @endif
                                    class="text-left rounded-[14px] bg-background px-3.5 py-3 {{ $clickable ? 'hover:bg-background/60 transition-colors cursor-pointer' : '' }}">
                                    <span class="flex items-center justify-between">
                                        <span class="size-8 rounded-[9px] bg-primary/10 text-primary flex items-center justify-center"><i class="{{ $s['icon'] }} text-[15px]"></i></span>
                                        @if (isset($s['href']))<i class="ri-arrow-right-up-line text-muted/40 text-[13px]"></i>@elseif (isset($s['tab']))<i class="ri-arrow-right-s-line text-muted/40 text-[14px]"></i>@endif
                                    </span>
                                    <span class="block mt-2">
                                        @if (! empty($s['money']))
                                            <x-figure :value="$s['value']" money class="block font-heading font-semibold text-text text-[16px]" />
                                        @else
                                            <span class="block font-heading font-semibold text-text text-[16px]">{{ number_format($s['value']) }}</span>
                                        @endif
                                        <span class="block font-sans text-muted text-[12px] mt-0.5">{{ $s['label'] }}</span>
                                        @if (isset($s['sub']))<span class="block font-sans text-muted/70 text-[11px]">{{ $s['sub'] }}</span>@endif
                                    </span>
                                </{{ $tag }}>
                            @endforeach
                        </div>
                    </x-backoffice.card>

                    @if ($user->is_agent)
                        <x-backoffice.card title="Vendor">
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                <div class="rounded-[14px] bg-primary/5 px-3.5 py-3">
                                    <p class="font-sans text-primary/70 text-[12px]">Vendor Float</p>
                                    <x-figure :value="$user->walletBalance('agent')" money class="block font-heading font-semibold text-primary text-[16px] mt-0.5" />
                                </div>
                                <a href="{{ route('backoffice.batches.index', ['search' => $user->username]) }}" wire:navigate class="rounded-[14px] bg-background px-3.5 py-3 hover:bg-background/60 transition-colors">
                                    <span class="flex items-center justify-between"><p class="font-sans text-muted text-[12px]">Batches</p><i class="ri-arrow-right-up-line text-muted/40 text-[13px]"></i></span>
                                    <p class="font-heading font-semibold text-text text-[16px] mt-0.5">{{ number_format($user->coupon_batches_count) }}</p>
                                </a>
                                <div class="rounded-[14px] bg-background px-3.5 py-3">
                                    <p class="font-sans text-muted text-[12px]">Tokens Sold</p>
                                    <p class="font-heading font-semibold text-text text-[16px] mt-0.5">{{ number_format($user->tokens_sold_count) }} <span class="font-sans text-muted text-[12px]">/ {{ number_format($user->issued_coupons_count) }}</span></p>
                                </div>
                            </div>
                        </x-backoffice.card>
                    @endif
                </div>

                <div class="flex flex-col gap-4 lg:gap-6">
                    @if ($canWrite)
                        <x-backoffice.card title="Account Actions">
                            <div class="flex flex-col gap-4">
                                <div>
                                    <p class="font-sans text-muted text-[12px] mb-2">Status</p>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($statuses as $s)
                                            @php $isSuspend = $s->value === 'suspended'; @endphp
                                            <button type="button"
                                                    x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'setStatus', params: ['{{ $s->value }}'], title: '{{ $isSuspend ? 'Suspend user' : 'Activate user' }}', message: '{{ $isSuspend ? 'The user will be locked out and cannot log in until reactivated.' : 'The user will regain full access to their account.' }}', confirmText: '{{ $isSuspend ? 'Suspend' : 'Activate' }}', tone: '{{ $isSuspend ? 'warning' : 'primary' }}' })"
                                                    class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $user->status === $s ? 'bg-primary text-white' : 'bg-background text-muted hover:text-text' }}">{{ $s->getName() }}</button>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="flex flex-col gap-2 pt-1">
                                    @foreach ([
                                        ['withdrawals_disabled', 'Block withdrawals', $user->withdrawals_disabled],
                                        ['is_agent', 'Agent (vendor)', $user->is_agent],
                                        ['is_special', 'Special vendor (can transfer)', $user->is_special],
                                        ['show_on_agents_page', 'Show on agents page', $user->show_on_agents_page],
                                    ] as [$flag, $label, $on])
                                        @php $meta = $flagPrompts[$flag][$on ? 'off' : 'on']; @endphp
                                        <button type="button"
                                                x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'toggleFlag', params: ['{{ $flag }}'], title: '{{ $meta['title'] }}', message: '{{ $meta['message'] }}', confirmText: '{{ $meta['confirm'] }}', tone: '{{ $meta['tone'] }}' })"
                                                class="flex items-center justify-between gap-3 rounded-[12px] bg-background px-3.5 py-2.5 hover:bg-background/70 transition">
                                            <span class="font-sans text-text text-[13px]">{{ $label }}</span>
                                            <span class="relative w-10 h-6 rounded-full transition {{ $on ? 'bg-primary' : 'bg-muted/30' }}">
                                                <span class="absolute top-0.5 size-5 rounded-full bg-white transition-all {{ $on ? 'left-[18px]' : 'left-0.5' }}"></span>
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </x-backoffice.card>
                    @else
                        <x-backoffice.card>
                            <p class="font-sans text-muted text-[13px] flex items-center gap-2"><i class="ri-eye-line"></i> Read-only access.</p>
                        </x-backoffice.card>
                    @endif

                    @if ($isSuper)
                        {{-- Support fallback for the "Pay directly with card" flow: the automatic path
                             issues the code as soon as a charge verifies, this covers everything it
                             cannot (payment under review, transfer that missed the checkout, lost code). --}}
                        <x-backoffice.card title="Revenue code">
                            <div class="flex flex-col gap-4">
                                @if ($issuedRevenueCode)
                                    <div class="rounded-[14px] bg-success/10 border border-success/25 px-3.5 py-3.5 flex flex-col gap-2">
                                        <p class="font-sans text-text text-[13px]">Code issued — the storage is already active on their account. It shows once here; the user can also read it on their Revenue Authentication page.</p>
                                        <p class="flex items-center gap-2.5">
                                            <span class="font-mono text-[13px] text-text break-all">{{ $issuedRevenueCode }}</span>
                                            <button type="button" x-data="{ copied: false }"
                                                    @click="nextelCopy('{{ $issuedRevenueCode }}').then(ok => { if (!ok) return; copied = true; setTimeout(() => { copied = false }, 1500) })"
                                                    class="shrink-0 inline-flex items-center gap-1 rounded-full bg-success/15 px-2.5 py-1 font-sans text-[12px] text-success hover:bg-success/25 transition">
                                                <i class="ri-file-copy-line"></i><span x-text="copied ? 'Copied' : 'Copy'"></span>
                                            </button>
                                        </p>
                                        <button type="button" wire:click="$set('issuedRevenueCode', null)" class="self-start font-sans text-[12px] text-muted hover:text-text">Hide</button>
                                    </div>
                                @endif

                                @if ($revenuePlans->isEmpty())
                                    <p class="font-sans text-muted text-[13px] leading-relaxed">
                                        {{ $revenueTier === null
                                            ? 'This user has no package, so no tier plan applies — activate a package first.'
                                            : 'No active revenue code plan is published for Tier '.$revenueTier.'.' }}
                                    </p>
                                @else
                                    <div>
                                        <p class="font-sans text-muted text-[12px] mb-2">Plan (Tier {{ $revenueTier }})</p>
                                        <select wire:model="revenuePlanId" class="{{ $selectCls }} !rounded-[12px] w-full">
                                            <option value="">Choose a plan…</option>
                                            @foreach ($revenuePlans as $plan)
                                                <option value="{{ $plan->id }}">{{ $plan->name }} · +{{ $plan->extra_contacts }} contacts @if ($plan->extra_sms > 0)· +{{ $plan->extra_sms }} SMS/day @endif</option>
                                            @endforeach
                                        </select>
                                        @error('revenuePlanId')<p class="font-sans text-danger text-[12px] mt-1.5">{{ $message }}</p>@enderror
                                    </div>

                                    <div>
                                        <p class="font-sans text-muted text-[12px] mb-2">Payment proof / note (optional)</p>
                                        <input type="text" wire:model="revenueReason" maxlength="200" placeholder="e.g. Paystack ref 1234567, paid 12 Oct" class="{{ $input }} w-full !rounded-[12px]">
                                        @error('revenueReason')<p class="font-sans text-danger text-[12px] mt-1.5">{{ $message }}</p>@enderror
                                    </div>

                                    <button type="button"
                                            x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'issueRevenueCode', params: [], title: 'Issue revenue code', message: 'This binds a fresh code to this user, applies the plan storage bonus now, and authorises one N-Cloud withdrawal. Only do this once the payment is confirmed.', confirmText: 'Issue code', tone: 'primary' })"
                                            class="h-[42px] rounded-[12px] bg-primary text-white font-sans text-[13px] font-medium inline-flex items-center justify-center gap-2 hover:opacity-95 transition">
                                        <i class="ri-gift-2-line text-[15px]"></i> Issue revenue code
                                    </button>
                                @endif
                            </div>
                        </x-backoffice.card>
                    @endif
                </div>
            </div>
        @endif

        @if ($tab === 'transactions')
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[360px]">
                        <i class="ri-search-line text-muted text-[17px]"></i>
                        <input type="text" wire:model.live.debounce.300ms="txSearch" placeholder="Search reference or description…"
                               class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
                        @if ($txSearch !== '')
                            <button type="button" wire:click="$set('txSearch', '')" class="text-muted hover:text-text"><i class="ri-close-line text-[16px]"></i></button>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <select wire:model.live="txType" class="{{ $selectCls }}">
                            <option value="all">All types</option>
                            @foreach ($txTypes as $t)<option value="{{ $t->value }}">{{ $t->getName() }}</option>@endforeach
                        </select>
                        <select wire:model.live="txStatus" class="{{ $selectCls }}">
                            <option value="all">All statuses</option>
                            <option value="completed">Completed</option>
                            <option value="pending">Pending</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                </div>

                @if ($transactions->isEmpty())
                    <x-backoffice.card><p class="font-sans text-muted text-[13px] py-6 text-center">No transactions{{ ($txType !== 'all' || $txStatus !== 'all' || $txSearch !== '') ? ' match these filters' : '' }}.</p></x-backoffice.card>
                @else
                    <x-backoffice.card class="!p-0 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead><tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                    <th class="px-5 py-3 font-medium">Transaction</th>
                                    <th class="px-5 py-3 font-medium">Wallet</th>
                                    <th class="px-5 py-3 font-medium">Status</th>
                                    <th class="px-5 py-3 font-medium text-right">Amount</th>
                                    <th class="px-5 py-3 font-medium">Date</th>
                                </tr></thead>
                                <tbody>
                                    @foreach ($transactions as $tx)
                                        <tr wire:key="utx-{{ $tx->id }}" wire:click="showTransaction('{{ $tx->id }}')" class="border-b border-muted/5 last:border-0 hover:bg-background/60 cursor-pointer transition-colors">
                                            <td class="px-5 py-3">
                                                <div class="flex items-center gap-3">
                                                    @if ($tx->network_logo)
                                                        <img src="{{ $tx->network_logo }}" alt="" class="size-9 rounded-full object-cover shrink-0">
                                                    @else
                                                        <span class="size-9 rounded-full flex items-center justify-center shrink-0 {{ $tx->is_credit ? 'bg-success/15 text-success' : 'bg-danger/15 text-danger' }}"><i class="{{ $tx->type->icon() }} text-[17px]"></i></span>
                                                    @endif
                                                    <div class="min-w-0">
                                                        <p class="font-sans text-text text-[13px] truncate">{{ $tx->type->getName() }}</p>
                                                        <p class="font-sans text-muted text-[12px] truncate">{{ $tx->description ?: $tx->reference }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-5 py-3 font-sans text-muted text-[12px]">{{ $tx->wallet ? (\App\Models\User::WALLET_LABELS[$tx->wallet] ?? $tx->wallet) : '—' }}</td>
                                            <td class="px-5 py-3"><x-backoffice.badge :tone="$tx->status === 'completed' ? 'success' : ($tx->status === 'pending' ? 'warning' : 'danger')">{{ ucfirst($tx->status) }}</x-backoffice.badge></td>
                                            <td class="px-5 py-3 text-right font-heading text-[13px] {{ $tx->is_credit ? 'text-success' : 'text-danger' }} whitespace-nowrap">{{ $tx->signed_amount }}</td>
                                            <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $tx->created_at?->format('j M Y, g:i A') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-backoffice.card>
                    @if ($transactions->hasPages())<div>{{ $transactions->links('vendor.pagination.nextel') }}</div>@endif
                @endif
            </div>

            <x-app.transaction-details :tx="$selectedTx">
                @if ($canWrite && $selectedTx && $selectedTx->type === \App\Enums\TransactionType::ADJUSTMENT && $selectedTx->status === 'completed')
                    <x-slot:actions>
                        <button type="button"
                                x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'reverseAdjustment', params: ['{{ $selectedTx->id }}'], title: 'Reverse adjustment', message: 'This undoes the wallet and lifetime-earnings effect of this adjustment and marks it as failed. The balance may go negative if it was already spent. Continue?', confirmText: 'Reverse', tone: 'danger' })"
                                class="h-[50px] rounded-full bg-danger/10 text-danger font-sans font-medium text-[15px] hover:bg-danger/15 transition inline-flex items-center justify-center gap-2">
                            <i class="ri-arrow-go-back-line text-[17px]"></i> Reverse adjustment
                        </button>
                    </x-slot:actions>
                @endif
            </x-app.transaction-details>
        @endif

        @if ($tab === 'referrals')
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[360px]">
                        <i class="ri-search-line text-muted text-[17px]"></i>
                        <input type="text" wire:model.live.debounce.300ms="refSearch" placeholder="Search name, username or email…"
                               class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
                        @if ($refSearch !== '')
                            <button type="button" wire:click="$set('refSearch', '')" class="text-muted hover:text-text"><i class="ri-close-line text-[16px]"></i></button>
                        @endif
                    </div>
                    <div class="flex items-center gap-1.5 flex-wrap">
                        @foreach (['direct' => ['Direct', $directCount], 'indirect' => ['Indirect', $indirectCount]] as $lvl => [$lvlLabel, $lvlCount])
                            <button type="button" wire:click="setRefLevel('{{ $lvl }}')"
                                    class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $refLevel === $lvl ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">
                                {{ $lvlLabel }} <span class="opacity-70">({{ number_format($lvlCount) }})</span>
                            </button>
                        @endforeach
                        <span class="w-px h-5 bg-muted/15 mx-1"></span>
                        @foreach (['all' => 'All', 'activated' => 'Active', 'pending' => 'Pending'] as $key => $label)
                            <button type="button" wire:click="setRefStatus('{{ $key }}')"
                                    class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $refStatus === $key ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

                @if ($referees->isEmpty())
                    <x-backoffice.card><p class="font-sans text-muted text-[13px] py-6 text-center">No {{ $refLevel }} referrals{{ ($refStatus !== 'all' || $refSearch !== '') ? ' match these filters' : '' }}.</p></x-backoffice.card>
                @else
                    <x-backoffice.card class="!p-0 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead><tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                    <th class="px-5 py-3 font-medium">User</th>
                                    <th class="px-5 py-3 font-medium">Email</th>
                                    @if ($refLevel === 'indirect')<th class="px-5 py-3 font-medium">Referred by</th>@endif
                                    <th class="px-5 py-3 font-medium">Package</th>
                                    <th class="px-5 py-3 font-medium">Status</th>
                                    <th class="px-5 py-3 font-medium">Joined</th>
                                </tr></thead>
                                <tbody>
                                    @foreach ($referees as $r)
                                        <tr wire:key="ref-{{ $r->id }}" wire:click="showReferral('{{ $r->id }}')" class="border-b border-muted/5 last:border-0 hover:bg-background/60 cursor-pointer transition-colors">
                                            <td class="px-5 py-3">
                                                <div class="flex items-center gap-3">
                                                    <span class="size-9 rounded-full bg-primary/10 overflow-hidden flex items-center justify-center font-heading text-primary text-[12px] shrink-0">
                                                        @if ($r->avatar)<img src="{{ $r->avatar }}" alt="" class="size-full object-cover">@else{{ strtoupper(substr($r->first_name ?: $r->username, 0, 2)) }}@endif
                                                    </span>
                                                    <div class="min-w-0">
                                                        <p class="font-sans text-text text-[13px] truncate">{{ $r->full_name }}</p>
                                                        <p class="font-sans text-muted text-[12px] truncate">{{ '@'.$r->username }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-5 py-3 font-sans text-muted text-[13px] truncate max-w-[220px]">{{ $r->email }}</td>
                                            @if ($refLevel === 'indirect')<td class="px-5 py-3 font-sans text-muted text-[13px]">{{ $r->referrer ? '@'.$r->referrer->username : '—' }}</td>@endif
                                            <td class="px-5 py-3 font-sans text-text text-[13px]">{{ $r->package?->name ?? '—' }}</td>
                                            <td class="px-5 py-3"><x-backoffice.badge :tone="$r->package_id ? 'success' : 'warning'">{{ $r->package_id ? 'Active' : 'Pending' }}</x-backoffice.badge></td>
                                            <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $r->created_at?->format('j M Y, g:i A') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-backoffice.card>
                    @if ($referees->hasPages())<div>{{ $referees->links('vendor.pagination.nextel') }}</div>@endif
                @endif
            </div>

            @if ($selectedReferral)
                @php
                    $r = $selectedReferral;
                    $profileUrl = route('backoffice.users.show', $r);
                @endphp
                <div class="fixed inset-0 z-[80] flex items-end sm:items-center justify-center sm:px-4" data-lock-scroll
                     x-data="{ shown: false }" x-init="$nextTick(() => shown = true)">
                    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" x-show="shown" x-transition.opacity wire:click="closeReferral"></div>

                    <div class="relative w-full sm:max-w-[440px] bg-surface rounded-t-[26px] sm:rounded-[26px] px-6 pt-6 pb-7 flex flex-col gap-5 max-h-[88vh] overflow-y-auto"
                         x-show="shown"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-6 sm:scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">

                        <div class="flex items-center justify-between">
                            <p class="font-heading font-medium text-text text-[16px]">Referral</p>
                            <button type="button" wire:click="closeReferral" class="size-9 rounded-full bg-background flex items-center justify-center text-text" aria-label="Close"><i class="ri-close-line text-[20px]"></i></button>
                        </div>

                        <div class="flex items-center gap-3.5">
                            <a href="{{ $profileUrl }}" wire:navigate class="size-14 rounded-full bg-primary/10 overflow-hidden flex items-center justify-center shrink-0">
                                @if ($r->avatar)<img src="{{ $r->avatar }}" alt="" class="size-full object-cover">@else<span class="font-heading font-semibold text-primary text-[18px]">{{ strtoupper(substr($r->first_name ?: $r->username, 0, 2)) }}</span>@endif
                            </a>
                            <div class="min-w-0">
                                <a href="{{ $profileUrl }}" wire:navigate class="font-heading font-medium text-text text-[17px] leading-tight hover:text-primary transition-colors inline-flex items-center gap-1">
                                    {{ $r->full_name }} <i class="ri-arrow-right-up-line text-muted text-[14px]"></i>
                                </a>
                                <p class="font-sans text-muted text-[13px]">{{ '@'.$r->username }}</p>
                                <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                                    <x-backoffice.badge :tone="$r->package_id ? 'success' : 'warning'">{{ $r->package_id ? 'Active' : 'Pending' }}</x-backoffice.badge>
                                    @if ($r->is_agent)<x-backoffice.badge tone="info"><i class="ri-store-2-line"></i> Agent</x-backoffice.badge>@endif
                                </div>
                            </div>
                        </div>

                        <div class="bg-background rounded-[16px] divide-y divide-muted/10">
                            @php
                                $rows = array_values(array_filter([
                                    ['Package', $r->package?->name ?? 'No package', null],
                                    $r->referrer_id !== $user->id && $r->referrer ? ['Referred by', '@'.$r->referrer->username, route('backoffice.users.show', $r->referrer)] : null,
                                    ['Email', $r->email, 'mailto:'.$r->email],
                                    ['Phone', $r->phone ?: '—', $r->phone ? 'tel:'.$r->phone : null],
                                    ['Joined', $r->created_at?->format('j M Y, g:i A'), null],
                                    ['Last login', $r->last_login_at?->format('j M Y, g:i A') ?? 'Never', null],
                                ]));
                            @endphp
                            @foreach ($rows as [$label, $value, $href])
                                <div class="flex items-center justify-between gap-3 px-4 py-3">
                                    <span class="font-sans text-muted text-[13px]">{{ $label }}</span>
                                    @if ($href)
                                        <a href="{{ $href }}" class="font-sans text-primary text-[13px] text-right truncate hover:underline">{{ $value }}</a>
                                    @else
                                        <span class="font-sans text-text text-[13px] text-right truncate">{{ $value }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <a href="{{ $profileUrl }}" wire:navigate class="h-[48px] rounded-[14px] bg-primary text-white font-sans text-[14px] flex items-center justify-center gap-1.5 hover:opacity-95 transition">
                            <i class="ri-user-3-line"></i> View full profile
                        </a>
                    </div>
                </div>
            @endif
        @endif

        @if ($tab === 'numbers')
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[300px]">
                        <i class="ri-search-line text-muted text-[17px]"></i>
                        <input type="text" wire:model.live.debounce.300ms="numSearch" placeholder="Search digits…"
                               class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
                        @if ($numSearch !== '')<button type="button" wire:click="$set('numSearch', '')" class="text-muted hover:text-text"><i class="ri-close-line text-[16px]"></i></button>@endif
                    </div>
                    <div class="flex items-center gap-2">
                        <select wire:model.live="numKind" class="{{ $selectCls }}">
                            <option value="all">All types</option>
                            <option value="permanent">Permanent</option>
                            <option value="temporary">Temporary</option>
                            <option value="expired">Expired</option>
                        </select>
                        <select wire:model.live="numNetwork" class="{{ $selectCls }}">
                            <option value="all">All networks</option>
                            @foreach ($numNetworks as $net)<option value="{{ $net }}">{{ $net }}</option>@endforeach
                        </select>
                    </div>
                </div>

                @if ($numbers->isEmpty())
                    <x-backoffice.card><p class="font-sans text-muted text-[13px] py-6 text-center">No call IDs{{ ($numKind !== 'all' || $numNetwork !== 'all' || $numSearch !== '') ? ' match these filters' : '' }}.</p></x-backoffice.card>
                @else
                    <x-backoffice.card class="!p-0 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead><tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                    <th class="px-5 py-3 font-medium">Call ID</th>
                                    <th class="px-5 py-3 font-medium">Source</th>
                                    <th class="px-5 py-3 font-medium">Status</th>
                                    <th class="px-5 py-3 font-medium">Expires</th>
                                    <th class="px-5 py-3 font-medium">Assigned</th>
                                    @if ($canWrite)<th class="px-5 py-3 font-medium text-right">Actions</th>@endif
                                </tr></thead>
                                <tbody>
                                    @foreach ($numbers as $n)
                                        @php
                                            [$stLabel, $stTone] = $n->expires_at === null
                                                ? ['Permanent', 'muted']
                                                : ($n->is_expired ? ['Expired', 'danger'] : ['Active', 'success']);
                                        @endphp
                                        <tr wire:key="num-{{ $n->id }}" class="border-b border-muted/5 last:border-0">
                                            <td class="px-5 py-3">
                                                <div class="flex items-center gap-3 min-w-0">
                                                    <span class="size-9 rounded-full bg-background overflow-hidden flex items-center justify-center shrink-0 ring-1 ring-black/[0.04]">
                                                        <img src="{{ $n->logo }}" alt="{{ $n->network }}" class="size-full object-cover">
                                                    </span>
                                                    <div class="min-w-0">
                                                        <p class="font-mono text-text text-[13px] leading-tight whitespace-nowrap">{{ $n->masked }}</p>
                                                        <p class="font-sans text-muted text-[12px] leading-tight">{{ $n->network }} · {{ $n->callType()->getName() }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-5 py-3"><x-backoffice.badge :tone="$n->source === 'package' ? 'info' : 'warning'">{{ ucfirst($n->source) }}</x-backoffice.badge></td>
                                            <td class="px-5 py-3"><x-backoffice.badge :tone="$stTone">{{ $stLabel }}</x-backoffice.badge></td>
                                            <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $n->expires_at ? $n->expires_at->format('j M Y, g:i A') : '—' }}</td>
                                            <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $n->created_at?->format('j M Y, g:i A') }}</td>
                                            @if ($canWrite)
                                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                                    <button type="button" title="Revoke" aria-label="Revoke Call ID"
                                                            x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'revokeNumber', params: ['{{ $n->id }}'], title: 'Revoke Call ID', message: 'Permanently remove this Call ID from the user? This cannot be undone.', confirmText: 'Revoke', tone: 'danger' })"
                                                            class="size-8 rounded-full hover:bg-danger/10 text-muted hover:text-danger inline-flex items-center justify-center transition-colors"><i class="ri-delete-bin-line text-[16px]"></i></button>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-backoffice.card>
                    @if ($numbers->hasPages())<div>{{ $numbers->links('vendor.pagination.nextel') }}</div>@endif
                @endif
            </div>
        @endif

        @if ($tab === 'withdrawals')
            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[360px]">
                    <i class="ri-search-line text-muted text-[17px]"></i>
                    <input type="text" wire:model.live.debounce.300ms="wdSearch" placeholder="Search reference, bank or account…"
                           class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
                    @if ($wdSearch !== '')<button type="button" wire:click="$set('wdSearch', '')" class="text-muted hover:text-text"><i class="ri-close-line text-[16px]"></i></button>@endif
                </div>

                @if ($withdrawals->isEmpty())
                    <x-backoffice.card><p class="font-sans text-muted text-[13px] py-6 text-center">No withdrawals{{ $wdSearch !== '' ? ' match this search' : '' }}.</p></x-backoffice.card>
                @else
                    <x-backoffice.card class="!p-0 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left min-w-[880px]">
                                <thead><tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                    <th class="px-5 py-3 font-medium">Reference</th>
                                    <th class="px-5 py-3 font-medium">Bank</th>
                                    <th class="px-5 py-3 font-medium">Amount</th>
                                    <th class="px-5 py-3 font-medium">Wallet</th>
                                    <th class="px-5 py-3 font-medium">Status</th>
                                    <th class="px-5 py-3 font-medium">Processed At</th>
                                </tr></thead>
                                <tbody>
                                    @foreach ($withdrawals as $w)
                                        <tr wire:key="wd-{{ $w->id }}" wire:click="showWithdrawal('{{ $w->id }}')" class="border-b border-muted/5 last:border-0 hover:bg-background/60 cursor-pointer transition-colors">
                                            <td class="px-5 py-3 whitespace-nowrap">
                                                <p class="font-mono text-text text-[12px] leading-tight">{{ $w->reference }}</p>
                                                <p class="font-sans text-muted text-[12px] leading-tight">{{ $w->created_at?->format('j M Y, g:i A') }}</p>
                                            </td>
                                            <td class="px-5 py-3 whitespace-nowrap">
                                                <p class="font-sans text-text text-[13px] leading-tight">{{ $w->bank_name }}</p>
                                                <p class="font-sans text-muted text-[12px] leading-tight">{{ $w->account_number }} · {{ $w->account_name }}</p>
                                            </td>
                                            <td class="px-5 py-3 whitespace-nowrap">
                                                <p class="font-heading text-text text-[13px] leading-tight">{{ money($w->amount) }}</p>
                                                @if ((float) $w->fee > 0)<p class="font-sans text-muted text-[12px] leading-tight">net {{ money($w->amount_paid) }}</p>@endif
                                            </td>
                                            <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $w->wallet_label }}</td>
                                            <td class="px-5 py-3"><x-backoffice.badge :tone="$w->status->color()">{{ $w->status->getName() }}</x-backoffice.badge></td>
                                            <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $w->processed_at?->format('j M Y, g:i A') ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-backoffice.card>
                    @if ($withdrawals->hasPages())<div>{{ $withdrawals->links('vendor.pagination.nextel') }}</div>@endif
                @endif
            </div>
        @endif

        @if ($tab === 'claims')
            @if ($claims->isEmpty())
                <x-backoffice.card><p class="font-sans text-muted text-[13px] py-6 text-center">No claims yet.</p></x-backoffice.card>
            @else
                <x-backoffice.card class="!p-0 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left min-w-[560px]">
                            <thead><tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">Call</th>
                                <th class="px-5 py-3 font-medium">Minutes</th>
                                <th class="px-5 py-3 font-medium">Earned</th>
                                <th class="px-5 py-3 font-medium">Claimed At</th>
                            </tr></thead>
                            <tbody>
                                @foreach ($claims as $c)
                                    <tr wire:key="cl-{{ $c->id }}" wire:click="showClaim('{{ $c->id }}')" class="border-b border-muted/5 last:border-0 hover:bg-background/60 cursor-pointer transition-colors">
                                        <td class="px-5 py-3">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <span class="size-9 rounded-full bg-background overflow-hidden flex items-center justify-center shrink-0 ring-1 ring-black/[0.04]">
                                                    <img src="{{ \App\Models\AllocatedNumber::logoFor($c->network) }}" alt="{{ $c->network }}" class="size-full object-cover">
                                                </span>
                                                <div class="min-w-0">
                                                    <p class="font-sans text-text text-[13px] leading-tight">{{ $c->network ?: 'Nextel' }}</p>
                                                    <p class="font-sans text-muted text-[12px] leading-tight">{{ $c->network === 'Nextel' ? 'On-net' : 'Off-net' }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ rtrim(rtrim(number_format((float) $c->duration_minutes, 1), '0'), '.') }} mins</td>
                                        <td class="px-5 py-3 font-heading text-success text-[13px] whitespace-nowrap">+{{ money($c->amount) }}</td>
                                        <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $c->claimed_at?->format('j M Y, g:i A') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-backoffice.card>
                @if ($claims->hasPages())<div>{{ $claims->links('vendor.pagination.nextel') }}</div>@endif
            @endif
        @endif

        @if ($tab === 'credit')
            @php
                $loanTones = ['processing' => 'warning', 'approved' => 'success', 'paid' => 'success', 'rejected' => 'danger'];
                $plTones = ['active' => 'info', 'repaid' => 'success', 'defaulted' => 'danger'];
            @endphp
            <div class="flex flex-col gap-4">
                <div class="flex items-center gap-1.5">
                    @foreach (['loans' => ['Loans', $loansCount], 'paylater' => ['Pay Later', $payLaterCount]] as $sub => [$subLabel, $subCount])
                        <button type="button" wire:click="setCreditTab('{{ $sub }}')"
                                class="px-4 h-[36px] rounded-full font-sans text-[12px] transition {{ $creditTab === $sub ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">
                            {{ $subLabel }} <span class="opacity-70">({{ number_format($subCount) }})</span>
                        </button>
                    @endforeach
                </div>

                @if ($creditTab === 'loans')
                    @if ($loans->isEmpty())
                        <x-backoffice.card><p class="font-sans text-muted text-[13px] py-6 text-center">No loans.</p></x-backoffice.card>
                    @else
                        <x-backoffice.card class="!p-0 overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left min-w-[520px]">
                                    <thead><tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                        <th class="px-5 py-3 font-medium">Amount</th>
                                        <th class="px-5 py-3 font-medium">Status</th>
                                        <th class="px-5 py-3 font-medium">Created</th>
                                        <th class="px-5 py-3 font-medium">Due At</th>
                                    </tr></thead>
                                    <tbody>
                                        @foreach ($loans as $loan)
                                            <tr wire:key="loan-{{ $loan->id }}" wire:click="showLoan('{{ $loan->id }}')" class="border-b border-muted/5 last:border-0 hover:bg-background/60 cursor-pointer transition-colors">
                                                <td class="px-5 py-3 font-heading text-text text-[13px] whitespace-nowrap">{{ money($loan->amount) }}</td>
                                                <td class="px-5 py-3"><x-backoffice.badge :tone="$loanTones[$loan->status->value] ?? 'muted'">{{ $loan->status->label() }}</x-backoffice.badge></td>
                                                <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $loan->created_at?->format('j M Y, g:i A') ?? '—' }}</td>
                                                <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $loan->due_date?->format('j M Y, g:i A') ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </x-backoffice.card>
                        @if ($loans->hasPages())<div>{{ $loans->links('vendor.pagination.nextel') }}</div>@endif
                    @endif
                @else
                    @if ($payLaters->isEmpty())
                        <x-backoffice.card><p class="font-sans text-muted text-[13px] py-6 text-center">No Pay Later credits.</p></x-backoffice.card>
                    @else
                        <x-backoffice.card class="!p-0 overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left min-w-[720px]">
                                    <thead><tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                        <th class="px-5 py-3 font-medium">Plan</th>
                                        <th class="px-5 py-3 font-medium">Amount</th>
                                        <th class="px-5 py-3 font-medium">Minutes</th>
                                        <th class="px-5 py-3 font-medium">Status</th>
                                        <th class="px-5 py-3 font-medium">Created At</th>
                                        <th class="px-5 py-3 font-medium">Due At</th>
                                    </tr></thead>
                                    <tbody>
                                        @foreach ($payLaters as $pl)
                                            <tr wire:key="pl-{{ $pl->id }}" wire:click="showCredit('{{ $pl->id }}')" class="border-b border-muted/5 last:border-0 hover:bg-background/60 cursor-pointer transition-colors">
                                                <td class="px-5 py-3 font-sans text-text text-[13px] whitespace-nowrap">{{ $pl->plan?->name ?? $pl->plan_name ?? '—' }}</td>
                                                <td class="px-5 py-3 font-heading text-text text-[13px] whitespace-nowrap">{{ money($pl->amount) }}</td>
                                                <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ rtrim(rtrim(number_format((float) $pl->minutes, 1), '0'), '.') }}</td>
                                                <td class="px-5 py-3"><x-backoffice.badge :tone="$pl->is_overdue ? 'warning' : ($plTones[$pl->status->value] ?? 'muted')">{{ $pl->is_overdue ? 'Overdue' : $pl->status->label() }}</x-backoffice.badge></td>
                                                <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $pl->created_at?->format('j M Y, g:i A') ?? '—' }}</td>
                                                <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $pl->due_date?->format('j M Y, g:i A') ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </x-backoffice.card>
                        @if ($payLaters->hasPages())<div>{{ $payLaters->links('vendor.pagination.nextel') }}</div>@endif
                    @endif
                @endif
            </div>
        @endif

        @if ($tab === 'tokens')
            @if (! $user->is_agent)
                <x-backoffice.card><p class="font-sans text-muted text-[13px] py-6 text-center">This user is not an agent.</p></x-backoffice.card>
            @else
                <div class="flex flex-col gap-4">
                    <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[360px]">
                        <i class="ri-search-line text-muted text-[17px]"></i>
                        <input type="text" wire:model.live.debounce.300ms="tokenSearch" placeholder="Search by batch reference…"
                               class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
                        @if ($tokenSearch !== '')<button type="button" wire:click="$set('tokenSearch', '')" class="text-muted hover:text-text"><i class="ri-close-line text-[16px]"></i></button>@endif
                    </div>

                    @if (! $batches || $batches->isEmpty())
                        <x-backoffice.card><p class="font-sans text-muted text-[13px] py-6 text-center">No token batches{{ $tokenSearch !== '' ? ' match this search' : ' generated' }}.</p></x-backoffice.card>
                    @else
                        <x-backoffice.card class="!p-0 overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left min-w-[620px]">
                                    <thead><tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                        <th class="px-5 py-3 font-medium">Batch</th>
                                        <th class="px-5 py-3 font-medium">Type</th>
                                        <th class="px-5 py-3 font-medium">Tokens</th>
                                        <th class="px-5 py-3 font-medium">Sold</th>
                                        <th class="px-5 py-3 font-medium">Created</th>
                                    </tr></thead>
                                    <tbody>
                                        @foreach ($batches as $batch)
                                            <tr wire:key="bt-{{ $batch->id }}" x-on:click="Livewire.navigate('{{ route('backoffice.batches.show', $batch) }}')" class="border-b border-muted/5 last:border-0 hover:bg-background/60 cursor-pointer transition-colors">
                                                <td class="px-5 py-3 font-mono text-text text-[12px] whitespace-nowrap">{{ $batch->reference }}</td>
                                                <td class="px-5 py-3"><x-backoffice.badge :tone="$batch->type->color()">{{ $batch->type->getName() }}</x-backoffice.badge></td>
                                                <td class="px-5 py-3 font-sans text-text text-[13px]">{{ number_format($batch->coupons_count) }}</td>
                                                <td class="px-5 py-3 font-sans text-muted text-[12px]">{{ number_format($batch->sold_count) }} / {{ number_format($batch->coupons_count) }}</td>
                                                <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $batch->created_at?->format('j M Y, g:i A') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </x-backoffice.card>
                        @if ($batches->hasPages())<div>{{ $batches->links('vendor.pagination.nextel') }}</div>@endif
                    @endif
                </div>
            @endif
        @endif

        @php
            $detailShell = 'relative w-full sm:max-w-[400px] bg-surface rounded-t-[26px] sm:rounded-[26px] px-6 pt-6 pb-7 flex flex-col gap-5 max-h-[88vh] overflow-y-auto';
            $loanTonesAll = ['processing' => 'warning', 'approved' => 'success', 'paid' => 'success', 'rejected' => 'danger'];
            $plTonesAll = ['active' => 'info', 'repaid' => 'success', 'defaulted' => 'danger'];
        @endphp

        @if ($selectedWithdrawal)
            @php $sw = $selectedWithdrawal; @endphp
            <div class="fixed inset-0 z-[80] flex items-end sm:items-center justify-center sm:px-4" data-lock-scroll x-data="{ shown: false }" x-init="$nextTick(() => shown = true)">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" x-show="shown" x-transition.opacity wire:click="closeWithdrawal"></div>
                <div class="{{ $detailShell }}" x-show="shown" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-6 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">
                    <div class="flex items-center justify-between">
                        <p class="font-heading font-medium text-text text-[16px]">Withdrawal Details</p>
                        <button type="button" wire:click="closeWithdrawal" class="size-9 rounded-full bg-background flex items-center justify-center text-text" aria-label="Close"><i class="ri-close-line text-[20px]"></i></button>
                    </div>
                    <div class="flex flex-col items-center gap-2 py-1">
                        <p class="font-heading font-semibold text-[28px] leading-none text-text">{{ money($sw->amount) }}</p>
                        <x-backoffice.badge :tone="$sw->status->color()">{{ $sw->status->getName() }}</x-backoffice.badge>
                    </div>
                    <div class="bg-background rounded-[16px] divide-y divide-muted/10">
                        @foreach (array_filter([
                            ['Reference', $sw->reference],
                            ['Bank', $sw->bank_name],
                            ['Account', trim($sw->account_number.' · '.$sw->account_name, ' ·')],
                            ['Wallet', $sw->wallet_label],
                            ['Amount', money($sw->amount)],
                            (float) $sw->fee > 0 ? ['Fee', money($sw->fee)] : null,
                            (float) $sw->fee > 0 ? ['Net paid', money($sw->amount_paid)] : null,
                            ['Created At', $sw->created_at?->format('j M Y, g:i A')],
                            $sw->processed_at ? ['Processed At', $sw->processed_at->format('j M Y, g:i A')] : null,
                            data_get($sw->meta, 'rejection_reason') ? ['Rejection reason', data_get($sw->meta, 'rejection_reason')] : null,
                        ]) as [$label, $value])
                            <div class="flex items-start justify-between gap-3 px-4 py-3">
                                <span class="font-sans text-muted text-[13px] shrink-0">{{ $label }}</span>
                                <span class="font-sans text-text text-[13px] text-right">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" wire:click="closeWithdrawal" class="h-[48px] rounded-[14px] bg-primary text-white font-sans text-[14px] hover:opacity-95 transition">Done</button>
                </div>
            </div>
        @endif

        @if ($selectedClaim)
            @php
                $sc = $selectedClaim;
                $cp = $sc->callRecord?->counterparty;
                $cpMasked = $cp ? '+234 '.substr(ltrim($cp, '0'), 0, 3).'••••'.substr($cp, -3) : null;
                $mins = (float) $sc->duration_minutes;
            @endphp
            <div class="fixed inset-0 z-[80] flex items-end sm:items-center justify-center sm:px-4" data-lock-scroll x-data="{ shown: false }" x-init="$nextTick(() => shown = true)">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" x-show="shown" x-transition.opacity wire:click="closeClaim"></div>
                <div class="{{ $detailShell }}" x-show="shown" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-6 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">
                    <div class="flex items-center justify-between">
                        <p class="font-heading font-medium text-text text-[16px]">Call earning</p>
                        <button type="button" wire:click="closeClaim" class="size-9 rounded-full bg-background flex items-center justify-center text-text" aria-label="Close"><i class="ri-close-line text-[20px]"></i></button>
                    </div>
                    <div class="flex flex-col items-center gap-2.5 py-1">
                        <span class="size-14 rounded-full bg-background overflow-hidden flex items-center justify-center ring-1 ring-black/[0.04]">
                            <img src="{{ \App\Models\AllocatedNumber::logoFor($sc->network) }}" alt="" class="size-full object-cover">
                        </span>
                        <p class="font-heading font-semibold text-[28px] leading-none text-success">+{{ money($sc->amount) }}</p>
                    </div>
                    <div class="bg-background rounded-[16px] divide-y divide-muted/10">
                        @foreach (array_filter([
                            ['Network', $sc->network ?: 'Nextel'],
                            ['Call type', $sc->network === 'Nextel' ? 'On-net' : 'Off-net'],
                            $cpMasked ? ['Number', $cpMasked] : null,
                            ['Minutes', rtrim(rtrim(number_format($mins, 1), '0'), '.').' mins'],
                            $mins > 0 ? ['Rate', money($sc->amount / $mins).'/min'] : null,
                            ['Earned', '+'.money($sc->amount)],
                            ['Claimed At', $sc->claimed_at?->format('j M Y, g:i A')],
                        ]) as [$label, $value])
                            <div class="flex items-start justify-between gap-3 px-4 py-3">
                                <span class="font-sans text-muted text-[13px] shrink-0">{{ $label }}</span>
                                <span class="font-sans text-text text-[13px] text-right">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" wire:click="closeClaim" class="h-[48px] rounded-[14px] bg-primary text-white font-sans text-[14px] hover:opacity-95 transition">Done</button>
                </div>
            </div>
        @endif

        @if ($selectedLoan)
            @php $sl = $selectedLoan; @endphp
            <div class="fixed inset-0 z-[80] flex items-end sm:items-center justify-center sm:px-4" data-lock-scroll x-data="{ shown: false }" x-init="$nextTick(() => shown = true)">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" x-show="shown" x-transition.opacity wire:click="closeLoan"></div>
                <div class="{{ $detailShell }}" x-show="shown" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-6 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">
                    <div class="flex items-center justify-between">
                        <p class="font-heading font-medium text-text text-[16px]">Loan</p>
                        <button type="button" wire:click="closeLoan" class="size-9 rounded-full bg-background flex items-center justify-center text-text" aria-label="Close"><i class="ri-close-line text-[20px]"></i></button>
                    </div>
                    <div class="flex flex-col items-center gap-2 py-1">
                        <p class="font-heading font-semibold text-[28px] leading-none text-text">{{ money($sl->amount) }}</p>
                        <x-backoffice.badge :tone="$loanTonesAll[$sl->status->value] ?? 'muted'">{{ $sl->status->label() }}</x-backoffice.badge>
                    </div>
                    <div class="bg-background rounded-[16px] divide-y divide-muted/10">
                        @foreach (array_filter([
                            ['Amount', money($sl->amount)],
                            ['Total repayment', money($sl->total_repayment)],
                            ['Status', $sl->status->label()],
                            ['Created At', $sl->created_at?->format('j M Y, g:i A')],
                            ['Paid At', $sl->paid_at?->format('j M Y, g:i A') ?? '—'],
                            ['Due At', $sl->due_date?->format('j M Y, g:i A')],
                        ]) as [$label, $value])
                            <div class="flex items-start justify-between gap-3 px-4 py-3">
                                <span class="font-sans text-muted text-[13px] shrink-0">{{ $label }}</span>
                                <span class="font-sans text-text text-[13px] text-right">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" wire:click="closeLoan" class="h-[48px] rounded-[14px] bg-primary text-white font-sans text-[14px] hover:opacity-95 transition">Done</button>
                </div>
            </div>
        @endif

        @if ($selectedCredit)
            @php $spl = $selectedCredit; @endphp
            <div class="fixed inset-0 z-[80] flex items-end sm:items-center justify-center sm:px-4" data-lock-scroll x-data="{ shown: false }" x-init="$nextTick(() => shown = true)">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" x-show="shown" x-transition.opacity wire:click="closeCredit"></div>
                <div class="{{ $detailShell }}" x-show="shown" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-6 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">
                    <div class="flex items-center justify-between">
                        <p class="font-heading font-medium text-text text-[16px]">Pay Later</p>
                        <button type="button" wire:click="closeCredit" class="size-9 rounded-full bg-background flex items-center justify-center text-text" aria-label="Close"><i class="ri-close-line text-[20px]"></i></button>
                    </div>
                    <div class="flex flex-col items-center gap-2 py-1">
                        <p class="font-heading font-semibold text-[28px] leading-none text-text">{{ money($spl->amount) }}</p>
                        <x-backoffice.badge :tone="$spl->is_overdue ? 'warning' : ($plTonesAll[$spl->status->value] ?? 'muted')">{{ $spl->is_overdue ? 'Overdue' : $spl->status->label() }}</x-backoffice.badge>
                    </div>
                    <div class="bg-background rounded-[16px] divide-y divide-muted/10">
                        @foreach (array_filter([
                            ['Plan', $spl->plan?->name ?? $spl->plan_name ?? '—'],
                            ['Amount', money($spl->amount)],
                            ['Minutes', rtrim(rtrim(number_format((float) $spl->minutes, 1), '0'), '.')],
                            ['Status', $spl->status->label()],
                            ['Created At', $spl->created_at?->format('j M Y, g:i A')],
                            ['Due At', $spl->due_date?->format('j M Y, g:i A')],
                            $spl->repaid_at ? ['Repaid At', $spl->repaid_at->format('j M Y, g:i A')] : null,
                        ]) as [$label, $value])
                            <div class="flex items-start justify-between gap-3 px-4 py-3">
                                <span class="font-sans text-muted text-[13px] shrink-0">{{ $label }}</span>
                                <span class="font-sans text-text text-[13px] text-right">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" wire:click="closeCredit" class="h-[48px] rounded-[14px] bg-primary text-white font-sans text-[14px] hover:opacity-95 transition">Done</button>
                </div>
            </div>
        @endif

        @if ($canWrite)
            <div x-data="{ open: false }" x-cloak @open-adjust.window="open = true; $wire.set('adjustReviewing', false)" @adjustment-saved.window="open = false" @keydown.escape.window="open = false" :data-lock-scroll="open ? '' : null">
                <div x-show="open" x-transition.opacity class="fixed inset-0 z-[80] bg-black/40 backdrop-blur-sm" @click="open = false"></div>
                <div x-show="open" x-transition class="fixed inset-x-4 top-1/2 -translate-y-1/2 sm:inset-x-auto sm:left-1/2 sm:-translate-x-1/2 sm:w-[440px] z-[90] bg-surface rounded-[22px] p-6 shadow-[0_20px_60px_rgba(0,0,0,0.25)]">
                    <div class="flex items-center justify-between mb-5">
                        <p class="font-heading font-medium text-text text-[16px]">{{ $adjustReviewing ? 'Review adjustment' : 'Adjust Wallet' }}</p>
                        <button type="button" @click="open = false" class="size-8 rounded-full bg-background flex items-center justify-center text-muted"><i class="ri-close-line"></i></button>
                    </div>

                    @if (! $adjustReviewing)
                        <form wire:submit="reviewAdjustment" class="flex flex-col gap-4">
                            <div class="grid grid-cols-2 gap-3">
                                <div class="flex flex-col gap-1.5">
                                    <label class="font-sans text-muted text-[12px]">Wallet</label>
                                    <select wire:model="adjustWallet" class="{{ $input }}">@foreach ($walletLabels as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <label class="font-sans text-muted text-[12px]">Direction</label>
                                    <select wire:model="adjustDirection" class="{{ $input }}"><option value="credit">Credit (add)</option><option value="debit">Debit (remove)</option></select>
                                </div>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="font-sans text-muted text-[12px]">Amount ($)</label>
                                <input type="number" step="0.01" min="0.01" wire:model="adjustAmount" placeholder="0.00" class="{{ $input }}">
                                @error('adjustAmount') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="font-sans text-muted text-[12px]">Reason</label>
                                <input type="text" wire:model="adjustReason" placeholder="Why this adjustment?" class="{{ $input }}">
                                @error('adjustReason') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                            </div>
                            <button type="submit" wire:loading.attr="disabled" wire:target="reviewAdjustment" class="h-[46px] rounded-[12px] bg-primary text-white font-sans text-[14px] mt-1 disabled:opacity-60">
                                <span wire:loading.remove wire:target="reviewAdjustment">Review adjustment</span>
                                <span wire:loading wire:target="reviewAdjustment" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Reviewing…</span>
                            </button>
                        </form>
                    @else
                        @php
                            $curBal = (float) $user->walletBalance($adjustWallet);
                            $amt = (float) ($adjustAmount ?: 0);
                            $isDebit = $adjustDirection === 'debit';
                            $newBal = $isDebit ? $curBal - $amt : $curBal + $amt;
                            $insufficient = $isDebit && $amt > $curBal;
                        @endphp
                        <div class="flex flex-col gap-4">
                            <div class="rounded-[14px] bg-background px-4 py-3.5 flex flex-col gap-3">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-sans text-muted text-[13px]">Action</span>
                                    <span class="font-heading text-[13px] {{ $isDebit ? 'text-danger' : 'text-success' }}">{{ $isDebit ? 'Debit' : 'Credit' }} {{ money($amt) }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-sans text-muted text-[13px]">Wallet</span>
                                    <span class="font-sans text-text text-[13px]">{{ $walletLabels[$adjustWallet] ?? $adjustWallet }}</span>
                                </div>
                                <div class="border-t border-muted/10 pt-3 flex items-center justify-between gap-3">
                                    <span class="font-sans text-muted text-[13px]">New balance</span>
                                    <span class="font-heading text-[14px] text-text flex items-center gap-1.5">
                                        {{ money($curBal) }}
                                        <i class="ri-arrow-right-line text-muted text-[13px]"></i>
                                        <span class="{{ $insufficient ? 'text-danger' : ($isDebit ? 'text-text' : 'text-success') }}">{{ money($newBal) }}</span>
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-start gap-2">
                                <i class="ri-chat-quote-line text-muted text-[14px] leading-[18px] shrink-0"></i>
                                <span class="font-sans text-[13px] text-text leading-[18px]">{{ $adjustReason }}</span>
                            </div>
                            @if ($insufficient)
                                <p class="font-sans text-[12px] text-danger flex items-center gap-1.5"><i class="ri-error-warning-line"></i> This debit exceeds the current balance and will be rejected.</p>
                            @endif
                            <div class="flex justify-end gap-2 mt-1">
                                <button type="button" wire:click="$set('adjustReviewing', false)" class="h-[42px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Back</button>
                                <button type="button" wire:click="saveAdjustment" wire:loading.attr="disabled" wire:target="saveAdjustment" @if ($insufficient) disabled @endif class="h-[42px] px-5 rounded-full bg-primary text-white font-sans text-[13px] disabled:opacity-60 {{ $insufficient ? 'opacity-50 cursor-not-allowed' : '' }}">
                                    <span wire:loading.remove wire:target="saveAdjustment">Apply adjustment</span>
                                    <span wire:loading wire:target="saveAdjustment" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Applying…</span>
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <x-backoffice.modal :show="$showProfileModal" title="Edit profile" closeAction="$set('showProfileModal', false)">
                <form wire:submit="saveProfile" class="flex flex-col gap-3.5">
                    <div class="flex items-center gap-4">
                        <span class="size-16 rounded-full bg-primary/10 overflow-hidden flex items-center justify-center shrink-0">
                            @if ($photo)
                                <img src="{{ $photo->temporaryUrl() }}" alt="" class="size-full object-cover">
                            @elseif ($user->avatar)
                                <img src="{{ $user->avatar }}" alt="" class="size-full object-cover">
                            @else
                                <span class="font-heading font-semibold text-primary text-[18px]">{{ strtoupper(substr($user->first_name ?: $user->username, 0, 2)) }}</span>
                            @endif
                        </span>
                        <div class="flex flex-col gap-1">
                            <label class="inline-flex items-center gap-2 cursor-pointer font-sans text-[13px] text-primary">
                                <i class="ri-upload-2-line" wire:loading.remove wire:target="photo"></i>
                                <i class="ri-loader-4-line animate-spin" wire:loading wire:target="photo"></i>
                                <span wire:loading.remove wire:target="photo">Change photo</span>
                                <span wire:loading wire:target="photo">Uploading…</span>
                                <input type="file" wire:model="photo" accept="image/*" class="hidden">
                            </label>
                            @error('photo') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5"><label class="font-sans text-muted text-[12px]">First name</label><input type="text" wire:model="first_name" class="{{ $input }}">@error('first_name')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror</div>
                        <div class="flex flex-col gap-1.5"><label class="font-sans text-muted text-[12px]">Last name</label><input type="text" wire:model="last_name" class="{{ $input }}"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5"><label class="font-sans text-muted text-[12px]">Username</label><input type="text" wire:model="username" class="{{ $input }}">@error('username')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror</div>
                        <div class="flex flex-col gap-1.5"><label class="font-sans text-muted text-[12px]">Phone</label><input type="text" wire:model="phone" class="{{ $input }}"></div>
                    </div>
                    <div class="flex flex-col gap-1.5"><label class="font-sans text-muted text-[12px]">Email</label><input type="email" wire:model="email" class="{{ $input }}">@error('email')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror</div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5"><label class="font-sans text-muted text-[12px]">Country (ISO-2)</label><input type="text" wire:model="country" maxlength="2" class="{{ $input }}"></div>
                        <div class="flex flex-col gap-1.5"><label class="font-sans text-muted text-[12px]">Gender</label><select wire:model="gender" class="{{ $input }}"><option value="">—</option>@foreach ($genders as $g)<option value="{{ $g->value }}">{{ $g->getName() }}</option>@endforeach</select></div>
                    </div>
                    <div class="flex flex-col gap-1.5"><label class="font-sans text-muted text-[12px]">Biography</label><textarea wire:model="biography" rows="2" class="rounded-[12px] bg-background border border-muted/15 px-3 py-2 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40"></textarea></div>
                    <div class="flex justify-end gap-2 mt-1">
                        <button type="button" wire:click="$set('showProfileModal', false)" class="h-[42px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="saveProfile" class="h-[42px] px-5 rounded-full bg-primary text-white font-sans text-[13px] disabled:opacity-60">
                            <span wire:loading.remove wire:target="saveProfile">Save</span>
                            <span wire:loading wire:target="saveProfile" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Saving…</span>
                        </button>
                    </div>
                </form>
            </x-backoffice.modal>

            <x-backoffice.modal :show="$showBankModal" title="Bank account" closeAction="$set('showBankModal', false)">
                <div class="flex flex-col gap-3.5">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Bank</label>
                        <select wire:model.live="bankId" class="{{ $input }}"><option value="">Select bank…</option>@foreach ($banks as $bank)<option value="{{ $bank->id }}">{{ $bank->name }}</option>@endforeach</select>
                        @error('bankId')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Account number</label>
                        <input type="text" inputmode="numeric" maxlength="10" wire:model.live.debounce.600ms="bankAccountNumber" class="{{ $input }}">
                        @error('bankAccountNumber')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div wire:loading.flex wire:target="bankId,bankAccountNumber,verifyBank" class="items-center gap-2 font-sans text-[12px] text-muted"><i class="ri-loader-4-line animate-spin"></i> Verifying account…</div>
                    @if ($bankVerifyError)<p class="font-sans text-[12px] text-danger">{{ $bankVerifyError }}</p>@endif
                    @if ($bankVerified && $bankAccountName)
                        <div class="rounded-[12px] bg-success/10 px-3.5 py-3 font-sans text-[13px] text-text"><i class="ri-checkbox-circle-fill text-success"></i> {{ $bankAccountName }}</div>
                    @endif
                    <div class="flex justify-end gap-2 mt-1">
                        <button type="button" wire:click="$set('showBankModal', false)" class="h-[42px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Cancel</button>
                        <button type="button" wire:click="saveBank" wire:loading.attr="disabled" wire:target="saveBank" @if (! $bankVerified) disabled @endif class="h-[42px] px-5 rounded-full bg-primary text-white font-sans text-[13px] disabled:opacity-60 {{ $bankVerified ? '' : 'opacity-50 cursor-not-allowed' }}">
                            <span wire:loading.remove wire:target="saveBank">Save bank</span>
                            <span wire:loading wire:target="saveBank" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Saving…</span>
                        </button>
                    </div>
                </div>
            </x-backoffice.modal>
        @endif
    </x-backoffice.page>
</div>
