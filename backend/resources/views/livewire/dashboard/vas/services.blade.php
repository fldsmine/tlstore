<div>
    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-12 flex flex-col gap-[22px]">

        <x-app.app-bar title="Value Added Services" subtitle="Pay bills & top up — all in one place" />

        {{-- Bills wallet: bill payments are funded from here; the user tops it up via Korapay checkout. --}}
        <div x-data="{ open: false, amount: '' }">
            <div class="relative overflow-hidden rounded-[20px] p-5 text-white shadow-[0_14px_34px_-12px_rgba(24,68,62,0.5)]"
                 style="background: radial-gradient(125% 130% at 88% 12%, #2f7468 0%, #18443E 58%); background-color:#18443E;">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex flex-col gap-1">
                        <p class="font-sans text-white/70 text-[11px] uppercase tracking-[0.14em]">VTU Balance</p>
                        <p class="font-heading font-semibold text-white text-[28px] leading-none"><x-money :usd="$billsBalance" /></p>
                        <p class="font-sans text-white/60 text-[12px]">Pay airtime, data, TV & bills from here.</p>
                    </div>
                    <i class="ri-wallet-3-line text-white/25 text-[30px]"></i>
                </div>

                {{-- Top-up temporarily disabled.
                <button type="button" @click="open = true"
                        class="mt-4 h-[42px] w-full rounded-[30px] bg-white/15 hover:bg-white/25 text-white font-sans text-[14px] inline-flex items-center justify-center gap-2 transition">
                    <i class="ri-add-circle-line text-[17px]"></i> Top up VTU balance
                </button>
                --}}
            </div>

            {{-- Top-up modal: amount is typed in the reader's display currency; the server converts it
                 to the NGN Korapay charges in and credits the USD equivalent on success. --}}
            <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4" @keydown.escape.window="open = false">
                <div class="absolute inset-0 bg-black/50" x-transition.opacity @click="open = false"></div>
                <div class="relative w-full max-w-[400px] bg-surface text-text rounded-[24px] p-6 shadow-2xl"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-4"
                     x-transition:enter-end="opacity-100 translate-y-0">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-heading font-semibold text-text text-[18px]">Top up VTU balance</p>
                            <p class="font-sans text-muted text-[13px] mt-0.5">Pay securely via Korapay checkout.</p>
                        </div>
                        <button type="button" @click="open = false" class="size-8 rounded-full bg-background flex items-center justify-center text-muted hover:text-text transition"><i class="ri-close-line text-[18px]"></i></button>
                    </div>

                    <form method="POST" action="{{ route('bills.topup.start') }}" class="mt-5 flex flex-col gap-4">
                        @csrf
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-[13px] text-text font-medium">Amount <span class="text-muted font-normal">({{ $entryCode }})</span></label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 font-heading text-muted text-[16px]">{{ $entrySymbol }}</span>
                                <input type="number" name="amount" x-model="amount" min="1" step="any" required placeholder="0"
                                       class="w-full h-[52px] rounded-[14px] bg-background border border-muted/20 pl-9 pr-4 outline-none font-heading text-[18px] text-text focus:border-primary focus:ring-1 focus:ring-primary">
                            </div>
                        </div>

                        <button type="submit" x-bind:disabled="!(Number(amount) > 0)"
                                class="h-[50px] rounded-[30px] bg-primary text-surface font-sans font-semibold text-[14px] inline-flex items-center justify-center gap-2 hover:opacity-95 transition disabled:opacity-50">
                            <i class="ri-secure-payment-line text-[17px]"></i> Continue to payment
                        </button>
                    </form>
                </div>
            </div>
        </div>

        @unless ($enabled)
            <div class="flex items-center gap-3 bg-warning/12 rounded-[16px] px-4 py-3.5">
                <i class="ri-time-line text-warning text-[18px] shrink-0"></i>
                <p class="font-sans text-muted text-[12px] leading-relaxed">Bill payments are temporarily unavailable. Please check back soon.</p>
            </div>
        @endunless

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-4">
            @foreach ($categories as $cat)
                <a @if ($enabled) href="{{ route('vas.category', $cat['key']) }}" wire:navigate @endif
                   wire:key="cat-{{ $cat['key'] }}"
                   class="group relative flex flex-col items-start gap-3 bg-surface rounded-[20px] p-4 md:p-5 text-left shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)] transition {{ $enabled ? 'hover:shadow-md cursor-pointer' : 'opacity-60 cursor-not-allowed' }}">
                    <span class="size-11 rounded-[14px] bg-primary/10 flex items-center justify-center group-hover:bg-primary/15 transition">
                        <i class="{{ $cat['icon'] }} text-primary text-[22px]"></i>
                    </span>
                    <div class="flex flex-col gap-0.5">
                        <p class="font-heading font-medium text-text text-[15px]">{{ $cat['label'] }}</p>
                        <p class="font-sans text-muted text-[12px] leading-snug">{{ $cat['blurb'] }}</p>
                    </div>
                </a>
            @endforeach
        </div>
    </div>

    <x-app.bottom-nav />
</div>
