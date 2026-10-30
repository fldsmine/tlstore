<div class="min-h-screen bg-background">
    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-12 flex flex-col gap-[22px]">

        <x-app.app-bar :title="'Transfer Funds'" />

        <div class="flex flex-col gap-[22px] lg:max-w-[460px] lg:mx-auto lg:w-full">

            <div x-data="{ cur: 'USD', open: false }" class="relative overflow-hidden rounded-[22px] p-5 text-white shadow-[0_14px_34px_-12px_rgba(24,68,62,0.5)]"
                 style="background: radial-gradient(125% 130% at 88% 12%, #2f7468 0%, #18443E 58%); background-color:#18443E;">
                <span class="absolute -right-8 -top-10 size-36 rounded-full bg-white/[0.06]"></span>
                <div class="relative flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-sans text-white/60 text-[12px] uppercase tracking-wide">Vendor wallet</p>
                        <p class="font-heading font-semibold text-[30px] mt-1 truncate">
                            <span x-show="cur === 'USD'">${{ number_format($balance, 2) }}</span>
                            <span x-show="cur !== 'USD'" x-cloak>{{ $localBalance }}</span>
                        </p>
                        <p class="font-sans text-white/70 text-[12px] mt-1">Available to transfer</p>
                    </div>
                    @if ($localCurrency !== 'USD')
                        <div class="relative shrink-0" @click.outside="open = false">
                            <button type="button" @click="open = !open" aria-haspopup="listbox" :aria-expanded="open" aria-label="Select currency"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-white/15 hover:bg-white/25 transition px-3 py-1.5 font-sans text-[11px]">
                                <i class="ri-coin-line text-[13px]"></i>
                                <span x-text="cur"></span>
                                <i class="ri-arrow-down-s-line text-[13px] transition-transform" :class="open && 'rotate-180'"></i>
                            </button>
                            <div x-show="open" x-cloak x-transition.opacity.duration.150ms role="listbox"
                                 class="absolute right-0 mt-1.5 w-[150px] rounded-[14px] bg-white text-text shadow-[0_12px_30px_rgba(0,0,0,0.25)] overflow-hidden z-20">
                                <button type="button" @click="cur = 'USD'; open = false" :class="cur === 'USD' && 'bg-primary/10'"
                                        class="w-full flex items-center justify-between px-3.5 py-2.5 font-sans text-[12px] hover:bg-background text-left">
                                    <span>USD</span>
                                    <i x-show="cur === 'USD'" class="ri-check-line text-primary text-[14px]"></i>
                                </button>
                                <button type="button" @click="cur = '{{ $localCurrency }}'; open = false" :class="cur !== 'USD' && 'bg-primary/10'"
                                        class="w-full flex items-center justify-between px-3.5 py-2.5 font-sans text-[12px] hover:bg-background text-left border-t border-muted/10">
                                    <span>{{ $localCurrency }} <span class="text-muted text-[10px]">Native</span></span>
                                    <i x-show="cur !== 'USD'" class="ri-check-line text-primary text-[14px]"></i>
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="bg-surface rounded-[24px] p-5 lg:p-7 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)] flex flex-col gap-4">
                <div class="flex items-start gap-3 bg-secondary/20 rounded-[16px] px-4 py-3.5">
                    <i class="ri-exchange-funds-line text-primary text-[18px] shrink-0 mt-0.5"></i>
                    <p class="font-sans text-muted text-[12px] leading-relaxed">Send funds from your vendor wallet to another vendor instantly. Transfers are PIN-protected and can’t be reversed.</p>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-[14px] text-text">Vendor username</label>
                    <div class="flex items-center gap-2.5 h-[48px] px-4 rounded-[16px] border-[0.5px] border-primary focus-within:ring-1 focus-within:ring-primary">
                        <i class="ri-at-line text-[15px] text-muted shrink-0"></i>
                        <input type="text" wire:model="recipientUsername" placeholder="Recipient’s username"
                               class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[14px] text-text placeholder:text-muted placeholder:text-[13px]">
                    </div>
                    @error('recipientUsername')<span class="font-sans text-[12px] text-danger px-1">{{ $message }}</span>@enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-[14px] text-text">Amount (USD)</label>
                    <div class="flex items-center gap-2.5 h-[48px] px-4 rounded-[16px] border-[0.5px] border-primary focus-within:ring-1 focus-within:ring-primary">
                        <span class="font-heading text-text text-[16px]">$</span>
                        <input type="text" wire:model.live.debounce.400ms="amount" inputmode="decimal" placeholder="0.00"
                               class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[14px] text-text placeholder:text-muted">
                        <span class="font-sans text-muted text-[12px] shrink-0">USD</span>
                    </div>
                    <div class="flex items-center justify-between px-1">
                        @error('amount')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@else<span></span>@enderror
                        <span class="font-sans text-muted text-[12px]">Balance: ${{ number_format($balance, 2) }}</span>
                    </div>
                </div>

                <button type="button" wire:click="review" wire:loading.attr="disabled" wire:target="review"
                        class="mt-1 h-[50px] rounded-[30px] bg-primary text-surface flex items-center justify-center gap-2 font-sans font-semibold text-[14px] hover:opacity-95 transition disabled:opacity-60">
                    <span wire:loading.remove wire:target="review"><i class="ri-arrow-right-line"></i> Review transfer</span>
                    <span wire:loading wire:target="review" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Please wait…</span>
                </button>
            </div>
        </div>
    </div>

    @if ($confirming && $preview)
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
                    <span class="size-14 rounded-full bg-primary/10 flex items-center justify-center overflow-hidden">
                        @if ($preview['avatar'])<img src="{{ $preview['avatar'] }}" alt="" class="size-full object-cover">@else<i class="ri-user-line text-primary text-[26px]"></i>@endif
                    </span>
                    <p class="font-heading font-medium text-text text-[19px]">Confirm transfer</p>
                    <p class="font-sans text-muted text-[13px] leading-relaxed max-w-[290px]">
                        Send <span class="font-semibold text-text">${{ number_format($preview['amount'], 2) }}</span>
                        to <span class="font-semibold text-text">{{ '@'.$preview['username'] }}</span>.
                    </p>
                </div>

                <div class="bg-surface rounded-[16px] p-4 flex flex-col gap-2 font-sans text-[13px]">
                    <div class="flex items-center justify-between"><span class="text-muted">Recipient</span><span class="text-text font-medium truncate ml-3">{{ '@'.$preview['username'] }}@if ($preview['name']) <span class="text-muted font-normal">· {{ $preview['name'] }}</span>@endif</span></div>
                    <div class="flex items-center justify-between"><span class="text-muted">Amount</span><span class="text-text font-medium">${{ number_format($preview['amount'], 2) }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-muted">Fee</span><span class="text-text">$0.00</span></div>
                    <div class="flex items-center justify-between border-t border-background pt-2"><span class="font-semibold text-text">They receive</span><span class="font-semibold text-success">${{ number_format($preview['amount'], 2) }}</span></div>
                </div>

                <div class="flex flex-col gap-2.5">
                    <label class="font-sans text-[13px] text-text font-medium text-center">Transaction PIN</label>
                    <x-app.pin-input target="pin" />
                    @error('pin')<span class="font-sans text-[12px] text-danger text-center">{{ $message }}</span>@enderror
                </div>

                <button type="button" wire:click="transfer" wire:loading.attr="disabled" wire:target="transfer"
                        class="h-[52px] rounded-[30px] bg-primary text-surface flex items-center justify-center gap-2 font-sans font-semibold text-[15px] hover:opacity-95 transition disabled:opacity-60">
                    <span wire:loading.remove wire:target="transfer" class="flex items-center gap-2"><i class="ri-checkbox-circle-line text-[18px]"></i> Confirm Transfer</span>
                    <span wire:loading wire:target="transfer" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Sending…</span>
                </button>
            </div>
        </div>
    @endif

    <x-app.bottom-nav />
</div>
