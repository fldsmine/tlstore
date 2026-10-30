<div class="min-h-screen bg-background">
    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-12 flex flex-col gap-[22px]">

        <x-app.app-bar :title="'Shares'" />

        @unless ($enabled)
            <x-app.feature-locked :title="config('settings.minutes.disabled_title')" :message="config('settings.minutes.disabled_message')" />
        @else
        <div class="relative overflow-hidden bg-primary text-surface rounded-[20px] p-5 flex flex-col gap-3 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
            <div class="pointer-events-none absolute -right-10 -top-10 w-[220px] h-[220px] rounded-full bg-secondary/20 blur-2xl"></div>
            @if ($shareSubscription)
                <div class="relative flex items-center justify-between gap-4">
                    <div class="flex flex-col gap-1.5">
                        <p class="font-sans text-surface/70 text-[12px]">Share minutes remaining this term</p>
                        <p class="font-heading font-semibold text-[30px] leading-none">{{ rtrim(rtrim(number_format($shareRemaining, 1), '0'), '.') }} <span class="text-[16px] font-sans font-normal text-surface/70">of {{ rtrim(rtrim(number_format($shareAllocated, 1), '0'), '.') }}</span></p>
                    </div>
                    <span class="text-right font-sans text-surface/70 text-[12px]">Term ends<br><span class="text-surface">{{ $shareSubscription->expires_at?->format('M j, Y') }}</span></span>
                </div>
                <p class="relative font-sans text-surface/70 text-[11px]">Available to claim now: {{ rtrim(rtrim(number_format($shareAvailable, 1), '0'), '.') }} minutes. Monthly quota accrues daily; unused accrued minutes roll forward.</p>
            @else
                <div class="relative flex flex-col gap-1.5">
                    <p class="font-heading font-semibold text-[20px]">No active share term</p>
                    <p class="font-sans text-surface/70 text-[12px]">Share-minute top-ups attach to a current share term and do not carry into upgrades or renewals.</p>
                </div>
                @if ($shareRenewalAvailable && $directPackagePay)
                    <form method="POST" action="{{ route('plans.buy.start') }}" class="relative self-start">
                        @csrf
                        <input type="hidden" name="package_id" value="{{ auth()->user()->package_id }}">
                        <input type="hidden" name="mode" value="renew">
                        <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-secondary text-primary px-4 py-2 font-sans font-semibold text-[13px]">Start or renew share term <i class="ri-arrow-right-line"></i></button>
                    </form>
                @elseif (! $shareRenewalAvailable)
                    <p class="relative font-sans text-surface/65 text-[11px]">This package needs an explicitly configured share quota and daily earning cap.</p>
                @endif
                @if ($shareOnly || ! $shareRenewalAvailable || ! $directPackagePay)
                    <a href="{{ route('shareholder') }}" wire:navigate class="relative self-start inline-flex items-center gap-1.5 rounded-full bg-secondary text-primary px-4 py-2 font-sans text-[12px] font-semibold">Buy share access <i class="ri-arrow-right-line"></i></a>
                @endif
            @endif
            @if ($packageName)
                <p class="relative font-sans text-surface/65 text-[11px]">eSIM daily call minutes remain a separate entitlement: {{ rtrim(rtrim(number_format($totalMinutes, 1), '0'), '.') }} mins/day.</p>
            @else
                <p class="relative font-sans text-surface/65 text-[11px]">This account has share access only; no eSIM minutes or eSIM activation are included.</p>
            @endif
        </div>

        <div class="flex flex-col gap-[22px] lg:grid lg:grid-cols-3 lg:gap-6 lg:items-start">

            <div class="lg:col-span-2 bg-surface rounded-[20px] p-5 flex flex-col gap-5 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                @if ($shareOnly && ! $shareSubscription)
                    <div class="flex flex-col items-center text-center gap-3 py-8">
                        <span class="size-14 rounded-full bg-primary/10 flex items-center justify-center text-primary"><i class="ri-pie-chart-line text-[28px]"></i></span>
                        <p class="font-heading font-medium text-text text-[17px]">Activate share access first</p>
                        <p class="font-sans text-muted text-[13px] max-w-[300px]">Minute top-ups attach to an active share term. Buy a CallType share product or redeem its share activation code first.</p>
                        <a href="{{ route('shareholder') }}" wire:navigate class="h-[44px] inline-flex items-center gap-2 rounded-full bg-primary px-5 font-sans text-[13px] font-semibold text-white">Go to Shareholder Portal <i class="ri-arrow-right-line"></i></a>
                    </div>
                @elseif ($done)
                    <div class="flex flex-col items-center text-center gap-2.5 py-6">
                        <span class="size-14 rounded-full bg-success/15 flex items-center justify-center text-success"><i class="ri-checkbox-circle-fill text-[28px]"></i></span>
                        <p class="font-heading font-medium text-text text-[17px]">Shares added!</p>
                        <p class="font-sans text-muted text-[13px] max-w-[260px]">{{ $redeemedPlan }} is now active on your account.</p>
                        <button type="button" wire:click="$set('done', false)" class="mt-1 h-[44px] px-5 rounded-[30px] bg-primary text-surface font-sans text-[14px]">Redeem another</button>
                    </div>
                @else
                    <div class="flex flex-col gap-1">
                        <p class="font-heading font-medium text-text text-[17px]">{{ $shareOnly ? 'Top up share minutes' : 'Top up eSIM minutes or share minutes' }}</p>
                        <p class="font-sans text-muted text-[13px]">{{ $shareOnly ? 'Choose a share-only plan and pay directly or redeem its extra-minutes code. Top-ups are applied after payment verification.' : 'Choose a plan and redeem its token, or pay directly for share minutes on an active share term. Direct top-ups are applied only after payment verification.' }}</p>
                    </div>

                    @if ($plans->isNotEmpty())
                    <div class="flex flex-col gap-2.5">
                        <label class="font-sans text-[13px] text-text font-medium">Select plan</label>
                        @foreach ($plans as $p)
                            @php $selected = $planId === $p->id; @endphp
                            <button type="button" wire:click="$set('planId', '{{ $p->id }}')"
                                    class="w-full flex items-center gap-3 rounded-[16px] border p-4 text-left transition {{ $selected ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-muted/20 bg-background hover:border-muted/40' }}">
                                <span class="size-[20px] rounded-full border-2 flex items-center justify-center shrink-0 {{ $selected ? 'border-primary' : 'border-muted/40' }}">
                                    @if ($selected)<span class="size-2.5 rounded-full bg-primary"></span>@endif
                                </span>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="font-heading font-medium text-text text-[15px] leading-tight">{{ $p->name }}
                                            @if ($p->is_featured)<span class="ml-1.5 align-middle rounded-full bg-secondary/40 text-primary px-1.5 py-0.5 font-sans text-[9px]">Best value</span>@endif
                                        </p>
                                        <x-money :usd="$prices[$p->id]" class="font-heading font-semibold text-primary text-[16px] leading-tight shrink-0" />
                                    </div>
                                    <p class="font-sans text-muted text-[12px] mt-1 flex flex-wrap items-center gap-x-1.5">
                                        @if ((float) $p->daily_call_minutes > 0)
                                            <span><i class="ri-time-line"></i> +{{ rtrim(rtrim(number_format((float) $p->daily_call_minutes, 1), '0'), '.') }} eSIM mins/day</span>
                                        @endif
                                        @if ((float) $p->share_minutes > 0)
                                            <span>· <i class="ri-pie-chart-line"></i> +{{ rtrim(rtrim(number_format((float) $p->share_minutes, 1), '0'), '.') }} share mins{{ $shareSubscription ? '' : ' (requires active term)' }}</span>
                                        @endif
                                        @if ((float) $p->daily_call_minutes <= 0 && (float) $p->share_minutes <= 0)
                                            <span>No minute allocation configured</span>
                                        @endif
                                        @if ((int) $p->call_id_bonus > 0)
                                            <span>· <i class="ri-contacts-book-3-line"></i> +{{ $p->call_id_bonus }} phone number{{ $p->call_id_bonus > 1 ? 's' : '' }}</span>
                                        @endif
                                        <span>· valid {{ $p->duration_days }} days</span>
                                    </p>
                                </div>
                            </button>
                        @endforeach
                        @error('planId')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>

                    @if ($canBuyShareMinutes && $directSharePlans->isNotEmpty())
                        <div class="rounded-[16px] bg-secondary/10 p-4 flex flex-col gap-2.5">
                            <div>
                                <p class="font-heading font-medium text-text text-[14px]">Pay directly for share minutes</p>
                                <p class="font-sans text-muted text-[11px]">The term must still be active when the payment is verified.</p>
                            </div>
                            @foreach ($directSharePlans as $topUpPlan)
                                <form method="POST" action="{{ route('minutes.topup.buy.start') }}" class="flex items-center justify-between gap-3 rounded-[12px] bg-surface px-3 py-2.5">
                                    @csrf
                                    <input type="hidden" name="plan_id" value="{{ $topUpPlan->id }}">
                                    <span class="font-sans text-text text-[12px]">{{ $topUpPlan->name }} · +{{ rtrim(rtrim(number_format((float) $topUpPlan->share_minutes, 1), '0'), '.') }} share mins</span>
                                    <button type="submit" class="shrink-0 rounded-full bg-primary px-3.5 py-2 font-sans text-[12px] font-semibold text-white">Pay <x-money :usd="$prices[$topUpPlan->id]" :abbreviate="false" /></button>
                                </form>
                            @endforeach
                        </div>
                    @endif

                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-[13px] text-text font-medium">Shares token</label>
                        <input type="text" wire:model="token" placeholder="Enter token code" wire:keydown.enter="redeem"
                               class="w-full h-[48px] rounded-[14px] bg-background border border-muted/20 px-4 outline-none font-sans text-[14px] text-text uppercase placeholder:text-muted placeholder:normal-case focus:border-primary focus:ring-1 focus:ring-primary">
                        @error('token')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>

                    <button type="button" wire:click="redeem" wire:loading.attr="disabled" wire:target="redeem"
                            class="h-[48px] rounded-[30px] bg-primary text-surface font-sans font-semibold text-[14px] flex items-center justify-center gap-2 hover:opacity-95 transition disabled:opacity-60">
                        <span wire:loading.remove wire:target="redeem">Redeem shares</span>
                        <span wire:loading wire:target="redeem" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Redeeming…</span>
                    </button>
                    @else
                        <div class="rounded-[14px] bg-background px-4 py-6 text-center">
                            <p class="font-sans text-muted text-[13px]">No compatible share-minute top-ups are configured for this account yet.</p>
                        </div>
                    @endif
                @endif
            </div>

            <div class="lg:col-span-1 flex flex-col gap-2.5">
                <p class="font-heading font-medium text-text text-[15px]">Active boosts</p>
                @forelse ($activeTopUps as $topUp)
                    <div class="bg-surface rounded-[16px] px-4 py-3.5 flex items-start justify-between gap-3 shadow-sm">
                        <div class="flex items-start gap-2.5 min-w-0">
                            <span class="size-9 rounded-full bg-success/12 flex items-center justify-center text-success shrink-0"><i class="ri-timer-flash-line text-[17px]"></i></span>
                            <div class="min-w-0 flex flex-col gap-1">
                                <p class="font-sans font-semibold text-text text-[14px] leading-tight">
                                    @if ((float) $topUp->daily_call_minutes > 0 && $topUp->expires_at?->isFuture())
                                        +{{ rtrim(rtrim(number_format((float) $topUp->daily_call_minutes, 1), '0'), '.') }} eSIM mins/day
                                    @endif
                                    @if ((float) $topUp->share_minutes > 0 && $topUp->share_expires_at?->isFuture() && $topUp->shareSubscription?->status === 'active')
                                        {{ (float) $topUp->daily_call_minutes > 0 && $topUp->expires_at?->isFuture() ? ' · ' : '' }}+{{ rtrim(rtrim(number_format((float) $topUp->share_minutes, 1), '0'), '.') }} share mins
                                    @endif
                                    @if ((int) ($topUp->plan?->call_id_bonus ?? 0) > 0 && $topUp->expires_at?->isFuture())
                                        <span class="text-muted font-normal">· +{{ $topUp->plan->call_id_bonus }} number{{ $topUp->plan->call_id_bonus > 1 ? 's' : '' }}</span>
                                    @endif
                                </p>
                                <p class="font-sans text-muted text-[11px]">
                                    {{ $topUp->plan?->name ?? 'Minutes boost' }}
                                </p>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            @if ($topUp->expires_at?->isFuture() && (float) $topUp->daily_call_minutes > 0)
                                <p class="font-sans text-muted text-[10px]">eSIM expires</p>
                                <p class="font-sans text-text text-[11px] leading-tight">{{ $topUp->expires_at->format('j M Y') }}</p>
                            @endif
                            @if ($topUp->share_expires_at?->isFuture() && (float) $topUp->share_minutes > 0 && $topUp->shareSubscription?->status === 'active')
                                <p class="font-sans text-muted text-[10px]">Share minutes expire</p>
                                <p class="font-sans text-text text-[11px] leading-tight">{{ $topUp->share_expires_at->format('j M Y') }}</p>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="bg-surface rounded-[16px] px-4 py-6 flex flex-col items-center gap-1.5 text-center shadow-sm">
                        <i class="ri-timer-line text-muted text-[24px]"></i>
                        <p class="font-sans text-muted text-[13px]">No active boosts yet.</p>
                        <p class="font-sans text-muted/70 text-[11px]">Redeem a token to add minutes.</p>
                    </div>
                @endforelse
            </div>
        </div>
        @endunless
    </div>

    <x-app.bottom-nav />
</div>
