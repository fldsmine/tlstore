<div>
    <x-backoffice.page title="Withdrawal-eligible balances"
                       subtitle="Per-wallet users and balances from the Reports total"
                       :back="route('backoffice.reports.index')">
        <div class="flex flex-col gap-4 lg:gap-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 lg:gap-4">
                @foreach ($walletSummaries as $summary)
                    <button type="button" wire:key="eligible-wallet-{{ $summary['key'] }}"
                            wire:click="selectWallet('{{ $summary['key'] }}')"
                            aria-pressed="{{ $selectedWallet['key'] === $summary['key'] ? 'true' : 'false' }}"
                            class="text-left rounded-[18px] border p-4 transition {{ $selectedWallet['key'] === $summary['key'] ? 'border-primary bg-primary/5 ring-1 ring-primary/20' : 'border-muted/10 bg-surface hover:border-primary/30' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-heading font-medium text-text text-[14px] truncate">{{ $summary['label'] }}</p>
                                <p class="font-sans text-muted text-[11px] mt-1">Minimum {{ money($summary['min']) }}</p>
                            </div>
                            @unless ($summary['enabled'])
                                <span class="shrink-0 rounded-full bg-muted/15 text-muted px-2 py-1 font-sans text-[9px]">closed</span>
                            @endunless
                        </div>
                        <div class="flex items-end justify-between gap-3 mt-4">
                            <div>
                                <p class="font-heading font-semibold text-text text-[20px] leading-none">{{ number_format($summary['count']) }}</p>
                                <p class="font-sans text-muted text-[10px] mt-1">users at/above minimum</p>
                            </div>
                            <x-figure :value="$summary['total']" money class="font-heading font-semibold text-success text-[15px] text-right" />
                        </div>
                    </button>
                @endforeach
            </div>

            <x-backoffice.card>
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div>
                        <p class="font-sans text-muted text-[11px] uppercase tracking-wide">Selected wallet</p>
                        <h2 class="font-heading font-semibold text-text text-[18px] mt-1">{{ $selectedWallet['label'] }}</h2>
                        <p class="font-sans text-muted text-[12px] mt-1">Eligible when the balance is at least {{ money($selectedWallet['min']) }}. This is the same threshold used by the Reports card.</p>
                    </div>
                    <div class="sm:text-right">
                        <p class="font-sans text-muted text-[11px]">All matching users · unfiltered</p>
                        <p class="font-heading font-semibold text-success text-[22px] mt-0.5">{{ money($selectedWallet['total']) }}</p>
                        <p class="font-sans text-muted text-[11px]">{{ number_format($selectedWallet['count']) }} users</p>
                    </div>
                </div>
            </x-backoffice.card>

            <x-backoffice.card class="!p-0 overflow-hidden">
                <div class="p-4 lg:p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h2 class="font-heading font-medium text-text text-[15px]">Users with eligible {{ $selectedWallet['label'] }} balances</h2>
                        <p class="font-sans text-muted text-[11px] mt-1">The listed balances sum to the unfiltered total above. Search filters this list only.</p>
                    </div>
                    <label class="relative w-full sm:w-[280px]">
                        <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-muted text-[14px]"></i>
                        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name, username, email, phone"
                               class="w-full h-[40px] rounded-[12px] bg-background border border-muted/15 pl-9 pr-3 font-sans text-[12px] text-text outline-none focus:border-primary">
                    </label>
                </div>

                @if ($users->isEmpty())
                    <div class="px-5 py-12 flex flex-col items-center gap-2 text-center border-t border-muted/10">
                        <span class="size-11 rounded-full bg-background flex items-center justify-center text-muted"><i class="ri-user-search-line text-[20px]"></i></span>
                        <p class="font-sans text-muted text-[13px]">{{ $search !== '' ? 'No eligible users match this search.' : 'No users meet this wallet minimum.' }}</p>
                    </div>
                @else
                    <div class="overflow-x-auto border-t border-muted/10">
                        <table class="w-full min-w-[640px] text-left">
                            <thead>
                                <tr class="font-sans text-[11px] text-muted bg-background/60">
                                    <th class="px-5 py-3 font-medium">User</th>
                                    <th class="px-5 py-3 font-medium">Account status</th>
                                    <th class="px-5 py-3 font-medium text-right">{{ $selectedWallet['label'] }} balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users as $user)
                                    <tr wire:key="eligible-user-{{ $user->id }}" class="border-t border-muted/5 hover:bg-background/70 transition">
                                        <td class="px-5 py-3">
                                            <a href="{{ route('backoffice.users.show', $user) }}" wire:navigate class="block hover:opacity-80 transition">
                                                <x-backoffice.user-cell :user="$user" />
                                            </a>
                                        </td>
                                        <td class="px-5 py-3">
                                            <x-backoffice.badge :tone="$user->status?->value === 'active' ? 'success' : 'danger'">{{ $user->status?->getName() ?? 'Unknown' }}</x-backoffice.badge>
                                        </td>
                                        <td class="px-5 py-3 text-right whitespace-nowrap">
                                            <x-figure :value="(float) $user->{$selectedWallet['column']}" money class="font-heading font-semibold text-text text-[13px]" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($users->hasPages())
                        <div class="p-4 border-t border-muted/10">{{ $users->links() }}</div>
                    @endif
                @endif
            </x-backoffice.card>
        </div>
    </x-backoffice.page>
</div>
