<div>
    <x-backoffice.page title="Dashboard" subtitle="Platform overview">
        @php
            $pendingWithdrawalSummary = $pendingWithdrawalByCurrency->map(
                fn ($minor, $currency) => \App\Support\BackofficeFormat::compactMinor((int) $minor, $currency)
            )->implode(' + ');
            $liabilitySummary = $liabilityByCurrency
                ->take(3)
                ->map(fn ($row) => \App\Support\BackofficeFormat::compactMinor((int) $row->total_minor, $row->currency))
                ->implode(' + ');
        @endphp

        @if ($pendingWithdrawals + $pendingReviews + $pendingShares + $pendingDeposits > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @if ($pendingWithdrawals > 0)
                    <a href="{{ route('backoffice.moderation.withdrawals') }}" wire:navigate
                       class="flex items-center gap-3 rounded-[16px] border bg-[#d79937]/10 border-[#d79937]/25 text-[#d79937] px-4 py-3.5 hover:opacity-90 transition">
                        <i class="ri-time-line text-[22px] shrink-0"></i>
                        <span class="flex-1 min-w-0">
                            <span class="block font-heading font-medium text-text text-[14px]">Withdrawals to settle</span>
                            <span class="block font-sans text-muted text-[12px]">{{ $pendingWithdrawals }} pending{{ $pendingWithdrawalSummary ? ' · '.$pendingWithdrawalSummary : '' }}</span>
                        </span>
                        <i class="ri-arrow-right-s-line text-[20px] shrink-0"></i>
                    </a>
                @endif
                @if ($pendingDeposits > 0)
                    <a href="{{ route('backoffice.moderation.deposits') }}" wire:navigate
                       class="flex items-center gap-3 rounded-[16px] border bg-[#d79937]/10 border-[#d79937]/25 text-[#d79937] px-4 py-3.5 hover:opacity-90 transition">
                        <i class="ri-bank-line text-[22px] shrink-0"></i>
                        <span class="flex-1 min-w-0">
                            <span class="block font-heading font-medium text-text text-[14px]">Deposits to verify</span>
                            <span class="block font-sans text-muted text-[12px]">{{ $pendingDeposits }} awaiting review</span>
                        </span>
                        <i class="ri-arrow-right-s-line text-[20px] shrink-0"></i>
                    </a>
                @endif
                @if ($pendingReviews > 0)
                    <a href="{{ route('backoffice.moderation.reviews') }}" wire:navigate
                       class="flex items-center gap-3 rounded-[16px] border bg-[#d79937]/10 border-[#d79937]/25 text-[#d79937] px-4 py-3.5 hover:opacity-90 transition">
                        <i class="ri-star-smile-line text-[22px] shrink-0"></i>
                        <span class="flex-1 min-w-0">
                            <span class="block font-heading font-medium text-text text-[14px]">Reviews to moderate</span>
                            <span class="block font-sans text-muted text-[12px]">{{ $pendingReviews }} pending decision</span>
                        </span>
                        <i class="ri-arrow-right-s-line text-[20px] shrink-0"></i>
                    </a>
                @endif
                @if ($pendingShares > 0)
                    <a href="{{ route('backoffice.moderation.shares') }}" wire:navigate
                       class="flex items-center gap-3 rounded-[16px] border bg-[#d79937]/10 border-[#d79937]/25 text-[#d79937] px-4 py-3.5 hover:opacity-90 transition">
                        <i class="ri-share-line text-[22px] shrink-0"></i>
                        <span class="flex-1 min-w-0">
                            <span class="block font-heading font-medium text-text text-[14px]">Shares to validate</span>
                            <span class="block font-sans text-muted text-[12px]">{{ $pendingShares }} pending decision</span>
                        </span>
                        <i class="ri-arrow-right-s-line text-[20px] shrink-0"></i>
                    </a>
                @endif
            </div>
        @endif

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
            <x-backoffice.stat label="Total Users" :value="number_format($usersTotal)" icon="ri-group-line" tone="primary" :hint="$usersActive.' active'" :href="route('backoffice.users.index')" />
            <x-backoffice.stat label="New Today" :value="number_format($newUsersToday)" icon="ri-user-add-line" tone="success" />
            <x-backoffice.stat label="Banned" :value="number_format($usersBanned)" icon="ri-forbid-line" tone="danger" :href="route('backoffice.users.index', ['status' => 'banned'])" />
            <x-backoffice.stat label="Liability (all wallets)" :value="$liabilitySummary ?: '—'" icon="ri-wallet-3-line" tone="primary" hint="per currency" :href="route('backoffice.reports.index')" />
            <x-backoffice.stat label="Pending Withdrawals" :value="number_format($pendingWithdrawals)" icon="ri-time-line" tone="warning" :hint="$pendingWithdrawalSummary" :href="route('backoffice.moderation.withdrawals')" />
            <x-backoffice.stat label="Manual Deposits" :value="number_format($pendingDeposits)" icon="ri-bank-line" tone="warning" :href="route('backoffice.moderation.deposits')" />
            <x-backoffice.stat label="Failed Deliveries (today)" :value="number_format($failedDeliveriesToday)" icon="ri-mail-unread-line" tone="{{ $failedDeliveriesToday ? 'danger' : 'muted' }}" :href="route('backoffice.notifications.index')" />
            <x-backoffice.stat label="Webhook Failures (today)" :value="number_format($webhookFailuresToday)" icon="ri-webhook-line" tone="{{ $webhookFailuresToday ? 'danger' : 'muted' }}" :href="route('backoffice.payments.providers')" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">
            <x-backoffice.card title="Recent Signups">
                @forelse ($recentUsers as $u)
                    <a href="{{ route('backoffice.users.show', $u) }}" wire:navigate
                       class="flex items-center gap-3 py-2.5 border-b border-muted/10 last:border-0 hover:opacity-80 transition">
                        <span class="size-9 rounded-full bg-primary/10 flex items-center justify-center font-heading text-primary text-[12px] shrink-0">
                            {{ strtoupper(mb_substr($u->full_name ?: $u->username, 0, 2)) }}
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="block font-sans text-text text-[13px] truncate">{{ $u->full_name }}</span>
                            <span class="block font-sans text-muted text-[12px] truncate">{{ '@'.$u->username }}</span>
                        </span>
                        <span class="font-sans text-muted text-[11px] shrink-0">{{ $u->created_at?->format('j M, H:i') }}</span>
                    </a>
                @empty
                    <p class="font-sans text-muted text-[13px] py-4 text-center">No users yet.</p>
                @endforelse
            </x-backoffice.card>

            <x-backoffice.card title="Recent Withdrawals">
                @forelse ($recentWithdrawals as $w)
                    <a href="{{ route('backoffice.moderation.withdrawals') }}" wire:navigate
                       class="flex items-center gap-3 py-2.5 border-b border-muted/10 last:border-0 hover:opacity-80 transition">
                        <span class="size-9 rounded-full bg-background flex items-center justify-center text-muted shrink-0"><i class="ri-arrow-up-line"></i></span>
                        <span class="flex-1 min-w-0">
                            <span class="block font-sans text-text text-[13px] truncate">
                                <x-backoffice.money :minor="$w->amount_minor" :currency="$w->currency" /> · {{ $w->user?->username ? '@'.$w->user->username : '—' }}
                            </span>
                            <span class="block font-sans text-muted text-[11px] truncate">{{ \Illuminate\Support\Carbon::parse($w->requested_at)->format('j M, H:i') }}</span>
                        </span>
                        <x-backoffice.badge :tone="match ($w->status->value) { 'pending' => 'warning', 'paid' => 'success', 'rejected', 'failed' => 'danger', default => 'muted' }">{{ \Illuminate\Support\Str::headline($w->status->value) }}</x-backoffice.badge>
                    </a>
                @empty
                    <p class="font-sans text-muted text-[13px] py-4 text-center">No withdrawals yet.</p>
                @endforelse
            </x-backoffice.card>

            <x-backoffice.card title="Latest Admin Activity">
                @forelse ($recentAuditLogs as $log)
                    <div class="flex items-start gap-3 py-2.5 border-b border-muted/10 last:border-0">
                        <span class="size-8 rounded-full bg-primary/10 flex items-center justify-center text-primary shrink-0 mt-0.5"><i class="ri-shield-check-line text-[15px]"></i></span>
                        <span class="flex-1 min-w-0">
                            <span class="block font-sans text-text text-[12px] truncate">{{ \Illuminate\Support\Str::headline(str_replace('.', ' ', $log->action)) }}</span>
                            <span class="block font-sans text-muted text-[11px] truncate">{{ $log->adminActor?->full_name ?? 'System' }} · {{ optional($log->created_at)->format('j M, H:i') }}</span>
                        </span>
                    </div>
                @empty
                    <p class="font-sans text-muted text-[13px] py-4 text-center">No admin activity recorded yet.</p>
                @endforelse
            </x-backoffice.card>
        </div>
    </x-backoffice.page>
</div>
