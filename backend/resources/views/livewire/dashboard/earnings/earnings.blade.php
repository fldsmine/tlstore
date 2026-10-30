<div class="min-h-screen bg-background">
    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-12 flex flex-col gap-[22px]">

        <x-app.app-bar :title="'Earnings'" />
        <div class="rounded-[14px] bg-secondary/15 px-4 py-3 font-sans text-muted text-[12px] leading-snug" role="note">
            Today's call-pool entries are synthetic and are not independently verified against carrier records. A claim may use only the remaining accrued share minutes or daily-cap room, with its payout reduced proportionally.
        </div>

        <div class="flex flex-col gap-[22px] lg:grid lg:grid-cols-3 lg:gap-6 lg:items-start">

            <div class="lg:col-span-1 flex flex-col gap-[22px]">

            <div class="relative overflow-hidden bg-surface border border-[#f8f8f8] rounded-[20px] p-5 flex flex-col gap-[15px] items-center shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                <div class="pointer-events-none absolute -left-10 top-[44px] w-[420px] h-[200px] rounded-full blur-2xl opacity-50"
                     style="background: radial-gradient(circle at 50% 50%, rgba(220,235,106,0.55), rgba(220,235,106,0) 70%);"></div>

                <div class="relative flex flex-col gap-[15px] items-center">
                    <p class="font-sans text-muted text-[12px]">Today&rsquo;s Earnings</p>
                    <x-money :usd="$todayEarnings" class="font-heading font-semibold text-text text-[32px] leading-none" />
                </div>

                <div class="relative w-full h-px bg-[#e7e4dc]"></div>

                <div class="relative w-full flex items-center justify-between">
                    <div class="flex flex-col gap-[15px] items-center flex-1">
                        <p class="font-sans text-muted text-[12px]">Claimed</p>
                        <x-money :usd="$claimedTotal" class="font-heading font-medium text-success text-[24px] leading-none" />
                    </div>
                    <div class="w-px h-[60px] bg-[#e7e4dc] shrink-0"></div>
                    <div class="flex flex-col gap-[15px] items-center flex-1">
                        <p class="font-sans text-muted text-[12px]">Available now</p>
                        <x-money :usd="$unclaimedTotal" class="font-heading font-medium text-text text-[24px] leading-none" />
                    </div>
                </div>

                @if ($hasActiveSharePlan)
                    <div class="relative w-full flex flex-col gap-1.5">
                        <p class="font-sans text-muted text-[11px]">Share-term minutes left: <span class="text-text">{{ rtrim(rtrim(number_format($remaining, 1), '0'), '.') }}</span> of {{ rtrim(rtrim(number_format($allowance, 1), '0'), '.') }} · expires {{ $shareExpiresAt?->format('M j, Y') }}</p>
                        <p class="font-sans text-muted text-[11px]">Available to claim now: <span class="text-text">{{ rtrim(rtrim(number_format($available, 1), '0'), '.') }} minutes</span> · unused accrued minutes roll forward.</p>
                        <p class="font-sans text-muted text-[11px]">Daily earning cap remaining: <span class="text-text"><x-money :usd="$dailyCapRemaining" /></span> of <x-money :usd="$dailyCap" /> · resets at midnight</p>
                    </div>
                @endif

                @if ($hasClaimable)
                    <button type="button" wire:click="claimAll" wire:loading.attr="disabled" wire:target="claimAll"
                            class="relative w-full h-[48px] rounded-[30px] bg-primary text-surface font-sans font-semibold text-[14px] flex items-center justify-center gap-2 hover:opacity-95 transition disabled:opacity-60">
                        <span wire:loading.remove wire:target="claimAll"><i class="ri-hand-coin-line"></i> Claim all available</span>
                        <span wire:loading wire:target="claimAll" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Claiming…</span>
                    </button>
                @endif
            </div>

            @if (! $hasActiveSharePlan)
                <div class="relative overflow-hidden bg-surface border border-[#f8f8f8] rounded-[20px] p-5 flex flex-col gap-3 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                    <p class="font-heading font-medium text-text text-[15px]">Share term inactive</p>
                    <p class="font-sans text-muted text-[13px] leading-snug">Claims need a current share subscription with a configured term quota and daily cap.</p>
                    @if ($shareRenewalAvailable && $directPackagePay)
                        <form method="POST" action="{{ route('plans.buy.start') }}" class="self-start">
                            @csrf
                            <input type="hidden" name="package_id" value="{{ auth()->user()->package_id }}">
                            <input type="hidden" name="mode" value="renew">
                            <button type="submit" class="h-[42px] px-4 rounded-full bg-primary text-white font-sans text-[13px]">Start or renew share term</button>
                        </form>
                    @elseif (! auth()->user()->package_id)
                        <a href="{{ route('shareholder') }}" wire:navigate class="self-start font-sans font-semibold text-primary text-[13px]">Open the Shareholder Portal <i class="ri-arrow-right-line"></i></a>
                    @endif
                </div>
            @endif

            @if ($allowanceExhausted)
                <div class="relative overflow-hidden bg-surface border border-[#f8f8f8] rounded-[20px] p-5 flex flex-col gap-4 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                    <div class="flex items-start gap-2.5">
                        <i class="ri-flashlight-fill text-primary text-[22px] shrink-0"></i>
                        <div>
                            <p class="font-heading font-medium text-text text-[15px]">No claim fits the remaining limits</p>
                            <p class="font-sans text-muted text-[13px] leading-snug">{{ $remaining <= 0 ? 'Your share-term minutes are exhausted.' : ($available <= 0 ? 'Your next share-minute allowance has not accrued yet. Unused accrued minutes roll forward.' : ($dailyCapRemaining <= 0 ? 'Your daily earning cap resets at midnight; a share-minute top-up does not increase it.' : 'The remaining quota or cap is too small for a claimable slice.')) }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <a href="{{ route('shareholder') }}" wire:navigate
                           class="{{ $dailyCapRemaining > 0 ? 'flex-1' : 'w-full' }} h-[46px] rounded-[30px] bg-primary text-surface font-sans font-semibold text-[14px] flex items-center justify-center gap-1.5">
                            <i class="ri-pie-chart-line"></i> Shareholder Portal
                        </a>
                        @if ($dailyCapRemaining > 0)
                            <a href="{{ route('plans') }}" wire:navigate
                               class="flex-1 h-[46px] rounded-[30px] bg-secondary/30 hover:bg-secondary/40 transition text-primary font-sans text-[14px] flex items-center justify-center gap-1.5">
                                <i class="ri-add-circle-line"></i> Buy shares
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            </div>

            <div class="lg:col-span-2 flex flex-col gap-[10px]">
                <p class="font-heading font-medium text-text text-[18px] lg:hidden">Earning Overview</p>

                @if ($rows->isEmpty())
                    <div class="bg-surface rounded-[20px] px-4 py-10 flex flex-col items-center gap-2 text-center shadow-sm">
                        <i class="ri-coins-line text-muted text-[28px]"></i>
                        <p class="font-sans text-muted text-[14px]">No call earnings yet today.</p>
                        <p class="font-sans text-muted/70 text-[12px]">New calls are logged through the day — check back soon.</p>
                    </div>
                @else
                    <div class="flex flex-col gap-[10px] lg:grid lg:grid-cols-2 lg:gap-x-5 lg:gap-y-3">
                        @foreach ($rows as $row)
                            <div class="bg-surface rounded-[20px] flex items-center justify-between px-[15px] py-[12px] shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)] {{ $row['claimed'] ? 'ring-1 ring-success/25' : '' }}">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span class="size-9 rounded-full bg-background overflow-hidden flex items-center justify-center shrink-0 ring-1 ring-black/[0.04]">
                                        <img src="{{ $row['logo'] }}" alt="{{ $row['network'] }}" class="size-full object-cover">
                                    </span>
                                    <div class="flex flex-col gap-[4px] min-w-0">
                                        <x-money :usd="$row['amount']" class="font-sans font-semibold text-text text-[14px] leading-none" />
                                        <p class="font-sans text-muted text-[11px] leading-none truncate">{{ $row['type'] }} · {{ $row['occurredAt']?->format('g:iA') }} · earned {{ money($row['earned']) }}</p>
                                        @php $rowCreditMinutes = $row['claimed'] ? $row['claimedMinutes'] : $row['claimableMinutes']; @endphp
                                        @if ($rowCreditMinutes > 0 && $rowCreditMinutes + 0.001 < $row['duration'])
                                            <p class="font-sans text-primary text-[10px] leading-none">Partial claim · {{ rtrim(rtrim(number_format($rowCreditMinutes, 2), '0'), '.') }} of {{ rtrim(rtrim(number_format($row['duration'], 2), '0'), '.') }} min</p>
                                        @endif
                                    </div>
                                </div>

                                @if ($row['claimed'])
                                    <span class="flex items-center gap-1 rounded-[35px] bg-success/20 text-success px-2.5 py-1.5 font-sans text-[10px] shrink-0">
                                        Claimed <i class="ri-check-line text-[12px]"></i>
                                    </span>
                                @elseif ($row['canClaim'])
                                    <button type="button" wire:click="claim('{{ $row['id'] }}')" wire:loading.attr="disabled"
                                            class="flex items-center gap-1 rounded-[35px] bg-success text-surface px-3.5 py-1.5 font-sans text-[10px] shrink-0 disabled:opacity-60">Claim</button>
                                @elseif (! $row['inPackage'] && $hasActiveSharePlan)
                                    <a href="{{ route('shareholder') }}" wire:navigate
                                       class="flex items-center gap-1 rounded-[35px] bg-primary text-surface px-3.5 py-1.5 font-sans text-[10px] shrink-0"><i class="ri-pie-chart-line text-[11px]"></i> Shareholder Portal</a>
                                @elseif ($row['inPackage'] && ! $row['capAllows'])
                                    <span class="rounded-[35px] bg-muted/10 text-muted px-2.5 py-1.5 font-sans text-[10px] shrink-0">Daily cap reached</span>
                                @elseif ($row['inPackage'])
                                    <a href="{{ route('plans') }}" wire:navigate
                                       class="flex items-center gap-1 rounded-[35px] bg-secondary/30 text-primary px-2.5 py-1.5 font-sans text-[10px] shrink-0">
                                        <i class="ri-add-line text-[12px]"></i> Add shares
                                    </a>
                                @else
                                    <span class="rounded-[35px] bg-muted/10 text-muted px-2.5 py-1.5 font-sans text-[10px] shrink-0">Locked</span>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if ($lockedTypes->isNotEmpty())
                        <div class="mt-2 rounded-[20px] bg-primary text-surface px-4 py-4 flex items-center gap-3">
                            <i class="ri-vip-crown-2-line text-secondary text-[24px] shrink-0"></i>
                            <div class="min-w-0 flex-1">
                                <p class="font-heading font-medium text-[14px]"><x-money :usd="$lockedPotential" /> locked in {{ $lockedTypes->join(', ', ' & ') }}</p>
                                <p class="font-sans text-surface/70 text-[12px]">{{ $hasActiveSharePlan ? 'Your current share term does not include these CallTypes. See the Shareholder Portal for access options; share terms do not stack.' : 'Start or renew an eligible share term before claiming.' }}</p>
                            </div>
                            @if ($hasActiveSharePlan || ! auth()->user()->package_id)
                                <a href="{{ route('shareholder') }}" wire:navigate class="shrink-0 inline-flex h-[40px] items-center rounded-[30px] bg-secondary text-primary px-4 font-sans text-[13px]">Shareholder Portal</a>
                            @endif
                        </div>
                    @endif

                    @if ($rows->hasPages())
                        <div class="pt-1">{{ $rows->links('vendor.pagination.nextel') }}</div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <x-app.bottom-nav />
</div>
