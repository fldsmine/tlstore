<div>
    <x-backoffice.page title="Dashboard" subtitle="Platform overview">
        @php
            $money = fn ($v) => money($v);
            $compact = fn ($v) => '$'.abbreviate_number((float) $v);
            // Tooltip exact value — only when the hint figure is abbreviated.
            $tip = fn ($v) => abs((float) $v) >= 100000 ? money($v) : null;

            $alerts = array_values(array_filter([
                $pendingCount > 0 ? ['Withdrawals to review', $pendingCount.' pending · '.$money($pendingAmount), 'ri-time-line', route('backoffice.withdrawals.index'), 'warning'] : null,
                $loansPending > 0 ? ['Loans pending approval', $loansPending.' awaiting', 'ri-bank-line', route('backoffice.loans.index'), 'warning'] : null,
                $payLaterOverdue > 0 ? ['Pay Later overdue', $payLaterOverdue.' past due', 'ri-alarm-warning-line', route('backoffice.pay-later.index'), 'danger'] : null,
            ]));
            $alertTone = [
                'warning' => 'bg-[#d79937]/10 border-[#d79937]/25 text-[#d79937]',
                'danger' => 'bg-danger/10 border-danger/25 text-danger',
            ];

            // Full breakdowns (incl. Cloud) live on Reports.
            $kpis = [
                ['label' => 'Total Users', 'value' => $usersTotal, 'icon' => 'ri-group-line', 'tone' => 'primary', 'hint' => $usersActive.' active', 'href' => route('backoffice.users.index')],
                ['label' => 'Mini App users', 'value' => $telegramUsers, 'icon' => 'ri-telegram-line', 'tone' => 'primary', 'hint' => 'Telegram access', 'href' => route('backoffice.telegram-users.index')],
                ['label' => 'Agents', 'value' => $agents, 'icon' => 'ri-store-2-line', 'tone' => 'primary', 'href' => route('backoffice.users.index', ['type' => 'agents'])],
                ['label' => 'Pending Withdrawals', 'value' => $pendingCount, 'icon' => 'ri-time-line', 'tone' => 'warning', 'hint' => $compact($pendingAmount), 'hintFull' => $tip($pendingAmount), 'href' => route('backoffice.withdrawals.index')],
                ['label' => 'Paid Out Today', 'value' => $paidToday, 'money' => true, 'icon' => 'ri-bank-card-line', 'tone' => 'success', 'href' => route('backoffice.withdrawals.index')],
                ['label' => 'Total Liability', 'value' => $totalLiability, 'money' => true, 'icon' => 'ri-wallet-3-line', 'tone' => 'primary', 'hint' => 'all wallets', 'href' => route('backoffice.reports.index')],
                ['label' => 'Withdrawable Now', 'value' => $withdrawableLiability, 'money' => true, 'icon' => 'ri-bank-card-2-line', 'tone' => 'warning', 'hint' => $withdrawalOpen ? 'open for payout' : 'withdrawals paused', 'href' => route('backoffice.reports.index')],
                ['label' => 'Vendor Float', 'value' => $vendorFloat, 'money' => true, 'icon' => 'ri-store-2-line', 'tone' => 'muted', 'hint' => 'not withdrawable', 'href' => route('backoffice.users.index', ['type' => 'agents'])],
                ['label' => 'Coupons Issued', 'value' => $couponsTotal, 'icon' => 'ri-coupon-line', 'tone' => 'primary', 'hint' => $couponsUsed.' redeemed', 'href' => route('backoffice.coupons.index')],
                ['label' => 'Ad Shares', 'value' => $adShares, 'icon' => 'ri-advertisement-line', 'tone' => 'success', 'hint' => $compact($adSharesAmount).' paid', 'hintFull' => $tip($adSharesAmount), 'href' => route('backoffice.ads.index')],
                ['label' => 'Active Storage Plans', 'value' => $cloudActivePlans, 'icon' => 'ri-cloud-line', 'tone' => 'primary', 'hint' => 'Nextel Cloud', 'href' => route('backoffice.reports.index')],
                ['label' => 'Cloud Calls Today', 'value' => $cloudCallsToday, 'icon' => 'ri-phone-line', 'tone' => 'success', 'href' => route('backoffice.reports.index')],
            ];
        @endphp

        @if ($alerts)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach ($alerts as [$label, $detail, $icon, $href, $tone])
                    <a href="{{ $href }}" wire:navigate
                       class="flex items-center gap-3 rounded-[16px] border px-4 py-3.5 hover:opacity-90 transition {{ $alertTone[$tone] }}">
                        <i class="{{ $icon }} text-[22px] shrink-0"></i>
                        <span class="flex-1 min-w-0">
                            <span class="block font-heading font-medium text-text text-[14px]">{{ $label }}</span>
                            <span class="block font-sans text-muted text-[12px]">{{ $detail }}</span>
                        </span>
                        <i class="ri-arrow-right-s-line text-[20px] shrink-0"></i>
                    </a>
                @endforeach
            </div>
        @endif

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
            @foreach ($kpis as $c)
                <x-backoffice.stat :label="$c['label']" :value="$c['value']" :money="$c['money'] ?? false" :icon="$c['icon']" :tone="$c['tone'] ?? 'primary'" :hint="$c['hint'] ?? null" :href="$c['href'] ?? null" :hint-full="$c['hintFull'] ?? null" />
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
            <x-backoffice.card title="Recent Signups">
                @forelse ($recentUsers as $u)
                    <a href="{{ route('backoffice.users.show', $u) }}" wire:navigate
                       class="flex items-center gap-3 py-2.5 border-b border-muted/10 last:border-0 hover:opacity-80 transition">
                        <span class="size-9 rounded-full bg-primary/10 flex items-center justify-center font-heading text-primary text-[12px] shrink-0">
                            {{ strtoupper(substr($u->first_name ?: $u->username, 0, 2)) }}
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="block font-sans text-text text-[13px] truncate">{{ $u->full_name }}</span>
                            <span class="block font-sans text-muted text-[12px] truncate">{{ '@'.$u->username }}</span>
                        </span>
                        <span class="font-sans text-muted text-[12px] shrink-0">{{ $u->created_at?->format('j M Y, g:i A') }}</span>
                    </a>
                @empty
                    <p class="font-sans text-muted text-[13px] py-4 text-center">No users yet.</p>
                @endforelse
            </x-backoffice.card>

            <x-backoffice.card title="Recent Withdrawals">
                @forelse ($recentWithdrawals as $w)
                    <a href="{{ route('backoffice.withdrawals.index') }}" wire:navigate
                       class="flex items-center gap-3 py-2.5 border-b border-muted/10 last:border-0 hover:opacity-80 transition">
                        <span class="size-9 rounded-full bg-background flex items-center justify-center text-muted shrink-0"><i class="ri-arrow-up-line"></i></span>
                        <span class="flex-1 min-w-0">
                            <span class="block font-sans text-text text-[13px] truncate">{{ $money($w->amount) }} · {{ $w->wallet_label }}</span>
                            <span class="block font-sans text-muted text-[12px] truncate">{{ $w->user?->username ? '@'.$w->user->username : '—' }}</span>
                        </span>
                        <x-backoffice.badge :tone="$w->status->color()">{{ $w->status->getName() }}</x-backoffice.badge>
                    </a>
                @empty
                    <p class="font-sans text-muted text-[13px] py-4 text-center">No withdrawals yet.</p>
                @endforelse
            </x-backoffice.card>
        </div>
    </x-backoffice.page>
</div>
