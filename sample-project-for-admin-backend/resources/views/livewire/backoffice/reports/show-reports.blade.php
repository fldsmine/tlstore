<div>
    <x-backoffice.page title="Reports" subtitle="Comprehensive platform analytics">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-1.5 flex-wrap">
                @foreach ([0 => 'All time', 7 => '7 days', 30 => '30 days', 90 => '90 days', 365 => '12 months'] as $value => $label)
                    <button type="button" wire:click="setDays({{ $value }})"
                            class="px-3.5 h-[36px] rounded-full font-sans text-[12px] transition {{ $days === $value ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">{{ $label }}</button>
                @endforeach
            </div>

            <div class="flex items-center gap-2.5">
                <span class="font-sans text-muted text-[11px]" wire:loading.remove wire:target="hardRefresh,setDays">
                    Updated {{ \Illuminate\Support\Carbon::parse($generatedAt ?? now())->diffForHumans() }}
                </span>
                <button type="button" wire:click="hardRefresh" wire:loading.attr="disabled" wire:target="hardRefresh"
                        class="px-3.5 h-[36px] rounded-full bg-surface text-muted hover:text-text font-sans text-[12px] inline-flex items-center gap-1.5 disabled:opacity-60 transition">
                    <i class="ri-refresh-line" wire:loading.remove wire:target="hardRefresh"></i>
                    <i class="ri-loader-4-line animate-spin" wire:loading wire:target="hardRefresh"></i>
                    <span wire:loading.remove wire:target="hardRefresh">Refresh</span>
                    <span wire:loading wire:target="hardRefresh">Refreshing…</span>
                </button>
            </div>
        </div>

        {{-- Focused reports with a page per record/user behind them. --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 lg:gap-4">
            <a href="{{ route('backoffice.reports.signins.index') }}" wire:navigate
               class="bg-surface rounded-[20px] p-5 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)] flex items-center gap-4 hover:shadow-[0_6px_16px_rgba(194,194,194,0.25)] transition-shadow">
                <span class="size-10 rounded-[12px] bg-primary/10 text-primary flex items-center justify-center shrink-0"><i class="ri-login-circle-line text-[20px]"></i></span>
                <div class="min-w-0 flex-1">
                    <p class="font-heading font-medium text-text text-[14px]">Sign-ins</p>
                    <p class="font-sans text-muted text-[12px]">Today's sign-ins, by surface, with a detail page per user</p>
                </div>
                <i class="ri-arrow-right-s-line text-muted text-[20px] shrink-0"></i>
            </a>
            <a href="{{ route('backoffice.reports.inbox-opens.index') }}" wire:navigate
               class="bg-surface rounded-[20px] p-5 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)] flex items-center gap-4 hover:shadow-[0_6px_16px_rgba(194,194,194,0.25)] transition-shadow">
                <span class="size-10 rounded-[12px] bg-primary/10 text-primary flex items-center justify-center shrink-0"><i class="ri-mail-open-line text-[20px]"></i></span>
                <div class="min-w-0 flex-1">
                    <p class="font-heading font-medium text-text text-[14px]">Cloud Inbox opens</p>
                    <p class="font-sans text-muted text-[12px]">Users who opened their SMS in the dashboard, message by message</p>
                </div>
                <i class="ri-arrow-right-s-line text-muted text-[20px] shrink-0"></i>
            </a>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
            @foreach ($periodStats as [$label, $value, $icon, $isMoney])
                <x-backoffice.card class="!p-4">
                    <div class="flex items-center gap-3">
                        <span class="size-9 rounded-[10px] bg-primary/10 text-primary flex items-center justify-center shrink-0"><i class="{{ $icon }}"></i></span>
                        <div class="min-w-0">
                            <x-figure :value="$value" :money="$isMoney" class="block font-heading font-semibold text-text text-[18px] leading-none" />
                            <p class="font-sans text-muted text-[12px] mt-1 truncate">{{ $label }}</p>
                        </div>
                    </div>
                </x-backoffice.card>
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
            <x-backoffice.line-chart title="New signups" :total="abbreviate_number(array_sum(array_column($signups, 'value')))" :points="$signups" color="#18443E" />
            <x-backoffice.line-chart title="Earnings credited" :total="'$'.abbreviate_number(array_sum(array_column($earnings, 'value')))" :points="$earnings" color="#2E7266" :money="true" />
            <x-backoffice.line-chart title="Paid out" :total="'$'.abbreviate_number(array_sum(array_column($payouts, 'value')))" :points="$payouts" color="#d79937" :money="true" />
            <x-backoffice.line-chart title="Ad shares" :total="abbreviate_number(array_sum(array_column($adShareSeries, 'value')))" :points="$adShareSeries" color="#2E7266" />
        </div>

        <div class="flex items-center gap-2 pt-1">
            <span class="size-7 rounded-[9px] bg-primary/10 text-primary flex items-center justify-center"><i class="ri-cloud-line text-[16px]"></i></span>
            <h2 class="font-heading text-text text-[15px]">Nextel Cloud</h2>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 lg:gap-4">
            @foreach ($cloudStats as [$label, $value, $icon, $isMoney])
                <x-backoffice.card class="!p-4">
                    <div class="flex items-center gap-3">
                        <span class="size-9 rounded-[10px] bg-primary/10 text-primary flex items-center justify-center shrink-0"><i class="{{ $icon }}"></i></span>
                        <div class="min-w-0">
                            <x-figure :value="$value" :money="$isMoney" class="block font-heading font-semibold text-text text-[18px] leading-none" />
                            <p class="font-sans text-muted text-[12px] mt-1 truncate">{{ $label }}</p>
                        </div>
                    </div>
                </x-backoffice.card>
            @endforeach
        </div>

        <div class="grid grid-cols-1 gap-4 lg:gap-6">
            <x-backoffice.line-chart title="Cloud earnings" :total="'$'.abbreviate_number(array_sum(array_column($cloudEarningsSeries, 'value')))" :points="$cloudEarningsSeries" color="#2f7468" :money="true" />
            {{-- "Cloud subscribers by plan" moved to the Loans page at the client's request — kept here (commented) in case it returns.
            <x-backoffice.card title="Cloud subscribers by plan">
                <div class="flex flex-col gap-3">
                    @forelse ($cloudStoragePlanDistribution as $row)
                        <div class="flex items-center gap-3">
                            <span class="font-sans text-text text-[13px] w-32 shrink-0 truncate">{{ $row['name'] }}</span>
                            <div class="flex-1 h-2.5 rounded-full bg-background overflow-hidden">
                                <div class="h-full rounded-full bg-primary" style="width: {{ max($row['pct'], $row['count'] > 0 ? 2 : 0) }}%"></div>
                            </div>
                            <span class="font-heading text-text text-[13px] w-16 text-right shrink-0">{{ number_format($row['count']) }}</span>
                            <span class="font-sans text-muted text-[12px] w-12 text-right shrink-0">{{ $row['pct'] }}%</span>
                        </div>
                    @empty
                        <p class="font-sans text-muted text-[13px] py-3 text-center">No Cloud storage plans yet.</p>
                    @endforelse
                </div>
            </x-backoffice.card>
            --}}
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
            <x-backoffice.card title="Referrals — direct vs indirect">
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-[12px] bg-background px-3.5 py-3">
                        <p class="font-sans text-muted text-[12px]">Direct</p>
                        <p class="font-heading font-semibold text-text text-[18px] mt-0.5">{{ number_format($referralBreakdown['directRefs']) }}</p>
                    </div>
                    <div class="rounded-[12px] bg-background px-3.5 py-3">
                        <p class="font-sans text-muted text-[12px]">Indirect</p>
                        <p class="font-heading font-semibold text-text text-[18px] mt-0.5">{{ number_format($referralBreakdown['indirectRefs']) }}</p>
                    </div>
                </div>
                <div class="flex flex-col mt-1">
                    <div class="flex items-center gap-3 py-2.5 border-b border-muted/10">
                        <span class="font-sans text-muted text-[13px]">Direct bonus paid</span>
                        <span class="font-sans text-muted text-[12px] ml-auto">{{ number_format($referralBreakdown['directBonusCount']) }} payouts</span>
                        <x-figure :value="$referralBreakdown['directBonusAmount']" money class="font-heading text-text text-[13px] w-24 text-right" />
                    </div>
                    <div class="flex items-center gap-3 py-2.5">
                        <span class="font-sans text-muted text-[13px]">Indirect bonus paid</span>
                        <span class="font-sans text-muted text-[12px] ml-auto">{{ number_format($referralBreakdown['indirectBonusCount']) }} payouts</span>
                        <x-figure :value="$referralBreakdown['indirectBonusAmount']" money class="font-heading text-text text-[13px] w-24 text-right" />
                    </div>
                </div>
            </x-backoffice.card>

            <x-backoffice.card title="Earnings by source">
                <div class="flex flex-col gap-3">
                    @foreach ($earningsBySource as $row)
                        <div class="flex items-center gap-3">
                            <span class="font-sans text-text text-[13px] w-28 shrink-0 truncate">{{ $row['label'] }}</span>
                            <div class="flex-1 h-2.5 rounded-full bg-background overflow-hidden">
                                <div class="h-full rounded-full bg-primary" style="width: {{ max($row['pct'], $row['amount'] > 0 ? 2 : 0) }}%"></div>
                            </div>
                            <x-figure :value="$row['amount']" money class="font-heading text-text text-[13px] w-24 text-right shrink-0" />
                            <span class="font-sans text-muted text-[12px] w-10 text-right shrink-0">{{ $row['pct'] }}%</span>
                        </div>
                    @endforeach
                </div>
            </x-backoffice.card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
            <x-backoffice.card title="Withdrawals by status (all-time)">
                <div class="flex flex-col">
                    @foreach ($withdrawalRows as [$label, $count, $amount, $tone])
                        <div class="flex items-center justify-between gap-3 py-2.5 border-b border-muted/10 last:border-0">
                            <span class="flex items-center gap-2"><x-backoffice.badge :tone="$tone">{{ $label }}</x-backoffice.badge></span>
                            <span class="font-sans text-muted text-[13px]">{{ number_format($count) }} req.</span>
                            <x-figure :value="$amount" money class="font-heading text-text text-[13px] ml-auto" />
                        </div>
                    @endforeach
                </div>
            </x-backoffice.card>

            <x-backoffice.card title="Users snapshot (all-time)">
                <div class="grid grid-cols-2 gap-x-6">
                    @foreach ($usersSnapshot as [$label, $value])
                        <div class="flex items-center justify-between gap-3 py-2.5 border-b border-muted/10">
                            <span class="font-sans text-muted text-[13px]">{{ $label }}</span>
                            <span class="font-heading text-text text-[13px]">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>
            </x-backoffice.card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
            <x-backoffice.card title="Loans by status (all-time)">
                <div class="flex flex-col">
                    @foreach ($loanRows as [$label, $count, $amount, $tone])
                        <div class="flex items-center justify-between gap-3 py-2.5 border-b border-muted/10 last:border-0">
                            <span class="flex items-center gap-2"><x-backoffice.badge :tone="$tone">{{ $label }}</x-backoffice.badge></span>
                            <span class="font-sans text-muted text-[13px]">{{ number_format($count) }} loan{{ $count === 1 ? '' : 's' }}</span>
                            <x-figure :value="$amount" money class="font-heading text-text text-[13px] ml-auto" />
                        </div>
                    @endforeach
                </div>
                <p class="font-sans text-muted text-[11px] mt-2 leading-relaxed">Amount shows scheduled repayment by status. Paid means staff recorded the loan as paid/disbursed; it does not mean the loan has been repaid.</p>
            </x-backoffice.card>

            <x-backoffice.card title="Payouts by currency (completed)">
                <div class="flex flex-col">
                    @forelse ($payoutsByCurrency as $p)
                        <div class="flex items-center justify-between gap-3 py-2.5 border-b border-muted/10 last:border-0">
                            <span class="font-heading text-text text-[13px] w-12 shrink-0">{{ $p['currency'] }}</span>
                            <span class="font-sans text-muted text-[13px]">{{ number_format($p['count']) }} paid</span>
                            <span class="font-sans text-muted text-[12px] ml-auto tabular-nums">{{ number_format($p['local'], 2) }}</span>
                            <x-figure :value="$p['usd']" money class="font-heading text-text text-[13px] w-24 text-right shrink-0" />
                        </div>
                    @empty
                        <p class="font-sans text-muted text-[13px] py-3 text-center">No completed payouts.</p>
                    @endforelse
                </div>
            </x-backoffice.card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
            <x-backoffice.card title="VTU orders by status (all-time)">
                <div class="flex flex-col">
                    @foreach ($vtuRows as [$label, $count, $amount, $tone])
                        <div class="flex items-center justify-between gap-3 py-2.5 border-b border-muted/10 last:border-0">
                            <span class="flex items-center gap-2"><x-backoffice.badge :tone="$tone">{{ $label }}</x-backoffice.badge></span>
                            <span class="font-sans text-muted text-[13px]">{{ number_format($count) }} order{{ $count === 1 ? '' : 's' }}</span>
                            <x-figure :value="$amount" money class="font-heading text-text text-[13px] ml-auto" />
                        </div>
                    @endforeach
                </div>
            </x-backoffice.card>

            <x-backoffice.card title="VTU delivered by service">
                <div class="flex flex-col">
                    @forelse ($vtuByService as $s)
                        <div class="flex items-center justify-between gap-3 py-2.5 border-b border-muted/10 last:border-0">
                            <span class="font-sans text-text text-[13px]">{{ $s['label'] }}</span>
                            <span class="font-sans text-muted text-[13px]">{{ number_format($s['count']) }}</span>
                            <x-figure :value="$s['amount']" money class="font-heading text-text text-[13px] ml-auto" />
                        </div>
                    @empty
                        <p class="font-sans text-muted text-[13px] py-3 text-center">No delivered VTU orders in this window.</p>
                    @endforelse
                </div>
            </x-backoffice.card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
            <x-backoffice.card title="Cloud SMS & bonuses">
                <div class="grid grid-cols-2 gap-x-6">
                    @foreach ($cloudSms as [$label, $value, $isMoney])
                        <div class="flex items-center justify-between gap-3 py-2.5 border-b border-muted/10">
                            <span class="font-sans text-muted text-[13px]">{{ $label }}</span>
                            @if ($isMoney)
                                <x-figure :value="$value" money class="font-heading text-text text-[13px]" />
                            @else
                                <span class="font-heading text-text text-[13px]">{{ number_format($value) }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-backoffice.card>

            <x-backoffice.card title="Token inventory (all-time)">
                <div class="grid grid-cols-3 gap-3 mb-3">
                    @foreach ($tokenInventory as [$label, $count, $amount])
                        <div class="rounded-[12px] bg-background px-3.5 py-3">
                            <p class="font-sans text-muted text-[12px]">{{ $label }}</p>
                            <p class="font-heading font-semibold text-text text-[16px] mt-0.5">{{ number_format($count) }}</p>
                            @unless (is_null($amount))<x-figure :value="$amount" money class="block font-sans text-muted text-[11px]" />@endunless
                        </div>
                    @endforeach
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($tokensByType as $t)
                        <span class="rounded-full bg-primary/10 text-primary px-2.5 py-1 font-sans text-[12px]">{{ $t['label'] }}: {{ number_format($t['count']) }}</span>
                    @endforeach
                </div>
            </x-backoffice.card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
            <x-backoffice.card title="Users by country">
                @php $countryTotal = array_sum(array_column($usersByCountry, 'count')); @endphp
                <div class="flex flex-col gap-3">
                    @foreach ($usersByCountry as $c)
                        <div class="flex items-center gap-3">
                            <span class="font-sans text-text text-[13px] w-32 shrink-0 truncate">{{ $countryNames[$c['code']] ?? $c['code'] }}</span>
                            <div class="flex-1 h-2.5 rounded-full bg-background overflow-hidden">
                                <div class="h-full rounded-full bg-primary" style="width: {{ $countryTotal > 0 ? max(2, (int) round($c['count'] / $countryTotal * 100)) : 0 }}%"></div>
                            </div>
                            <span class="font-heading text-text text-[13px] w-12 text-right shrink-0">{{ number_format($c['count']) }}</span>
                        </div>
                    @endforeach
                </div>
            </x-backoffice.card>

            <x-backoffice.card title="Call earnings by type">
                <div class="flex flex-col">
                    @forelse ($callEarningsByType as $c)
                        <div class="flex items-center justify-between gap-3 py-2.5 border-b border-muted/10 last:border-0">
                            <span class="font-sans text-text text-[13px]">{{ $c['label'] }}</span>
                            <span class="font-sans text-muted text-[13px]">{{ number_format($c['count']) }} claims</span>
                            <x-figure :value="$c['amount']" money class="font-heading text-success text-[13px] ml-auto" />
                        </div>
                    @empty
                        <p class="font-sans text-muted text-[13px] py-3 text-center">No call claims in this window.</p>
                    @endforelse
                </div>
            </x-backoffice.card>
        </div>

        <x-backoffice.card title="Adjustments & vendor transfers (this period)">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="rounded-[12px] bg-background px-3.5 py-3">
                    <p class="font-sans text-muted text-[12px]">Adjustments credited</p>
                    <x-figure :value="$adjustments['credited']" money class="block font-heading font-semibold text-success text-[15px] mt-0.5" />
                </div>
                <div class="rounded-[12px] bg-background px-3.5 py-3">
                    <p class="font-sans text-muted text-[12px]">Adjustments debited</p>
                    <x-figure :value="$adjustments['debited']" money class="block font-heading font-semibold text-danger text-[15px] mt-0.5" />
                </div>
                <div class="rounded-[12px] bg-background px-3.5 py-3">
                    <p class="font-sans text-muted text-[12px]">Vendor transfers</p>
                    <p class="font-heading font-semibold text-text text-[15px] mt-0.5">{{ number_format($agentTransfers['count']) }}</p>
                </div>
                <div class="rounded-[12px] bg-background px-3.5 py-3">
                    <p class="font-sans text-muted text-[12px]">Transfer volume</p>
                    <x-figure :value="$agentTransfers['amount']" money class="block font-heading font-semibold text-text text-[15px] mt-0.5" />
                </div>
            </div>
        </x-backoffice.card>

        <x-backoffice.card title="Users by package">
            <div class="flex flex-col gap-3">
                @foreach ($usersByPackage as $row)
                    <div class="flex items-center gap-3">
                        <span class="font-sans text-text text-[13px] w-32 shrink-0 truncate">{{ $row['name'] }}</span>
                        <div class="flex-1 h-2.5 rounded-full bg-background overflow-hidden">
                            <div class="h-full rounded-full {{ $row['name'] === 'No package' ? 'bg-muted/40' : 'bg-primary' }}" style="width: {{ max($row['pct'], $row['count'] > 0 ? 2 : 0) }}%"></div>
                        </div>
                        <span class="font-heading text-text text-[13px] w-16 text-right shrink-0">{{ number_format($row['count']) }}</span>
                        <span class="font-sans text-muted text-[12px] w-12 text-right shrink-0">{{ $row['pct'] }}%</span>
                    </div>
                @endforeach
            </div>
        </x-backoffice.card>

        <x-backoffice.card title="Wallet balances (held across all users)">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                @foreach ($walletSnapshot as [$label, $amount])
                    <div class="rounded-[12px] bg-background px-3.5 py-3">
                        <p class="font-sans text-muted text-[12px]">{{ $label }}</p>
                        <x-figure :value="$amount" money class="block font-heading font-semibold text-text text-[15px] mt-0.5" />
                    </div>
                @endforeach
            </div>
        </x-backoffice.card>

        <a href="{{ route('backoffice.reports.withdrawal-eligible.index') }}" wire:navigate
           class="group block rounded-[20px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
            <x-backoffice.card title="Withdrawal-eligible balances" class="!p-4 transition group-hover:ring-1 group-hover:ring-primary/25">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2 -mt-2 mb-4">
                    <p class="font-sans text-muted text-[12px] leading-relaxed">Per wallet: users whose balance is at or above that wallet's minimum withdrawal, and the total balance they hold (eligible to be withdrawn).</p>
                    <span class="shrink-0 inline-flex items-center gap-1 font-sans font-semibold text-primary text-[11px]">View eligible users <i class="ri-arrow-right-line"></i></span>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                    @foreach ($withdrawalEligible as $w)
                        <div class="rounded-[12px] bg-background px-3.5 py-3 flex flex-col gap-1.5">
                            <div class="flex items-center justify-between gap-2">
                                <p class="font-sans text-muted text-[12px] truncate">{{ $w['label'] }}</p>
                                @unless ($w['enabled'])<span class="shrink-0 rounded-full bg-muted/15 text-muted px-1.5 py-0.5 font-sans text-[9px]">closed</span>@endunless
                            </div>
                            <p class="font-sans text-muted text-[11px]">Min {{ money($w['min']) }}</p>
                            <div class="flex items-end justify-between gap-2 mt-0.5">
                                <div class="min-w-0">
                                    <p class="font-heading font-semibold text-text text-[18px] leading-none">{{ number_format($w['count']) }}</p>
                                    <p class="font-sans text-muted text-[10px] mt-0.5">user{{ $w['count'] === 1 ? '' : 's' }} eligible</p>
                                </div>
                                <x-figure :value="$w['total']" money class="font-heading font-semibold text-success text-[14px] text-right shrink-0" />
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-backoffice.card>
        </a>

        {{-- Own Livewire component so changing wallet/step re-runs only its query, not the whole page. --}}
        <livewire:backoffice.reports.balance-distribution />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">
            <x-backoffice.card title="Top Earners">
                @forelse ($topEarners as $e)
                    <a href="{{ route('backoffice.users.show', $e['id']) }}" wire:navigate class="flex items-center gap-3 py-2 border-b border-muted/10 last:border-0 hover:opacity-80">
                        <span class="font-heading text-muted text-[12px] w-4">{{ $loop->iteration }}</span>
                        <span class="flex-1 min-w-0 font-sans text-text text-[13px] truncate">{{ $e['username'] }}</span>
                        <x-figure :value="$e['value']" money class="font-heading text-success text-[13px]" />
                    </a>
                @empty
                    <p class="font-sans text-muted text-[13px] py-3 text-center">No data.</p>
                @endforelse
            </x-backoffice.card>

            <x-backoffice.card title="Top Agents (tokens redeemed)">
                @forelse ($topAgents as $a)
                    <a href="{{ route('backoffice.users.show', $a['id']) }}" wire:navigate class="flex items-center gap-3 py-2 border-b border-muted/10 last:border-0 hover:opacity-80">
                        <span class="font-heading text-muted text-[12px] w-4">{{ $loop->iteration }}</span>
                        <span class="flex-1 min-w-0 font-sans text-text text-[13px] truncate">{{ $a['username'] }}</span>
                        <span class="font-heading text-text text-[13px]">{{ number_format($a['count']) }}</span>
                    </a>
                @empty
                    <p class="font-sans text-muted text-[13px] py-3 text-center">No data.</p>
                @endforelse
            </x-backoffice.card>

            <x-backoffice.card title="Top Referrers (direct)">
                @forelse ($topReferrers as $r)
                    <a href="{{ route('backoffice.users.show', $r['id']) }}" wire:navigate class="flex items-center gap-3 py-2 border-b border-muted/10 last:border-0 hover:opacity-80">
                        <span class="font-heading text-muted text-[12px] w-4">{{ $loop->iteration }}</span>
                        <span class="flex-1 min-w-0 font-sans text-text text-[13px] truncate">{{ $r['username'] }}</span>
                        <span class="font-heading text-text text-[13px]">{{ number_format($r['count']) }}</span>
                    </a>
                @empty
                    <p class="font-sans text-muted text-[13px] py-3 text-center">No data.</p>
                @endforelse
            </x-backoffice.card>
        </div>
    </x-backoffice.page>
</div>
