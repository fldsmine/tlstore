<div class="min-h-screen bg-background">
    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-12 flex flex-col gap-[22px]">

        <x-app.app-bar title="Withdraw" />

        <div class="flex flex-col gap-4 w-full">

            @if (! $isActivated)
                <x-app.feature-locked class="mt-2" icon="ri-sim-card-2-line"
                    title="Activate your account to withdraw"
                    message="You need an active eSIM plan before you can withdraw. Activate your account to unlock withdrawals and start earning.">
                    <button type="button" x-data @click="Livewire.dispatch('open-activation')"
                       class="h-[48px] px-6 rounded-[30px] bg-primary text-surface inline-flex items-center justify-center gap-2 font-sans font-semibold text-[14px] hover:opacity-95 transition">
                        <i class="ri-flashlight-line text-[18px]"></i> Activate a Plan
                    </button>
                </x-app.feature-locked>
            @elseif ($userBlocked)
                <x-app.feature-locked class="mt-2" icon="ri-lock-2-line"
                    title="Withdrawals disabled"
                    message="Withdrawals are currently disabled on your account.">
                    <a href="{{ route('contact') }}"
                       class="h-[48px] px-6 rounded-[30px] bg-primary text-surface inline-flex items-center justify-center gap-2 font-sans font-semibold text-[14px] hover:opacity-95 transition">
                        <i class="ri-customer-service-2-line text-[18px]"></i> Contact support
                    </a>
                </x-app.feature-locked>
            @elseif (! $bankAccount)
                <x-app.feature-locked class="mt-2" icon="ri-bank-card-line"
                    title="Add a payout account"
                    message="Before you can withdraw, add the Nigerian bank account where your money should be paid.">
                    <a href="{{ route('payment-settings') }}" wire:navigate
                       class="h-[48px] px-6 rounded-[30px] bg-primary text-surface inline-flex items-center justify-center gap-2 font-sans font-semibold text-[14px] hover:opacity-95 transition">
                        <i class="ri-add-line text-[18px]"></i> Add Bank Account
                    </a>
                </x-app.feature-locked>
            @elseif (! $anyWalletOpen)
                <x-app.feature-locked class="mt-2" icon="ri-time-line"
                    title="Withdrawals paused"
                    message="Withdrawals are temporarily unavailable. Please check back soon.">
                    <a href="{{ route('contact') }}"
                       class="h-[48px] px-6 rounded-[30px] bg-primary text-surface inline-flex items-center justify-center gap-2 font-sans font-semibold text-[14px] hover:opacity-95 transition">
                        <i class="ri-customer-service-2-line text-[18px]"></i> Contact support
                    </a>
                </x-app.feature-locked>
            @else
                <div class="flex flex-col gap-4 lg:grid lg:grid-cols-2 lg:gap-5 lg:items-start">

                <div class="flex flex-col gap-4">

                <div x-data="{ show: true }" class="flex flex-col gap-3 pt-1">
                    <div class="flex items-center justify-between gap-3">
                        <div class="relative">
                            <select wire:model.live="wallet"
                                    class="appearance-none max-w-[210px] h-[38px] rounded-full bg-surface shadow-sm pl-4 pr-9 font-sans text-[13px] font-medium text-text outline-none focus:ring-1 focus:ring-primary cursor-pointer">
                                @foreach ($wallets as $w)
                                    <option value="{{ $w['key'] }}">{{ $w['label'] }}@unless ($w['open']) · closed @endunless</option>
                                @endforeach
                            </select>
                            <i class="ri-arrow-down-s-line text-[18px] text-primary absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                        </div>
                        <button @click="show = !show" class="text-muted shrink-0" aria-label="Toggle balance">
                            <i class="ri-eye-line text-[20px]" x-show="show"></i>
                            <i class="ri-eye-off-line text-[20px]" x-show="!show" x-cloak></i>
                        </button>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <p class="font-heading font-semibold text-text text-[34px] leading-none">
                            <x-money :usd="$available" x-show="show" />
                            <span x-show="!show" x-cloak>{{ \App\Support\DisplayCurrency::symbol() }}••••••</span>
                        </p>
                        <p class="font-sans text-muted text-[12px]">Available in {{ $walletLabel }}</p>
                    </div>
                </div>

                {{-- TEMPORARY PROMO: +50% commission on N-Cloud SMS withdrawals (remove with settings.withdrawal.sms_bonus_rate). --}}
                @if ($smsBonusRate > 0)
                    <div class="flex items-start gap-2.5 bg-success/12 rounded-[16px] px-4 py-3">
                        <span class="size-8 rounded-full bg-success/20 text-success flex items-center justify-center shrink-0"><i class="ri-gift-2-fill text-[17px]"></i></span>
                        <p class="font-sans text-[12.5px] text-text leading-relaxed">
                            <span class="font-semibold text-success">Get an extra {{ (int) round($smsBonusRate * 100) }}% bonus</span> on every {{ $walletLabel }} withdrawal! Cash out any amount and receive more than you requested — paid directly into your bank account.
                        </p>
                    </div>
                @endif

                <div class="bg-surface rounded-[22px] p-5 lg:p-6 flex flex-col gap-4 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                    <div class="flex flex-col items-center gap-2 pt-1">
                        <div class="flex items-center justify-between w-full">
                            <span class="font-sans text-muted text-[13px]">Enter Amount</span>
                            <button type="button" wire:click="fillMax"
                                    class="inline-flex items-center gap-1 font-sans text-[12px] text-primary font-semibold bg-primary/10 rounded-full px-2.5 py-1 hover:bg-primary/15 transition">
                                <i class="ri-flashlight-line text-[13px]"></i> Max
                            </button>
                        </div>
                        <label class="flex items-baseline justify-center gap-1.5 w-full cursor-text py-1">
                            <span class="font-heading font-semibold text-text text-[26px] leading-none">{{ $entrySymbol }}</span>
                            <input type="text" inputmode="decimal" wire:model.live.debounce.400ms="amount" placeholder="0.00"
                                   class="field-sizing-content min-w-[80px] max-w-[230px] bg-transparent outline-none border-0 p-0 text-center font-heading font-semibold text-text text-[40px] leading-none placeholder:text-muted/40">
                            <span class="font-sans text-muted text-[13px]">{{ $entryCode }}</span>
                        </label>
                        <div class="flex items-center justify-center gap-2 font-sans text-[12px]">
                            <span class="text-muted">Min <x-money :usd="$minAmount" :abbreviate="false" /></span>
                            <span class="size-1 rounded-full bg-muted/40"></span>
                            <span class="text-primary">Available <x-money :usd="$available" :abbreviate="false" /></span>
                        </div>
                        @error('amount')<span class="font-sans text-[12px] text-danger text-center">{{ $message }}</span>@enderror
                    </div>

                    <div class="border-t border-background pt-4 flex flex-col gap-2.5">
                        <div class="flex items-center justify-between font-sans text-[14px]">
                            <p class="text-muted">Amount</p>
                            <p class="text-text"><x-money :usd="$amountUsd" /></p>
                        </div>
                        {{-- TEMPORARY PROMO: show the N-Cloud SMS bonus as its own line. --}}
                        @if ($smsBonusRate > 0 && $net > $amountUsd)
                            <div class="flex items-center justify-between font-sans text-[14px]">
                                <p class="text-muted">Bonus <span class="text-success font-medium">(+{{ (int) round($smsBonusRate * 100) }}%)</span></p>
                                <p class="text-success">+<x-money :usd="(float) ($net - $amountUsd)" /></p>
                            </div>
                        @endif
                        @if ($flatFee > 0 && $smsBonusRate <= 0)
                            <div class="flex items-center justify-between font-sans text-[14px]">
                                <p class="text-muted">Processing Fee</p>
                                <p class="text-danger">−<x-money :usd="(float) $fee" /></p>
                            </div>
                        @endif
                        <div class="flex items-center justify-between text-[14px] border-t border-background pt-2.5">
                            <p class="font-sans font-semibold text-text">You Receive</p>
                            <p class="font-sans font-semibold text-success"><x-money :usd="(float) $net" /></p>
                        </div>
                    </div>
                </div>

                @if ($requiresWithdrawalCode)
                    <div class="bg-surface rounded-[22px] p-5 lg:p-6 flex flex-col gap-3 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                        <div class="flex items-center gap-2">
                            <span class="size-8 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0"><i class="ri-key-2-line text-[16px]"></i></span>
                            <p class="font-sans font-semibold text-text text-[14px]">Account Authorization Code</p>
                        </div>
                        <p class="font-sans text-muted text-[12px] leading-relaxed">N-Cloud withdrawals need authorization code to validate this withdrawal. Enter it to continue.</p>
                        <input type="text" wire:model="withdrawalCode" autocomplete="off" placeholder="AUTHORIZATION CODE"
                               class="w-full h-12 rounded-[12px] bg-background border border-muted/15 px-4 font-mono text-[14px] tracking-wider uppercase text-text placeholder:text-muted/50 placeholder:font-sans placeholder:tracking-normal focus:outline-none focus:border-primary/40 transition @error('withdrawalCode') border-danger @enderror">
                        @error('withdrawalCode')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                        @if ($codeNeedsActivation)
                            <a href="{{ route('special-coupon') }}" wire:navigate
                               class="inline-flex items-center gap-1 font-sans text-[12px] text-primary font-semibold hover:underline">
                                <i class="ri-gift-2-line text-[13px]"></i> Activate this code on the Special Coupon page
                            </a>
                        @endif

						<div class="flex items-center gap-1 font-sans text-[12px] text-muted">
							<i class="ri-shield-check-line text-[13px]"></i>
							<span>Not sure it's valid?</span>
							@if (session('mini_app'))
								<button type="button" @click.prevent="window.Android.openCouponSearch()" class="inline-flex items-center gap-1 font-sans text-[12px] text-primary font-semibold hover:underline">
									<i class="ri-shield-check-line text-[13px]"></i> Verify a coupon code
								</button>
							@else
							<x-app.coupon-verify-sheet label="Verify a coupon code" />
							@endif
						</div>
                    </div>
                @endif

                </div>

                <div class="flex flex-col gap-4">

                <div class="flex flex-col gap-1.5">
                    <p class="font-sans font-semibold text-text text-[14px] px-1">Withdraw to</p>
                    <a href="{{ route('payment-settings') }}" wire:navigate
                       class="bg-surface rounded-[18px] px-4 py-3.5 flex items-center gap-3 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)] hover:bg-surface/70 transition">
                        <span class="size-10 rounded-full bg-primary/10 flex items-center justify-center shrink-0"><i class="ri-bank-line text-primary text-[20px]"></i></span>
                        <div class="flex flex-col min-w-0 flex-1">
                            <span class="font-sans font-semibold text-text text-[14px] truncate">{{ $bankAccount->bank->name }}</span>
                            <span class="font-sans text-muted text-[12px] truncate">{{ $bankAccount->masked_number }} · {{ $bankAccount->account_name }}</span>
                        </div>
                        <i class="ri-arrow-right-s-line text-[20px] text-muted shrink-0"></i>
                    </a>
                </div>

                <div class="flex items-start gap-2.5 bg-secondary/15 rounded-[16px] px-4 py-3">
                    <i class="ri-shield-check-line text-primary text-[18px] shrink-0 mt-0.5"></i>
                    <p class="font-sans text-[12px] text-muted leading-relaxed">Withdrawals are reviewed and paid to your saved bank account, usually between 24 and 48 hours.</p>
                </div>

                @if ($pendingWithdrawal)
                    <div class="flex items-start gap-2.5 bg-warning/12 rounded-[16px] px-4 py-3">
                        <i class="ri-time-line text-warning text-[18px] shrink-0 mt-0.5"></i>
                        <p class="font-sans text-[12px] text-text leading-relaxed">You have a withdrawal from {{ $walletLabel }} still processing. You can make another once it completes.</p>
                    </div>
                @elseif (! $walletOpen)
                    <div class="flex items-start gap-2.5 bg-warning/12 rounded-[16px] px-4 py-3">
                        <i class="ri-lock-2-line text-warning text-[18px] shrink-0 mt-0.5"></i>
                        <p class="font-sans text-[12px] text-text leading-relaxed">{{ $walletLabel }} withdrawals are currently closed. Pick another wallet above to continue.</p>
                    </div>
                @endif

                @php $canWithdraw = $walletOpen && ! $pendingWithdrawal; @endphp
                <button type="button" wire:click="review" wire:loading.attr="disabled" wire:target="review" @disabled(! $canWithdraw)
                        class="mt-1 h-[52px] rounded-[30px] bg-primary text-surface flex items-center justify-center gap-2 font-sans font-semibold text-[15px] hover:opacity-95 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="review" class="flex items-center gap-2">
                        <i class="ri-bank-card-line text-[17px]"></i>
                        @if (! $walletOpen) Withdrawal Closed @elseif ($pendingWithdrawal) Withdrawal in progress @else Withdraw @if ($net > 0){{ money($net, 0) }}@endif @endif
                    </span>
                    <span wire:loading wire:target="review" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Please wait…</span>
                </button>

                </div>{{-- /right column --}}
                </div>{{-- /grid --}}
            @endif
        </div>
    </div>

    {{-- PIN confirmation sheet (bottom on mobile, centered on desktop) --}}
    @if ($confirming && $bankAccount)
        <div data-lock-scroll class="fixed inset-0 z-[90] flex items-end justify-center sm:items-center sm:p-4"
             x-data="{ shown: false }" x-init="$nextTick(() => shown = true)">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" x-show="shown" x-transition.opacity @click="$wire.closeConfirm()"></div>

            <div class="relative w-full sm:max-w-[400px] bg-background rounded-t-[26px] sm:rounded-[26px] max-h-[92vh] overflow-y-auto px-6 pt-6 pb-8 flex flex-col gap-4"
                 x-show="shown"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">

                <button type="button" wire:click="closeConfirm" class="absolute right-5 top-5 size-9 rounded-full bg-surface shadow-sm flex items-center justify-center text-text" aria-label="Close">
                    <i class="ri-close-line text-[20px]"></i>
                </button>

                <div class="flex flex-col items-center text-center gap-2 mt-1">
                    <span class="size-14 rounded-full bg-primary/10 flex items-center justify-center"><i class="ri-shield-keyhole-line text-primary text-[28px]"></i></span>
                    <p class="font-heading font-medium text-text text-[19px]">Confirm withdrawal</p>
                    <p class="font-sans text-muted text-[13px] leading-relaxed max-w-[290px]">
                        Enter your 4-digit transaction PIN to send
                        <span class="font-semibold text-text">{{ money($net) }}</span>
                        to {{ $bankAccount->bank->name }} ({{ $bankAccount->masked_number }}).
                    </p>
                </div>

                <div class="bg-surface rounded-[16px] p-4 flex flex-col gap-2 font-sans text-[13px]">
                    <div class="flex items-center justify-between"><span class="text-muted">From</span><span class="text-text font-medium">{{ $walletLabel }}</span></div>
                    @if ($requiresWithdrawalCode)
                        <div class="flex items-center justify-between"><span class="text-muted">Authorization Code</span><span class="text-success font-medium inline-flex items-center gap-1"><i class="ri-checkbox-circle-fill text-[14px]"></i> Verified</span></div>
                    @endif
                    <div class="flex items-center justify-between"><span class="text-muted">Amount</span><span class="text-text"><x-money :usd="$amountUsd" /></span></div>
                    {{-- TEMPORARY PROMO: N-Cloud SMS bonus line. --}}
                    @if ($smsBonusRate > 0 && $net > $amountUsd)
                        <div class="flex items-center justify-between"><span class="text-muted">Bonus <span class="text-success font-medium">(+{{ (int) round($smsBonusRate * 100) }}%)</span></span><span class="text-success">+<x-money :usd="(float) ($net - $amountUsd)" /></span></div>
                    @endif
                    @if ($flatFee > 0 && $smsBonusRate <= 0)
                        <div class="flex items-center justify-between"><span class="text-muted">Processing Fee</span><span class="text-danger">−<x-money :usd="(float) $fee" /></span></div>
                    @endif
                    <div class="flex items-center justify-between border-t border-background pt-2"><span class="font-semibold text-text">You receive</span><span class="font-semibold text-success"><x-money :usd="(float) $net" /></span></div>
                    <div class="flex items-center justify-between text-[12px]"><span class="text-muted">Paid out in</span><span class="text-muted font-medium">{{ $localCurrency }}</span></div>
                </div>

                <div class="flex flex-col gap-2.5">
                    <label class="font-sans text-[13px] text-text font-medium text-center">Transaction PIN</label>
                    <x-app.pin-input target="pin" />
                    @error('pin')<span class="font-sans text-[12px] text-danger text-center">{{ $message }}</span>@enderror
                    <div class="flex items-center justify-between px-1 mt-0.5">
                        <a href="{{ route('reset-pin') }}" wire:navigate class="font-sans text-[12px] text-muted hover:text-primary">Forgot PIN?</a>
                        <a href="{{ route('reset-pin') }}" wire:navigate class="font-sans text-[12px] text-primary font-medium">Reset for: <x-money :usd="$pinResetPrice" /></a>
                    </div>
                </div>

                <button type="button" wire:click="withdraw" wire:loading.attr="disabled" wire:target="withdraw"
                        class="h-[52px] rounded-[30px] bg-primary text-surface flex items-center justify-center gap-2 font-sans font-semibold text-[15px] hover:opacity-95 transition disabled:opacity-60">
                    <span wire:loading.remove wire:target="withdraw" class="flex items-center gap-2"><i class="ri-checkbox-circle-line text-[18px]"></i> Confirm Withdrawal</span>
                    <span wire:loading wire:target="withdraw" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Processing…</span>
                </button>
            </div>
        </div>
    @endif

    <x-app.bottom-nav />
</div>
