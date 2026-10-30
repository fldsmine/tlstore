<div class="min-h-screen bg-background">
    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-12 flex flex-col gap-[22px]">

        <x-app.app-bar :title="'Rewards'" />

        @if (! $enabled)
            <x-app.feature-locked class="mt-2" :title="config('settings.rewards.disabled_title')" :message="config('settings.rewards.disabled_message')" />
        @else
            <p class="font-sans text-muted text-[14px] -mt-1">Set a daily Connect Bonus target, hit it, and earn Nextel Smart Reward.</p>

            <div class="bg-surface rounded-[20px] p-5 flex items-center justify-between shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                <div class="flex flex-col gap-1.5 items-center flex-1 min-w-0">
                    <x-money :usd="$lifetimeEarned" class="font-heading font-medium text-success text-[18px] leading-none" />
                    <p class="font-sans text-muted text-[11px] text-center">Total Earned</p>
                </div>
                <div class="w-px h-10 bg-[#e7e4dc] shrink-0"></div>
                <div class="flex flex-col gap-1.5 items-center flex-1 min-w-0">
                    <x-money :usd="$unclaimedTotal" class="font-heading font-medium text-[#d79937] text-[18px] leading-none" />
                    <p class="font-sans text-muted text-[11px] text-center">Unclaimed</p>
                </div>
                <div class="w-px h-10 bg-[#e7e4dc] shrink-0"></div>
                <div class="flex flex-col gap-1.5 items-center flex-1 min-w-0">
                    <p class="font-heading font-medium text-text text-[18px] leading-none">&#128293; {{ $streak }}</p>
                    <p class="font-sans text-muted text-[11px] text-center">Streak</p>
                </div>
            </div>

            <div class="flex flex-col gap-[22px] lg:grid lg:grid-cols-3 lg:gap-6 lg:items-start">

                <div class="lg:col-span-2 flex flex-col gap-[22px]">

                    <div class="relative overflow-hidden bg-surface border border-[#f8f8f8] rounded-[20px] flex items-center gap-[15px] px-4 py-5 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                        <div class="pointer-events-none absolute -top-[37px] left-[6px] w-[363px] h-[200px] opacity-60"
                             style="background: radial-gradient(circle at 35% 50%, rgba(220,235,106,0.55), rgba(220,235,106,0) 65%);"></div>

                        <div class="relative shrink-0 size-[100px] rounded-full"
                             style="background: conic-gradient(#dceb6a 0% {{ $pct }}%, #18443e {{ $pct }}% 100%);">
                            <div class="absolute inset-[11px] rounded-full bg-surface flex flex-col items-center justify-center text-center leading-none">
                                <span class="font-sans text-text text-[20px]">{{ $pct }}%</span>
                                <span class="font-sans text-muted text-[10px] mt-1">of target</span>
                            </div>
                        </div>

                        <div class="relative flex-1 min-w-0 flex flex-col gap-2 justify-center">
                            <p class="font-sans font-semibold text-text text-[16px] leading-none">Daily Target</p>
                            @if ($hasTarget)
                                <p class="leading-none">
                                    <x-money :usd="$progress" class="font-sans font-semibold text-primary text-[18px]" />
                                    <span class="font-sans font-semibold text-muted text-[12px]"> / {{ money($targetAmount) }}</span>
                                </p>
                                <div class="h-[10px] rounded-[46px] bg-primary overflow-hidden">
                                    <div class="h-full bg-secondary" style="width: {{ $pct }}%"></div>
                                </div>
                                @if ($locked)
                                    <p class="font-sans text-success text-[12px]">&#127881; Target hit — your reward is unlocked!</p>
                                @else
                                    <p class="font-sans text-muted text-[12px]">{{ money($remaining) }} more to unlock your reward</p>
                                @endif
                            @else
                                <p class="font-sans text-muted text-[13px] leading-snug">No target set for today. Pick one below to start earning rewards from your referral bonus.</p>
                            @endif
                        </div>
                    </div>

                    {{-- Target is set once a day, then locked. --}}
                    <div class="flex flex-col gap-[15px]">
                        <div class="flex items-center justify-between">
                            <p class="font-sans font-semibold text-text text-[16px]">{{ $targetSet ? 'Today’s Target' : 'Set Your Target' }}</p>
                            @unless ($targetSet)
                                <p class="font-sans text-muted text-[12px] text-right leading-tight">Min. ${{ number_format($minTarget, 2) }}@if ($maxTarget > 0) · Max ${{ number_format($maxTarget, 2) }}@endif
                                    @if (\App\Support\DisplayCurrency::isLocal())
                                        <span class="block opacity-80">{{ money($minTarget) }}@if ($maxTarget > 0) · {{ money($maxTarget) }}@endif</span>
                                    @endif
                                </p>
                            @endunless
                        </div>

                        @if (! $targetSet)
                            {{-- Targets are stored — and therefore typed — in USD, while every figure around them renders
                                 in the reader's currency, so the picker carries its own $/USD labels plus the converted
                                 equivalent; otherwise there's no telling which unit the box wants. --}}
                            {{-- Deferred @entangle: presets/input update client-side; saveCustom() sanitizes + validates server-side. --}}
                            <div x-data="{ ct: @entangle('customTarget'), rate: {{ \App\Support\DisplayCurrency::convert(1) }}, symbol: @js(\App\Support\DisplayCurrency::symbol()) }" class="flex flex-col gap-3"
                                 x-effect="ct = String(ct).replace(/[^0-9]/g, '')">
                            @if (! empty($presets))
                                <div class="flex items-center justify-between gap-2.5">
                                    @foreach ($presets as $preset)
                                        <button type="button" @click="ct = '{{ $preset }}'"
                                                class="flex-1 min-w-0 h-[46px] rounded-[30px] font-sans transition flex flex-col items-center justify-center leading-none"
                                                :class="Number(ct) === {{ $preset }} ? 'bg-primary text-surface' : 'bg-muted/15 text-text hover:bg-muted/25'">
                                            <span class="text-[14px]">${{ number_format($preset, 2) }}</span>
                                            @if (\App\Support\DisplayCurrency::isLocal())
                                                <span class="text-[10px] opacity-75 mt-1">{{ money($preset, 0) }}</span>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            <div class="flex items-end gap-2.5">
                                <label class="flex-1 min-w-0 flex flex-col gap-1.5">
                                    <span class="font-sans text-text text-[13px] font-medium">Custom target</span>
                                    <span class="w-full h-[48px] rounded-[14px] bg-surface border border-muted/20 px-4 flex items-center gap-1.5 focus-within:border-primary focus-within:ring-1 focus-within:ring-primary">
                                        <span class="font-sans text-muted text-[14px] shrink-0">$</span>
                                        <input type="text" inputmode="numeric" x-model="ct" placeholder="{{ $presets[0] ?? (int) ceil($minTarget) }}" @keydown.enter="$wire.saveCustom()"
                                               class="flex-1 min-w-0 bg-transparent border-0 p-0 outline-none font-sans text-[14px] text-text placeholder:text-muted">
                                        <span class="font-sans text-muted text-[12px] shrink-0">USD</span>
                                    </span>
                                </label>
                                <button type="button" wire:click="saveCustom" wire:loading.attr="disabled" wire:target="saveCustom"
                                        class="h-[48px] shrink-0 bg-primary text-surface rounded-[30px] px-7 font-sans font-semibold text-[14px] hover:opacity-95 transition disabled:opacity-60">Save</button>
                            </div>

                            @if (\App\Support\DisplayCurrency::isLocal())
                                <p class="font-sans text-muted text-[12px]" x-show="Number(ct) > 0" x-cloak
                                   x-text="'≈ ' + symbol + (Number(ct) * rate).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></p>
                            @endif
                            </div>
                            <p class="font-sans text-muted text-[12px]">Pick or type a target, then <span class="text-text font-medium">Save</span> — it&rsquo;s set once for today and can&rsquo;t be changed.</p>
                        @elseif ($locked)
                            <div class="rounded-[16px] bg-success/12 px-4 py-3.5 flex items-center gap-2.5">
                                <i class="ri-checkbox-circle-fill text-success text-[20px] shrink-0"></i>
                                <p class="font-sans text-text text-[13px]">You&rsquo;ve hit today&rsquo;s {{ money($targetAmount) }} target. Claim your reward below — set a fresh one tomorrow.</p>
                            </div>
                        @else
                            <div class="rounded-[16px] bg-secondary/15 px-4 py-3.5 flex items-center gap-2.5">
                                <i class="ri-lock-2-line text-primary text-[20px] shrink-0"></i>
                                <p class="font-sans text-text text-[13px]">Today&rsquo;s target is {{ money($targetAmount) }} (locked). Keep referring to hit it before the day ends.</p>
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-col gap-[15px]">
                        <div class="flex items-center justify-between">
                            <p class="font-heading font-medium text-text text-[18px]">Unclaimed Rewards</p>
                            @if ($unclaimed->count() > 1)
                                <button type="button" wire:click="claimAll" class="font-sans text-primary text-[13px] font-semibold">Claim all</button>
                            @endif
                        </div>

                        @forelse ($unclaimed as $reward)
                            <div class="bg-surface rounded-[50px] flex items-center gap-2.5 pl-2 pr-2.5 py-2.5 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                                <div class="size-[45px] rounded-full bg-[#d79937]/20 flex items-center justify-center shrink-0">
                                    <span class="text-[17px]">&#127942;</span>
                                </div>
                                <div class="flex-1 min-w-0 flex flex-col">
                                    <x-money :usd="$reward->reward_amount" class="font-sans text-text text-[16px]" />
                                    <p class="font-sans text-muted text-[12px]">{{ $reward->date->format('j M Y, g:i A') }} · target {{ money($reward->target_amount) }}</p>
                                </div>
                                <button type="button" wire:click="claim('{{ $reward->id }}')" wire:loading.attr="disabled"
                                        class="bg-success text-surface rounded-[35px] px-4 py-2 font-sans text-[11px] shrink-0 disabled:opacity-60">Claim</button>
                            </div>
                        @empty
                            <div class="bg-surface rounded-[16px] px-4 py-6 flex flex-col items-center gap-1.5 text-center shadow-sm">
                                <i class="ri-trophy-line text-muted text-[24px]"></i>
                                <p class="font-sans text-muted text-[13px]">No rewards waiting to be claimed.</p>
                                <p class="font-sans text-muted/70 text-[11px]">Hit your daily target to unlock one.</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="lg:col-span-1 flex flex-col gap-[22px]">

                    <div class="flex flex-col gap-[15px]">
                        <p class="font-heading font-medium text-text text-[18px]">History</p>

                        @forelse ($history as $entry)
                            <div class="bg-surface rounded-[50px] flex items-center gap-2.5 px-4 py-2.5 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                                <div class="flex-1 min-w-0 flex flex-col">
                                    <x-money :usd="$entry['claimed'] ? $entry['amount'] : 0" class="font-sans text-text text-[16px]" />
                                    <p class="font-sans text-muted text-[12px]">{{ $entry['date'] }} · target {{ money($entry['target']) }}</p>
                                </div>
                                <p class="font-sans font-semibold text-[13px] {{ $entry['claimed'] ? 'text-success' : 'text-danger' }}">{{ $entry['claimed'] ? 'Claimed' : 'Missed target' }}</p>
                            </div>
                        @empty
                            <div class="bg-surface rounded-[16px] px-4 py-6 flex flex-col items-center gap-1.5 text-center shadow-sm">
                                <i class="ri-history-line text-muted text-[24px]"></i>
                                <p class="font-sans text-muted text-[13px]">No history yet.</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif
    </div>

    <x-app.bottom-nav />
</div>
