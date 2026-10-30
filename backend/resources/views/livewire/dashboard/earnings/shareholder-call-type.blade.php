<div class="min-h-screen bg-background">
    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-[1040px] px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-12 flex flex-col gap-[22px]">

        <x-app.app-bar :title="$callType->name" />

        <div class="flex flex-col gap-[22px] lg:grid lg:grid-cols-3 lg:gap-6 lg:items-start">

        <div class="lg:col-span-1 flex flex-col gap-[22px]">

        @php
            $iconMap = [
                'on_net' => 'ri-flashlight-line',
                'off_net' => 'ri-signal-tower-line',
                'international' => 'ri-global-line',
                'broadband' => 'ri-wifi-line',
            ];
            $icon = $iconMap[$callType->key->value] ?? 'ri-phone-line';
        @endphp
        <div class="bg-surface rounded-[20px] p-5 flex flex-col gap-4 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="size-[42px] rounded-full flex items-center justify-center shrink-0"
                          style="background-color: {{ $callType->color }}1a; color: {{ $callType->color }};">
                        <i class="{{ $icon }} text-[20px]"></i>
                    </span>
                    <div class="min-w-0">
                        <p class="font-heading font-medium text-text text-[18px] truncate">{{ $callType->name }}</p>
                        <p class="font-sans text-muted text-[13px] truncate">{{ $callType->description }}</p>
                    </div>
                </div>
                @if ($inPackage)
                    <span class="flex items-center gap-1.5 rounded-[35px] bg-success/15 px-2.5 py-[5px] shrink-0">
                        <span class="size-2 rounded-full bg-success animate-pulse"></span>
                        <span class="font-sans text-success text-[12px]">Live</span>
                    </span>
                @else
                    <span class="flex items-center gap-1.5 rounded-[35px] bg-muted/15 px-2.5 py-[5px] shrink-0">
                        <i class="ri-lock-2-line text-muted text-[12px]"></i>
                        <span class="font-sans text-muted text-[12px]">Locked</span>
                    </span>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-[15px]">
                <div class="bg-background rounded-[15px] p-4 flex flex-col gap-2">
                    <p class="font-sans text-muted text-[13px]">Total Calls</p>
                    <p class="font-heading font-semibold text-text text-[22px] leading-none">{{ $totalCalls }}</p>
                    <p class="font-sans text-muted text-[12px]">{{ rtrim(rtrim(number_format($totalMinutes, 1), '0'), '.') }} mins today</p>
                </div>
                <div class="bg-background rounded-[15px] p-4 flex flex-col gap-2">
                    <p class="font-sans text-muted text-[13px]">Your Share</p>
                    <p class="font-heading font-semibold text-text text-[22px] leading-none">{{ rtrim(rtrim(number_format($sharePct, 1), '0'), '.') }}%</p>
                    <p class="font-sans text-muted text-[12px]">of call revenue</p>
                </div>
            </div>

            @if ($inPackage)
                <div class="flex items-stretch gap-[15px]">
                    <div class="flex-1 rounded-[15px] bg-secondary/15 p-4 flex flex-col gap-1">
                        <p class="font-sans text-muted text-[13px]">Claimable now</p>
                        <x-money :usd="$claimableTotal" class="font-heading font-semibold text-primary text-[18px] leading-none" />
                    </div>
                    <div class="flex-1 rounded-[15px] bg-background p-4 flex flex-col gap-1">
                        <p class="font-sans text-muted text-[13px]">Claimed today</p>
                        <x-money :usd="$claimedTotal" class="font-heading font-semibold text-text text-[18px] leading-none" />
                    </div>
                </div>
            @endif
        </div>

        @if (! $inPackage)
            <div class="flex flex-col gap-3 rounded-[20px] bg-primary text-surface px-4 py-4">
                <div class="flex items-center gap-3">
                    <i class="ri-pie-chart-line text-secondary text-[26px] shrink-0"></i>
                    <div class="min-w-0 flex-1">
                        @if (! $hasActiveSharePlan)
                            <p class="font-heading font-medium text-[15px]">{{ $callType->name }} share access is inactive</p>
                            <p class="font-sans text-surface/70 text-[12px]">Buy this share product or activate a matching share code. No eSIM activation is required.</p>
                        @elseif ($shareTypeName)
                            <p class="font-heading font-medium text-[15px]">Your active share product is {{ $shareTypeName }}</p>
                            <p class="font-sans text-surface/70 text-[12px]">Share terms do not stack. You can buy {{ $callType->name }} after the current term expires.</p>
                        @else
                            <p class="font-heading font-medium text-[15px]">{{ $callType->name }} is not included in your active package share term</p>
                            <p class="font-sans text-surface/70 text-[12px]">Package-based share eligibility remains separate from this standalone product.</p>
                        @endif
                    </div>
                </div>

                @if ($canBuyShare)
                    <form method="POST" action="{{ route('share-access.buy.start') }}" class="self-start">
                        @csrf
                        <input type="hidden" name="call_type_id" value="{{ $callType->id }}">
                        <button type="submit" class="inline-flex h-[40px] items-center gap-2 rounded-[30px] bg-secondary text-primary px-4 font-sans text-[13px] font-semibold">
                            Buy {{ $callType->name }} share access <x-money :usd="$sharePrice" :abbreviate="false" />
                        </button>
                    </form>
                @elseif (! $hasActiveSharePlan && $shareConfigured)
                    <a href="{{ route('shareholder') }}" wire:navigate class="self-start inline-flex h-[40px] items-center rounded-[30px] bg-secondary text-primary px-4 font-sans text-[13px]">Activate a share code</a>
                @elseif (! $shareConfigured)
                    <p class="font-sans text-surface/65 text-[11px]">This share product has not been configured yet.</p>
                @endif

                @if (! $hasActiveSharePlan && $packageName && $shareRenewalAvailable && $directPackagePay)
                    <form method="POST" action="{{ route('plans.buy.start') }}" class="self-start border-t border-surface/20 pt-2">
                        @csrf
                        <input type="hidden" name="package_id" value="{{ auth()->user()->package_id }}">
                        <input type="hidden" name="mode" value="renew">
                        <button type="submit" class="font-sans text-[12px] text-surface/80 underline">Or renew the package share term</button>
                    </form>
                @endif
            </div>
        @endif

        @if ($inPackage)
            <div class="flex items-center justify-between -mt-1.5 px-1 hidden">
                <p class="font-sans text-muted text-[12px]">
                    <i class="ri-time-line"></i>
                    Minutes left: <span class="text-text">{{ rtrim(rtrim(number_format($remaining, 1), '0'), '.') }}</span>
                    of {{ rtrim(rtrim(number_format($allowance, 1), '0'), '.') }} · expires {{ $shareExpiresAt?->format('M j') }}
                </p>
                <p class="font-sans text-muted text-[11px]">Daily cap remains <x-money :usd="$dailyCapRemaining" /></p>
            </div>
        @endif

        </div>

        <div class="lg:col-span-2 flex flex-col gap-[15px]">
            <p class="font-heading font-medium text-text text-[18px]">Shares log</p>

            @if ($rows->isEmpty())
                <div class="bg-surface rounded-[20px] px-4 py-10 flex flex-col items-center gap-2 text-center shadow-sm">
                    <i class="ri-inbox-line text-muted text-[28px]"></i>
                    <p class="font-sans text-muted text-[14px]">No {{ $callType->name }} calls logged today yet.</p>
                    <p class="font-sans text-muted/70 text-[12px]">New calls are generated through the day.</p>
                </div>
            @else
                <div class="flex flex-col gap-2.5">
                    @foreach ($rows as $row)
                        @php
                            $local = ltrim($row['counterparty'], '0');
                            $maskedNumber = '+234 '.substr($local, 0, 3).'••••'.substr($local, -3);
                        @endphp
                        <div class="bg-surface rounded-[18px] flex items-center justify-between gap-3 px-4 py-3 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)] {{ $row['claimed'] ? 'ring-1 ring-success/25' : '' }}">

                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="size-[40px] rounded-full bg-background overflow-hidden flex items-center justify-center shrink-0 ring-1 ring-black/[0.04]">
                                    <img src="{{ $row['logo'] }}" alt="{{ $row['network'] }}" class="size-full object-cover">
                                </span>
                                <div class="min-w-0 flex flex-col gap-1">
                                    <p class="font-sans font-semibold text-text text-[14px] leading-none truncate">{{ $maskedNumber }}</p>
                                    <p class="font-sans text-muted text-[11px] leading-none flex items-center gap-1.5">
                                        <i class="{{ $row['direction'] === 'incoming' ? 'ri-arrow-left-down-line text-success' : 'ri-arrow-right-up-line text-primary' }}"></i>
                                        {{ $row['network'] }} · {{ rtrim(rtrim(number_format($row['duration'], 1), '0'), '.') }} mins · earned {{ money($row['earnings']) }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 shrink-0">
                                <div class="text-right flex flex-col gap-1">
                                    <p class="font-heading font-semibold text-[14px] leading-none {{ $row['claimed'] ? 'text-success' : ($row['canClaim'] ? 'text-primary' : 'text-muted') }}">
                                        <x-money :usd="$row['claimable']" />
                                    </p>
                                    <p class="font-sans text-muted text-[10px] leading-none">claimable</p>
                                </div>

                                @if ($row['claimed'])
                                    <span class="flex items-center gap-1 rounded-[35px] bg-success/20 text-success px-2.5 py-1.5 font-sans text-[11px] shrink-0">
                                        Claimed <i class="ri-check-line text-[12px]"></i>
                                    </span>
                                @elseif ($row['canClaim'])
                                    <button type="button" wire:click="claim('{{ $row['id'] }}')" wire:loading.attr="disabled"
                                            class="flex items-center gap-1 rounded-[35px] bg-success text-surface px-3.5 py-1.5 font-sans text-[11px] shrink-0 disabled:opacity-60">Claim</button>
                                @elseif (! $inPackage && $hasActiveSharePlan)
                                    <a href="{{ route('shareholder') }}" wire:navigate
                                       class="flex items-center gap-1 rounded-[35px] bg-primary text-surface px-3.5 py-1.5 font-sans text-[11px] shrink-0"><i class="ri-pie-chart-line text-[11px]"></i> Shareholder Portal</a>
                                @elseif ($inPackage && ! $row['capAllows'])
                                    <a href="{{ route('plans') }}" wire:navigate
                                       class="flex items-center gap-1 rounded-[35px] bg-secondary/30 text-primary px-2.5 py-1.5 font-sans text-[11px] shrink-0">
                                        <i class="ri-add-line text-[12px]"></i> More shares
                                    </a>
                                @elseif ($inPackage)
                                    <a href="{{ route('plans') }}" wire:navigate
                                       class="flex items-center gap-1 rounded-[35px] bg-secondary/30 text-primary px-2.5 py-1.5 font-sans text-[11px] shrink-0">
                                        <i class="ri-add-line text-[12px]"></i> Add shares
                                    </a>
                                @else
                                    <span class="rounded-[35px] bg-muted/10 text-muted px-2.5 py-1.5 font-sans text-[10px] shrink-0">Locked</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($rows->hasPages())
                    <div class="pt-1">{{ $rows->links('vendor.pagination.nextel') }}</div>
                @endif
            @endif
        </div>
        </div>
    </div>

    <x-app.bottom-nav />
</div>
