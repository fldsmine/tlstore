{{-- balances: Collection<array{label,amount,wallet}> (e.g. $user->walletBalances()); `amount` is
     already rendered in the user's display currency. `currency` shows the persisted USD/local switcher. --}}
@props(['balances', 'size' => '34px', 'eye' => true, 'withdraw' => false, 'currency' => false])

<div x-data="{
        hidden: JSON.parse(localStorage.getItem('nx_hide_balance') || 'false'),
        i: 0,
        balances: @js($balances->values()),
        next() { this.i = (this.i + 1) % this.balances.length },
        toggle() {
            this.hidden = ! this.hidden;
            localStorage.setItem('nx_hide_balance', JSON.stringify(this.hidden));
            window.dispatchEvent(new CustomEvent('nx-balance-visibility', { detail: this.hidden }));
        },
     }"
     x-init="window.addEventListener('nx-balance-visibility', e => hidden = e.detail)"
     class="flex flex-col gap-4">
    <div class="flex items-start justify-between">
        <div @click="next()" role="button" tabindex="0" @keydown.enter="next()" @keydown.space.prevent="next()"
             class="flex flex-col gap-2 cursor-pointer select-none" aria-label="Switch wallet balance" title="Tap to switch wallet">
            <div class="flex items-center gap-2.5">
                <p class="font-sans text-text text-[16px]" x-text="balances[i].label"></p>
                <span class="size-[30px] rounded-full bg-surface shadow-sm flex items-center justify-center text-primary">
                    <i class="ri-arrow-right-s-line text-[18px]"></i>
                </span>
            </div>
            <div class="flex flex-col">
                <p class="font-heading font-semibold text-text leading-none" style="font-size: {{ $size }}">
                    <span x-show="!hidden" class="inline-flex items-center gap-1.5">
                        <span x-text="balances[i].amount"></span>
                        {{-- Abbreviated (K/M/B) balances hide the exact value; a tappable popover reveals it — native title tooltips don't fire on touch. --}}
                        <template x-if="balances[i].amount !== balances[i].exact">
                            <span class="relative inline-flex items-center leading-none" x-data="{ hover: false, pinned: false }"
                                  @mouseenter="hover = true" @mouseleave="hover = false"
                                  @click.outside="pinned = false" @keydown.escape.window="pinned = false">
                                <span role="button" tabindex="0" aria-label="Show full value"
                                      @click.prevent.stop="pinned = ! pinned" @keydown.enter.prevent.stop="pinned = ! pinned"
                                      class="shrink-0 cursor-pointer p-2 -m-1.5 text-muted hover:text-primary transition-colors">
                                    <i class="ri-information-line text-[14px] align-middle"></i>
                                </span>
                                <span x-show="hover || pinned" x-cloak @click.prevent.stop="pinned = false"
                                      class="absolute bottom-full left-1/2 z-30 mb-2 -translate-x-1/2 whitespace-nowrap rounded-[10px] bg-text px-2.5 py-1.5 font-sans text-[12px] font-normal leading-none text-surface shadow-lg"
                                      x-text="balances[i].exact"></span>
                            </span>
                        </template>
                    </span>
                    <span x-show="hidden" x-cloak>{{ \App\Support\DisplayCurrency::symbol() }}••••••</span>
                </p>
                {{ $slot }}
            </div>
        </div>
        <div class="flex items-center gap-2 mt-1 shrink-0">
            @if ($currency)
                <livewire:dashboard.currency-switcher />
            @endif
            @if ($eye)
                <button @click="toggle()" class="text-text" aria-label="Toggle balance">
                    <i class="ri-eye-line text-[22px]" x-show="!hidden"></i>
                    <i class="ri-eye-off-line text-[22px]" x-show="hidden" x-cloak></i>
                </button>
            @endif
        </div>
    </div>

    @if ($withdraw)
        {{-- Withdraws the wallet shown above. Total is an aggregate, not withdrawable. --}}
        <a x-bind:href="['total', 'legacy_shareholder'].includes(balances[i].wallet) ? null : '{{ route('withdraw') }}?wallet=' + balances[i].wallet"
           x-bind:aria-disabled="['total', 'legacy_shareholder'].includes(balances[i].wallet)"
           x-bind:tabindex="['total', 'legacy_shareholder'].includes(balances[i].wallet) ? -1 : 0"
           x-bind:title="balances[i].wallet === 'legacy_shareholder' ? 'Legacy shares are visible but locked and cannot be withdrawn' : (balances[i].wallet === 'total' ? 'Total is an aggregate of your wallets — switch to a specific wallet to withdraw' : 'Withdraw')"
           x-bind:class="['total', 'legacy_shareholder'].includes(balances[i].wallet)
               ? 'bg-primary/40 text-surface/70 cursor-not-allowed pointer-events-none'
               : 'bg-primary text-surface hover:opacity-95 shadow-[0_8px_20px_rgba(24,68,62,0.18)]'"
           wire:navigate
           class="self-start inline-flex items-center gap-2 rounded-[30px] px-5 py-2.5 font-sans font-semibold text-[14px] transition">
            <i class="ri-bank-card-line text-[17px]"></i>
            <span x-text="balances[i].wallet === 'legacy_shareholder' ? 'Locked' : 'Withdraw'"></span>
        </a>
    @endif
</div>
