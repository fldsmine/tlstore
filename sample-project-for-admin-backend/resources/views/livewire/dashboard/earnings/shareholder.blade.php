<div class="min-h-screen bg-background">
    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-12 flex flex-col gap-[22px]">

        <x-app.app-bar :title="'Shareholder Portal'" />

        <p class="font-sans text-muted text-[14px] leading-snug">
            Eligible share-plan holders can claim the revenue share of today&rsquo;s call records. Call records refresh daily; your share-minute quota lasts for the active term, while the earning cap resets daily.
        </p>

        @if ($hasActiveSharePlan)
            <div class="rounded-[16px] bg-secondary/15 px-4 py-3 flex flex-col gap-2">
                <div class="flex items-center gap-2">
                    <i class="ri-time-line text-primary text-[16px]"></i>
                    <p class="font-sans text-text text-[13px]">
                        Share minutes remaining: <span class="font-semibold">{{ rtrim(rtrim(number_format($remaining, 1), '0'), '.') }}</span>
                        of {{ rtrim(rtrim(number_format($allowance, 1), '0'), '.') }}
                    </p>
                </div>
                @if ($shareTypeName)
                    <p class="font-sans text-muted text-[13px]">Active share product: <span class="font-semibold text-text">{{ $shareTypeName }}</span> only.</p>
                @elseif ($packageName)
                    <p class="font-sans text-muted text-[13px]">Share access from your {{ $packageName }} eSIM package.</p>
                @endif
                <div class="flex items-center justify-between gap-3 font-sans text-[13px]">
                    <span class="text-muted">Daily earning cap remaining</span>
                    <span class="text-text"><x-money :usd="$dailyCapRemaining" />· resets at midnight</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <p class="font-sans text-muted text-[12px]">Term expires {{ $shareExpiresAt?->format('M j, Y') }}</p>
                    @if (config('settings.minutes.enabled'))
                        <a href="{{ route('plans') }}" wire:navigate class="font-sans text-[13px] font-semibold text-primary">Add share minutes</a>
                    @endif
                </div>
            </div>
        @else
            <div class="rounded-[16px] bg-primary/10 px-4 py-3 flex flex-col gap-2">
                <p class="font-heading font-medium text-text text-[14px]">No active share term</p>
                @if ($packageName)
                    <p class="font-sans text-muted text-[13px]">Start a share product to claim earnings. Your {{ $packageName }} eSIM access is separate and will not change.</p>
                    @if ($shareRenewalAvailable && $directPackagePay)
                        <form method="POST" action="{{ route('plans.buy.start') }}" class="self-start">
                            @csrf
                            <input type="hidden" name="package_id" value="{{ auth()->user()->package_id }}">
                            <input type="hidden" name="mode" value="renew">
                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-full bg-primary px-4 py-2 font-sans text-[13px] font-semibold text-white">Renew package share term <i class="ri-arrow-right-line"></i></button>
                        </form>
                    @elseif (! $shareRenewalAvailable)
                        <p class="font-sans text-muted text-[12px]">The package share quota and daily cap are not active. You can still buy a share product below.</p>
                    @endif
                @else
                    <p class="font-sans text-muted text-[13px]">Choose a CallType share product or redeem a share activation code below. eSIM activation is not required.</p>
                @endif
            </div>
        @endif

        @if (! $hasActiveSharePlan && $hasConfiguredShareProducts)
            @php
                $selectedShareCard = $cards->first(fn (array $card): bool => $card['type']->id === $shareCallTypeId);
            @endphp

            <section class="rounded-[18px] bg-surface p-4 flex flex-col gap-4 shadow-sm" aria-labelledby="share-product-heading">
                <div class="flex flex-col gap-1">
                    <p id="share-product-heading" class="font-heading font-medium text-text text-[15px]">Choose a share product</p>
                    <p class="font-sans text-muted text-[13px]">Select the CallType for your separate share term. Redeem a matching share activation code or pay directly when checkout is available.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    @foreach ($couponShareTypes as $shareType)
                        @php
                            $productCard = $cards->first(fn (array $card): bool => $card['type']->id === $shareType->id);
                            $isSelected = $shareCallTypeId === $shareType->id;
                        @endphp
                        <button type="button" wire:key="share-product-{{ $shareType->id }}"
                                wire:click="$set('shareCallTypeId', '{{ $shareType->id }}')"
                                aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                                class="relative rounded-[14px] border p-3 text-left transition focus:outline-none focus:ring-2 focus:ring-primary/40 {{ $isSelected ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-muted/20 bg-surface hover:border-muted/40' }}">
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-heading font-medium text-text text-[13px] leading-tight">{{ $shareType->name }}</p>
                                @if ($isSelected)
                                    <span class="shrink-0 rounded-full bg-primary/10 px-2 py-0.5 font-sans text-[11px] font-semibold text-primary">Selected</span>
                                @endif
                            </div>
                            <x-money :usd="(float) ($productCard['sharePrice'] ?? 0)" :abbreviate="false" class="mt-1 block font-heading font-semibold text-primary text-[15px] leading-tight" />
                            <p class="mt-1 font-sans text-muted text-[11px] leading-snug">{{ $shareType->description ?: 'Share access for this CallType.' }}</p>
                        </button>
                    @endforeach
                </div>
                @error('shareCallTypeId') <span class="font-sans text-[13px] text-danger">{{ $message }}</span> @enderror

                @if ($selectedShareCard)
                    @php $selectedShareType = $selectedShareCard['type']; @endphp
                    <div class="rounded-[14px] bg-background px-4 py-3 flex flex-col gap-2.5 border-primary bg-primary/5 ring-1 ring-primary">
                        <div>
                            <p class="font-heading font-medium text-text text-[15px]">{{ $selectedShareType->name }} share details</p>
                            <p class="mt-0.5 font-sans text-warning text-[13px]">This term covers this CallType only. It does not activate or change your eSIM package.</p>
                        </div>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-2 border-t border-muted/10 pt-2.5">
                            <div class="flex flex-col gap-0.5">
                                <span class="font-sans text-info text-[12px]">Daily earning cap</span>
                                <x-money :usd="(float) $selectedShareType->max_daily_shareholder_earning" :abbreviate="false" class="font-sans text-text text-[14px] font-semibold" />
                            </div>
                            <div class="flex flex-col gap-0.5">
                                <span class="font-sans text-info text-[12px]">Resell revenue share</span>
                                <span class="font-sans text-text text-[14px] font-semibold">{{ rtrim(rtrim(number_format((float) $selectedShareType->claimable_percentage, 2), '0'), '.') }}%</span>
                            </div>
                            <div class="flex flex-col gap-0.5">
                                <span class="font-sans text-info text-[12px]">Monthly share quota</span>
                                <span class="font-sans text-text text-[14px] font-semibold">{{ rtrim(rtrim(number_format((float) $selectedShareType->monthly_share_minutes, 2), '0'), '.') }} minutes</span>
                            </div>
                            <div class="flex flex-col gap-0.5">
                                <span class="font-sans text-info text-[12px]">Withdrawal</span>
                                <span class="font-sans text-text text-[14px] font-semibold">24/7</span>
                            </div>
                            <div class="col-span-2 flex items-center justify-between gap-3 border-t border-muted/10 pt-2">
                                <span class="font-sans text-info text-[12px]">Approx. ≃ Monthly Withdrawal</span>
                                <x-money :usd="(float) ($selectedShareType->max_daily_shareholder_earning * 30) " :abbreviate="false" class="font-sans text-text text-[14px] font-semibold" />
                            </div>
                            <div class="col-span-2 flex items-center justify-between gap-3 border-t border-muted/10 pt-2">
                                <span class="font-sans text-warning text-[12px]">Minimum shareholder withdrawal</span>
                                <x-money :usd="(float) config('settings.withdrawal.wallets.shareholder.min', 5)" :abbreviate="false" class="font-sans text-text text-[14px] font-semibold" />
                            </div>
                        </div>
                    </div>

                    @if ($selectedShareCard['canBuyShare'])
                        <form method="POST" action="{{ route('share-access.buy.start') }}" class="flex flex-col gap-2 rounded-[14px] border border-primary/15 bg-primary/5 p-3">
                            @csrf
                            <input type="hidden" name="call_type_id" value="{{ $selectedShareType->id }}">
                            <div class="flex items-center justify-between gap-3">
                                <span class="font-sans text-muted text-[13px]">Direct purchase</span>
                                <x-money :usd="(float) $selectedShareCard['sharePrice']" :abbreviate="false" class="font-sans text-text text-[13px] font-semibold" />
                            </div>
                            <button type="submit" class="w-full h-[46px] rounded-full bg-primary px-4 font-sans text-[13px] font-semibold text-white inline-flex items-center justify-center gap-2 hover:opacity-95 transition">
                                <i class="ri-secure-payment-line text-[16px]"></i> Pay directly
                            </button>
                        </form>
                    @else
                        <p class="rounded-[12px] bg-background px-3 py-2 font-sans text-muted text-[12px]">
                            Direct checkout is not available for this product right now. You can still activate it with a matching share code below.
                        </p>
                    @endif
                @endif

                <form wire:submit="redeemShare" class="flex flex-col gap-3 border-t border-muted/10 pt-3">
                    <div class="flex flex-col gap-1">
                        <label for="share-activation-code" class="font-sans text-[13px] text-text font-medium">Activate with a share code</label>
                        <p class="font-sans text-muted text-[12px]">This is a share activation code, not an eSIM activation code.</p>
                    </div>
                    <input id="share-activation-code" type="text" wire:model="shareToken" placeholder="Share activation code" autocomplete="off" class="h-[46px] rounded-[12px] bg-background border border-muted/20 px-3 font-sans text-[13px] uppercase text-text placeholder:normal-case focus:outline-none focus:ring-2 focus:ring-primary/30">
                    @error('shareToken') <span class="font-sans text-[13px] text-danger">{{ $message }}</span> @enderror
                    <button type="submit" wire:loading.attr="disabled" wire:target="redeemShare" @disabled(! $shareCallTypeId) class="h-[44px] rounded-full bg-primary px-5 font-sans text-[13px] font-semibold text-white disabled:opacity-60">
                        <span wire:loading.remove wire:target="redeemShare">Activate share access</span>
                        <span wire:loading wire:target="redeemShare">Activating…</span>
                    </button>
                </form>
            </section>
        @endif

        @php
            $iconMap = [
                'on_net' => 'ri-flashlight-line',
                'off_net' => 'ri-signal-tower-line',
                'international' => 'ri-global-line',
                'broadband' => 'ri-wifi-line',
            ];
        @endphp

        @if ($hasActiveSharePlan)
            <div class="flex flex-col gap-4 lg:grid lg:grid-cols-2 lg:gap-5">
            @foreach ($cards as $card)
            @php
                $ct = $card['type'];
                $key = $ct->key->value;
                $icon = $iconMap[$key] ?? 'ri-phone-line';
                $locked = ! $card['inPackage'];
            @endphp
            <div class="relative bg-surface rounded-[20px] px-4 py-5 flex flex-col gap-5 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)] {{ $locked ? 'ring-1 ring-muted/10' : '' }}">
                <div class="flex flex-col gap-[7px]">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="size-[34px] rounded-full flex items-center justify-center shrink-0" style="background-color: {{ $ct->color }}1a; color: {{ $ct->color }};">
                                <i class="{{ $icon }} text-[17px]"></i>
                            </span>
                            <p class="font-heading font-medium text-text text-[18px] truncate">{{ $ct->name }}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            @if ($ct->id === $topTypeId)
                                <span class="flex items-center gap-1.5 rounded-[35px] bg-primary/15 px-2.5 py-[5px]">
                                    <i class="ri-star-fill text-primary text-[13px]"></i>
                                    <span class="font-sans text-primary text-[13px]">Top Today</span>
                                </span>
                            @endif
                            @if ($locked)
                                <span class="flex items-center gap-1.5 rounded-[35px] bg-muted/15 px-2.5 py-[5px]">
                                    <i class="ri-lock-2-line text-muted text-[13px]"></i>
                                    <span class="font-sans text-muted text-[13px]">Locked</span>
                                </span>
                            @else
                                <span class="flex items-center gap-1.5 rounded-[35px] bg-success/15 px-2.5 py-[5px]">
                                    <span class="size-2 rounded-full bg-success animate-pulse"></span>
                                    <span class="font-sans text-success text-[13px]">Live</span>
                                </span>
                            @endif
                        </div>
                    </div>
                    <p class="font-sans text-muted text-[14px]">{{ $ct->description }}</p>
                </div>

                @php $potential = round($card['earnings'] * $card['sharePct'] / 100, 8); @endphp

                <div class="grid grid-cols-2 gap-[15px]">
                    <div class="bg-background rounded-[15px] p-4 flex flex-col gap-2">
                        <p class="font-sans text-muted text-[13px]">Call Volume</p>
                        <p class="font-heading font-medium text-text text-[18px] leading-none">{{ rtrim(rtrim(number_format($card['minutes'], 1), '0'), '.') }} mins</p>
                        <p class="font-sans text-muted text-[13px]">{{ $card['calls'] }} calls today</p>
                    </div>
                    <div class="bg-background rounded-[15px] p-4 flex flex-col gap-2">
                        <p class="font-sans text-muted text-[13px]">Call Revenue</p>
                        <x-money :usd="$card['earnings']" class="font-heading font-medium text-text text-[18px] leading-none" />
                        <p class="font-sans text-muted text-[13px]">from all numbers</p>
                    </div>
                    <div class="bg-background rounded-[15px] p-4 flex flex-col gap-2">
                        <p class="font-sans text-muted text-[13px]">Your Share</p>
                        <p class="font-heading font-medium text-text text-[18px] leading-none">{{ rtrim(rtrim(number_format($card['sharePct'], 1), '0'), '.') }}%</p>
                        <p class="font-sans text-muted text-[13px]">of revenue</p>
                    </div>
                    <div class="bg-background rounded-[15px] p-4 flex flex-col gap-2">
                        <p class="font-sans text-[13px] {{ $locked ? 'text-muted' : 'text-primary' }}">Your Earnings</p>
                        <x-money :usd="$potential" class="font-heading font-semibold text-[18px] leading-none {{ $locked ? 'text-muted' : 'text-primary' }}" />
                        <p class="font-sans text-muted text-[13px]">{{ $locked ? 'locked' : 'claimable today' }}</p>
                    </div>
                </div>

                @if ($card['showPurchasePrompt'])
                    <div class="rounded-[14px] bg-secondary/15 px-3.5 py-2.5 flex items-center justify-between gap-3">
                        <p class="font-sans text-primary text-[13px] leading-snug">{{ $remaining < 1 ? 'Share-term minutes are exhausted.' : 'Below the '.$card['promptThreshold'].'-minute share-plan prompt threshold.' }}</p>
                        @if (config('settings.minutes.enabled'))
                            <a href="{{ route('plans') }}" wire:navigate class="shrink-0 font-sans font-semibold text-primary text-[13px]">Add share minutes</a>
                        @endif
                    </div>
                @endif

                @if ($locked)
                    <div class="flex flex-col gap-2 rounded-[15px] bg-background px-4 py-3">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex flex-col gap-0.5">
                                <span class="font-sans text-muted text-[13px]">Potential earnings</span>
                                <x-money :usd="$potential" class="font-heading font-semibold text-muted text-[18px] leading-none" />
                            </div>
                            <a href="{{ route('shareholder.show', $ct) }}" wire:navigate class="inline-flex h-[40px] items-center gap-1.5 rounded-full bg-primary text-surface px-3.5 font-sans text-[13px]">Breakdown <i class="ri-arrow-right-up-line"></i></a>
                        </div>
                        @if ($card['canBuyShare'])
                            <form method="POST" action="{{ route('share-access.buy.start') }}" class="flex items-center justify-between gap-3 border-t border-muted/10 pt-2">
                                @csrf
                                <input type="hidden" name="call_type_id" value="{{ $ct->id }}">
                                <span class="font-sans text-muted text-[13px]">{{ $ct->name }} share access</span>
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-full bg-secondary px-3.5 py-2 font-sans text-[13px] font-semibold text-primary">Buy <x-money :usd="$card['sharePrice']" :abbreviate="false" /></button>
                            </form>
                        @elseif ($card['activeOnAnotherShareType'])
                            <p class="border-t border-muted/10 pt-2 font-sans text-muted text-[12px]">Your active share term covers {{ $shareTypeName }} only and does not stack with another share product.</p>
                        @elseif (! $card['shareConfigured'])
                            <p class="border-t border-muted/10 pt-2 font-sans text-muted text-[12px]">This share product is not active for activation yet.</p>
                        @endif
                    </div>
                @else
                    <a href="{{ route('shareholder.show', $ct) }}" wire:navigate class="flex items-center justify-between rounded-[15px] bg-secondary/15 px-4 py-3">
                        <div class="flex flex-col gap-0.5">
                            <span class="font-sans text-muted text-[13px]">Claimable now</span>
                            <x-money :usd="$card['claimable']" class="font-heading font-semibold text-primary text-[18px] leading-none" />
                        </div>
                        <span class="inline-flex h-[44px] items-center gap-1.5 rounded-[30px] bg-primary text-surface px-4 font-sans text-[14px]">
                            @if ($card['unclaimed'] > 0)
                                Claim <span class="rounded-full bg-surface/20 px-1.5 text-[13px]">{{ $card['unclaimed'] }}</span>
                            @else
                                Breakdown
                            @endif
                            <i class="ri-arrow-right-up-line text-[14px]"></i>
                        </span>
                    </a>
                @endif
            </div>
            @endforeach
            </div>
        @endif
    </div>

    <x-app.bottom-nav />
</div>