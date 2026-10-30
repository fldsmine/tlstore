<div class="min-h-screen bg-background lg:flex lg:items-center lg:justify-center lg:p-10">
    <div class="mx-auto w-full max-w-[440px] lg:max-w-[470px] px-4 lg:px-9 pt-6 lg:pt-9 pb-12 lg:pb-9 flex flex-col gap-6 lg:gap-7 lg:bg-surface lg:rounded-[32px] lg:shadow-[0_30px_80px_-20px_rgba(24,68,62,0.25)] lg:border lg:border-black/[0.04]"
         {{-- The ticker counts in the reader's currency, so it gets the converted rate and its symbol. --}}
         x-data="{ connecting: true, secs: 0, rate: {{ \App\Support\DisplayCurrency::convert((float) $rate) }}, symbol: @js(\App\Support\DisplayCurrency::symbol()), muted: false, speaker: false, timer: null,
                   get clock() { return String(Math.floor(this.secs / 60)).padStart(2, '0') + ':' + String(this.secs % 60).padStart(2, '0'); },
                   get earned() { return this.symbol + (this.secs / 60 * this.rate).toFixed(2); } }"
         x-init="setTimeout(() => { connecting = false; timer = setInterval(() => secs++, 1000); }, 3500)">

        @if ($beneficiary)
            <div class="flex items-center gap-3">
                <p class="font-heading font-medium text-muted text-[14px] tracking-wide">ACTIVE CALL</p>
                <span class="inline-flex items-center gap-1.5 ml-auto">
                    <span class="size-2 rounded-full animate-pulse" :class="connecting ? 'bg-[#D79937]' : 'bg-success'"></span>
                    <span class="font-sans text-[12px]" :class="connecting ? 'text-[#D79937]' : 'text-success'" x-text="connecting ? 'Ringing…' : 'Connected'">Connected</span>
                </span>
            </div>

            <div class="flex flex-col items-center gap-3 pt-2">
                <div class="relative flex items-center justify-center">
                    <span class="absolute size-[120px] rounded-full animate-ping" :class="connecting ? 'bg-[#D79937]/20' : 'bg-success/15'"></span>
                    <span class="absolute size-[108px] rounded-full" :class="connecting ? 'bg-[#D79937]/10' : 'bg-success/10'"></span>
                    <img src="{{ $beneficiary->logo }}" alt="" class="relative size-[88px] rounded-full object-cover ring-4 ring-surface shadow-[0_8px_24px_rgba(24,68,62,0.18)]">
                </div>
                <div class="flex flex-col items-center gap-1.5">
                    <p class="font-heading font-semibold text-text text-[24px] leading-none">{{ $beneficiary->masked }}</p>
                    <span class="inline-flex items-center gap-1.5 bg-surface rounded-full px-3 py-1 shadow-sm">
                        <span class="size-1.5 rounded-full" style="background: {{ $beneficiary->color }}"></span>
                        <span class="font-sans text-muted text-[13px]">{{ $beneficiary->network }} · {{ ucfirst(str_replace('_', '-', $beneficiary->callType()->value)) }}</span>
                    </span>
                </div>
            </div>

            <div class="flex gap-3">
                <div class="flex-1 min-w-0 bg-surface lg:bg-background rounded-[20px] px-4 py-5 flex flex-col items-center gap-1.5 text-center shadow-[0_4px_9px_rgba(194,194,194,0.1)] lg:shadow-none">
                    <p class="font-sans text-muted text-[12px]">Connected Minutes</p>
                    <p class="font-heading font-semibold text-text text-[30px] leading-none tabular-nums" x-text="clock">00:00</p>
                </div>
                <div class="flex-1 min-w-0 bg-primary rounded-[20px] px-4 py-5 flex flex-col items-center gap-1.5 text-center shadow-[0_8px_20px_rgba(24,68,62,0.2)]">
                    <p class="font-sans text-white/70 text-[12px]">Live Earnings</p>
                    <p class="font-heading font-semibold text-secondary text-[30px] leading-none tabular-nums" x-text="earned">{{ money(0) }}</p>
                </div>
            </div>

            <div class="bg-surface lg:bg-background rounded-[16px] px-4 py-3 flex items-center gap-2.5">
                <i class="ri-shield-check-line text-primary text-[18px] shrink-0"></i>
                <p class="font-sans text-muted text-[11px] leading-relaxed">Earnings are calculated from verified connected duration at <span class="font-semibold text-primary"><x-money :usd="$rate" />/min.</span></p>
            </div>

            <div class="flex gap-3">
                <button type="button" @click="muted = !muted" class="flex-1 h-[56px] rounded-[18px] flex flex-col items-center justify-center gap-1 font-sans text-[12px] transition-colors" :class="muted ? 'bg-primary text-surface' : 'bg-surface lg:bg-background text-text'">
                    <i class="text-[20px]" :class="muted ? 'ri-mic-off-line' : 'ri-mic-line'"></i>
                    <span x-text="muted ? 'Unmute' : 'Mute'">Mute</span>
                </button>
                <button type="button" @click="speaker = !speaker" class="flex-1 h-[56px] rounded-[18px] flex flex-col items-center justify-center gap-1 font-sans text-[12px] transition-colors" :class="speaker ? 'bg-primary text-surface' : 'bg-surface lg:bg-background text-text'">
                    <i class="ri-volume-up-line text-[20px]"></i>
                    Speaker
                </button>
            </div>

            <a href="{{ route('beneficiaries') }}" wire:navigate @click="clearInterval(timer)"
               class="mt-auto h-[56px] rounded-full bg-danger text-white flex items-center justify-center gap-2 font-sans font-semibold text-[15px] hover:opacity-95 transition shadow-[0_8px_20px_rgba(206,60,60,0.3)]">
                <i class="ri-phone-fill text-[18px] rotate-[135deg]"></i> End Call
            </a>
        @else
            <div class="bg-surface rounded-[20px] p-8 flex flex-col items-center gap-3 text-center mt-6">
                <p class="font-sans text-muted text-[14px]">No active number.</p>
                <a href="{{ route('beneficiaries') }}" wire:navigate class="inline-flex items-center gap-2 bg-primary text-surface rounded-[30px] px-5 py-2.5 font-sans text-[14px]">Back to Beneficiaries</a>
            </div>
        @endif
    </div>
</div>
