<div class="min-h-screen bg-background">
    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-12 flex flex-col gap-[22px]">

        <x-app.app-bar :title="'Earn Now, Pay Later'" />

        @if (! $eligible)
            <x-app.feature-locked class="mt-2" :title="config('settings.paylater.disabled_title')" :message="config('settings.paylater.disabled_message')" />

        @elseif ($step === 'list')
            <div class="flex flex-col gap-2.5">
                <p class="font-sans text-muted text-[14px] leading-snug lg:max-w-[620px]">Access additional call minutes instantly and start earning right away. Repay before the due date with a token — <span class="text-primary">your earnings remain yours</span> once settled.</p>
            </div>

            @if ($subsetLimit)
                <div class="flex items-center gap-2 rounded-[14px] bg-secondary/20 px-4 py-2.5">
                    <i class="ri-flashlight-fill text-primary text-[16px]"></i>
                    <p class="font-sans text-text text-[13px]">Available to the first <span class="font-semibold">{{ number_format($subsetLimit) }}</span> users · Limited Slots</p>
                </div>
            @endif

            <div class="flex flex-col gap-[22px] lg:grid lg:grid-cols-3 lg:gap-6 lg:items-start">

                <div class="lg:col-span-2 flex flex-col gap-4">
                    @foreach ($plans as $p)
                        <div class="bg-surface rounded-[20px] p-5 flex flex-col gap-4 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-heading font-medium text-text text-[18px] leading-tight">{{ $p['name'] }}
                                        @if ($p['is_featured'])<span class="ml-1.5 align-middle rounded-full bg-secondary/40 text-primary px-1.5 py-0.5 font-sans text-[9px]">Best Value</span>@endif
                                    </p>
                                    <p class="font-sans text-muted text-[12px] mt-1 flex items-center gap-1.5"><i class="ri-coins-line"></i> Earn now, repay in {{ $p['days'] }} days</p>
                                </div>
                                <button type="button" wire:click="apply('{{ $p['id'] }}')"
                                        class="shrink-0 inline-flex items-center h-[40px] rounded-[30px] bg-primary text-surface px-6 font-sans text-[13px] hover:opacity-95 transition">Apply</button>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-3 border-t border-muted/15 pt-4">
                                <div class="flex flex-col gap-0.5">
                                    <p class="font-sans text-muted text-[11px]">Price</p>
                                    <x-money :usd="$p['price']" :decimals="0" class="font-heading font-medium text-text text-[15px] block" />
                                </div>
                                <div class="flex flex-col gap-0.5">
                                    <p class="font-sans text-muted text-[11px]">Min. earning rate</p>
                                    <p class="font-heading font-medium text-text text-[15px]"><x-money :usd="$p['min_rate']" />/min</p>
                                </div>
                                <div class="flex flex-col gap-0.5">
                                    <p class="font-sans text-muted text-[11px]">Repayment</p>
                                    <p class="font-heading font-medium text-text text-[15px]">{{ $p['days'] }} days</p>
                                </div>
                                <div class="flex flex-col gap-0.5">
                                    <p class="font-sans text-muted text-[11px]">Potential income</p>
                                    <p class="font-sans text-success text-[14px] font-medium flex items-center gap-1"><i class="ri-arrow-right-up-line"></i> +{{ rtrim(rtrim(number_format($p['trend'], 1), '0'), '.') }}%</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="lg:col-span-1 flex flex-col gap-3 {{ $hasOutstanding ? 'order-first lg:order-none' : '' }}">
                    <p class="font-heading font-medium text-text text-[15px]">Your Pay Later</p>

                    @forelse ($credits->where('status.value', 'active') as $credit)
                        @php
                            $total = max(1, $credit->created_at->diffInSeconds($credit->due_date->copy()->endOfDay()));
                            $elapsed = min($total, $credit->created_at->diffInSeconds(now()));
                            $pct = min(100, max(2, round($elapsed / $total * 100)));
                            $daysLeft = max(0, now()->startOfDay()->diffInDays($credit->due_date, false));
                            $overdue = $credit->due_date->isBefore(today()); // matches forfeitOverdue() (< today)
                        @endphp
                        <div class="bg-surface rounded-[16px] border {{ $overdue ? 'border-danger/30' : 'border-warning/30' }} p-4 flex flex-col gap-2.5 shadow-sm">
                            <div class="flex items-center justify-between gap-2">
                                <p class="font-sans text-text text-[13px]">{{ $credit->plan_name }}</p>
                                <p class="font-sans text-muted text-[12px]">Due {{ $credit->due_date->format('j M Y, g:i A') }}</p>
                            </div>
                            <div class="h-2.5 rounded-full bg-background overflow-hidden">
                                <div class="h-full rounded-full {{ $overdue ? 'bg-danger' : 'bg-warning' }}" style="width: {{ $pct }}%"></div>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <p class="font-sans text-muted text-[12px]">{{ $overdue ? 'Overdue — repay now' : $daysLeft.' day'.($daysLeft === 1 ? '' : 's').' left' }}</p>
                                <button type="button" wire:click="startRepay('{{ $credit->id }}')" class="font-sans text-primary text-[12px] font-semibold">Repay</button>
                            </div>
                        </div>
                    @empty
                        <div class="bg-surface rounded-[16px] px-4 py-6 flex flex-col items-center gap-1.5 text-center shadow-sm">
                            <i class="ri-checkbox-circle-line text-success text-[24px]"></i>
                            <p class="font-sans text-muted text-[13px]">No active Pay Later.</p>
                            <p class="font-sans text-muted/70 text-[11px]">Apply for a plan to get minutes on credit.</p>
                        </div>
                    @endforelse

                    <div class="bg-secondary/15 rounded-[16px] px-4 py-3.5 flex flex-col gap-2">
                        <p class="font-heading font-medium text-text text-[13px] flex items-center gap-1.5"><i class="ri-information-line text-primary"></i> How it works</p>
                        <p class="font-sans text-muted text-[12px] leading-snug">Get extra minutes instantly, start earning, then settle with a token before the due date. Miss it and the bonus earnings are forfeited.</p>
                    </div>
                </div>
            </div>

        @elseif ($step === 'confirm' && $selectedPlan)
            <p class="font-heading font-medium text-text text-[22px] leading-tight">Confirm your selection</p>

            <div class="flex flex-col gap-[22px] lg:grid lg:grid-cols-2 lg:gap-6 lg:items-start">

                <div class="bg-surface rounded-[20px] p-5 flex flex-col gap-5 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex flex-col gap-1">
                            <p class="font-sans text-muted text-[12px]">Selected package</p>
                            <p class="font-heading font-medium text-text text-[18px]">{{ $selectedPlan['name'] }}</p>
                        </div>
                        <span class="rounded-full bg-secondary/30 text-primary px-2.5 py-1 font-sans text-[11px]">+{{ rtrim(rtrim(number_format($selectedPlan['trend'], 1), '0'), '.') }}%</span>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-[14px] border border-muted/20 px-4 py-3">
                            <p class="font-sans text-muted text-[11px]">Price</p>
                            <x-money :usd="$selectedPlan['price']" :decimals="0" class="font-heading font-medium text-text text-[15px] block" />
                        </div>
                        <div class="rounded-[14px] border border-muted/20 px-4 py-3">
                            <p class="font-sans text-muted text-[11px]">Min. earning rate</p>
                            <p class="font-heading font-medium text-text text-[15px]"><x-money :usd="$selectedPlan['min_rate']" />/min</p>
                        </div>
                        <div class="rounded-[14px] border border-muted/20 px-4 py-3">
                            <p class="font-sans text-muted text-[11px]">Repayment duration</p>
                            <p class="font-heading font-medium text-text text-[15px]">{{ $selectedPlan['days'] }} days</p>
                        </div>
                        <div class="rounded-[14px] border border-muted/20 px-4 py-3">
                            <p class="font-sans text-muted text-[11px]">Extra phone numbers</p>
                            <p class="font-heading font-medium text-text text-[15px]">{{ $selectedPlan['extra_numbers'] }}</p>
                        </div>
                    </div>
                    <div class="rounded-[14px] border border-muted/20 px-4 py-3">
                        <p class="font-sans text-muted text-[11px]">Potential income</p>
                        <x-money :usd="$selectedPlan['potential']" :decimals="0" class="font-heading font-medium text-text text-[15px] block" />
                    </div>
                </div>

                <div class="flex flex-col gap-4">
                    <div class="rounded-[14px] bg-warning/15 border-[0.5px] border-warning/60 px-4 py-3 flex gap-2.5">
                        <i class="ri-error-warning-line text-warning text-[16px] shrink-0 mt-0.5"></i>
                        <p class="font-sans text-muted text-[12px] leading-snug"><span class="font-semibold text-text">Important.</span> You are about to access additional minutes on credit. Earnings from these minutes go toward repayment.</p>
                    </div>

                    <div class="flex items-center gap-2.5">
                        <button type="button" wire:click="backToList" class="flex-1 h-[52px] rounded-[30px] bg-danger/10 text-danger font-sans font-semibold text-[15px]">Back</button>
                        <button type="button" wire:click="toTerms" class="flex-1 h-[52px] rounded-[30px] bg-primary text-surface font-sans font-semibold text-[15px]">Confirm</button>
                    </div>
                </div>
            </div>

        @elseif ($step === 'terms')
            <p class="font-heading font-medium text-text text-[22px] leading-tight">Terms &amp; agreement</p>

            <div class="flex flex-col gap-[22px] lg:grid lg:grid-cols-2 lg:gap-6 lg:items-start">

                <div class="flex flex-col gap-4">
                    <div class="flex items-start gap-2.5">
                        <span class="size-10 rounded-full bg-primary/10 flex items-center justify-center text-primary shrink-0"><i class="ri-shield-check-line text-[20px]"></i></span>
                        <div>
                            <p class="font-heading font-medium text-text text-[16px]">Review carefully</p>
                            <p class="font-sans text-muted text-[13px]">Please accept the terms before proceeding.</p>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2.5">
                        @foreach ([
                            'Repayment must be completed within the stated duration.',
                            'Failure to repay forfeits the earnings generated from these minutes.',
                            'Earnings may vary based on your call activity.',
                        ] as $term)
                            <div class="rounded-[14px] bg-surface px-4 py-3 flex items-start gap-2 shadow-sm">
                                <span class="size-1.5 rounded-full bg-muted mt-2 shrink-0"></span>
                                <p class="font-sans text-muted text-[13px] leading-snug">{{ $term }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex flex-col gap-4">
                    <div class="bg-surface rounded-[16px] px-4 py-4 shadow-sm">
                        <label class="flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" wire:model.live="agreed" class="size-5 rounded-[6px] border-muted/40 text-primary focus:ring-primary">
                            <span class="font-sans text-text text-[14px]">I agree to the <a href="{{ route('terms') }}" target="_blank" rel="noopener" class="text-primary font-medium underline underline-offset-2" @click.stop>terms and conditions</a></span>
                        </label>
                    </div>

                    <div class="flex items-center gap-2.5">
                        <button type="button" wire:click="apply('{{ $planId }}')" class="flex-1 h-[52px] rounded-[30px] bg-surface border border-muted/30 text-text font-sans text-[15px]">Back</button>
                        <button type="button" wire:click="acceptTerms" @disabled(! $agreed)
                                class="flex-1 h-[52px] rounded-[30px] bg-primary text-surface font-sans font-semibold text-[15px] disabled:opacity-50">Accept &amp; proceed</button>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if ($step === 'pin')
        <div data-lock-scroll class="fixed inset-0 z-[90] flex items-end justify-center sm:items-center sm:p-4" x-data="{ shown: false }" x-init="$nextTick(() => shown = true)">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" x-show="shown" x-transition.opacity @click="$wire.toTerms()"></div>
            <div class="relative w-full sm:max-w-[400px] bg-background rounded-t-[26px] sm:rounded-[26px] px-6 pt-8 pb-8 flex flex-col items-center gap-4"
                 x-show="shown" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">
                <p class="font-heading font-medium text-text text-[18px]">Transaction PIN</p>
                <x-app.pin-input target="pin" :autofocus="true" />
                @error('pin')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                <a href="{{ route('reset-pin') }}" wire:navigate class="font-sans text-[12px] text-muted">Forgot PIN? <span class="text-primary font-semibold">Reset it</span></a>
                <div class="flex items-center gap-2.5 w-full mt-2">
                    <button type="button" wire:click="toTerms" class="flex-1 h-[50px] rounded-[30px] bg-surface border border-muted/30 text-text font-sans text-[14px]">Cancel</button>
                    <button type="button" wire:click="confirmPin" wire:loading.attr="disabled" wire:target="confirmPin"
                            class="flex-1 h-[50px] rounded-[30px] bg-primary text-surface font-sans font-semibold text-[14px] flex items-center justify-center gap-2 disabled:opacity-60">
                        <span wire:loading.remove wire:target="confirmPin">Confirm</span>
                        <span wire:loading wire:target="confirmPin"><i class="ri-loader-4-line animate-spin"></i></span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($step === 'done' && $activated)
        <div data-lock-scroll class="fixed inset-0 z-[90] flex items-end justify-center sm:items-center sm:p-4" x-data>
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
            <div class="relative w-full sm:max-w-[360px] bg-background rounded-t-[26px] sm:rounded-[26px] px-6 pt-8 pb-8 flex flex-col items-center text-center gap-3">
                <span class="size-16 rounded-full bg-success/15 flex items-center justify-center text-success"><i class="ri-checkbox-circle-fill text-[34px]"></i></span>
                <p class="font-heading font-medium text-text text-[20px]">Minutes activated</p>
                <p class="font-sans text-muted text-[13px] max-w-[280px]">Your extra minutes are now available. Start earning immediately.</p>
                <button type="button" wire:click="finish" class="mt-2 h-[48px] px-8 rounded-[30px] bg-primary text-surface font-sans font-semibold text-[14px]">Done</button>
            </div>
        </div>
    @endif

    @if ($repayingId)
        <div data-lock-scroll class="fixed inset-0 z-[90] flex items-end justify-center sm:items-center sm:p-4" x-data="{ shown: false }" x-init="$nextTick(() => shown = true)">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" x-show="shown" x-transition.opacity @click="$wire.cancelRepay()"></div>
            <div class="relative w-full sm:max-w-[400px] bg-background rounded-t-[26px] sm:rounded-[26px] px-6 pt-6 pb-8 flex flex-col gap-4"
                 x-show="shown" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">
                <button type="button" wire:click="cancelRepay" class="absolute right-5 top-5 size-9 rounded-full bg-surface shadow-sm flex items-center justify-center text-text" aria-label="Close"><i class="ri-close-line text-[20px]"></i></button>
                <div class="flex flex-col gap-1 pr-8">
                    <p class="font-heading font-medium text-text text-[19px]">Repay your credit</p>
                    <p class="font-sans text-muted text-[13px]">Enter a minutes token to settle this Pay Later.</p>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-text text-[13px] font-medium">Minutes token</label>
                    <input type="text" wire:model="repayToken" placeholder="Enter token code" wire:keydown.enter="repay"
                           class="w-full h-[48px] rounded-[14px] bg-surface border border-muted/20 px-4 outline-none font-sans text-[14px] text-text uppercase placeholder:text-muted placeholder:normal-case focus:border-primary focus:ring-1 focus:ring-primary">
                    @error('repayToken')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                </div>
                <button type="button" wire:click="repay" wire:loading.attr="disabled" wire:target="repay"
                        class="h-[52px] rounded-[30px] bg-primary text-surface font-sans font-semibold text-[15px] flex items-center justify-center gap-2 disabled:opacity-60">
                    <span wire:loading.remove wire:target="repay">Repay now</span>
                    <span wire:loading wire:target="repay"><i class="ri-loader-4-line animate-spin"></i></span>
                </button>
            </div>
        </div>
    @endif

    <x-app.bottom-nav />
</div>
