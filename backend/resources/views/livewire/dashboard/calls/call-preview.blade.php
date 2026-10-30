<div class="min-h-screen bg-background lg:flex lg:items-center lg:justify-center lg:p-10">
    <div class="mx-auto w-full max-w-[440px] lg:max-w-[470px] px-4 lg:px-9 pt-6 lg:pt-9 pb-12 lg:pb-9 flex flex-col gap-6 lg:gap-7 lg:bg-surface lg:rounded-[32px] lg:shadow-[0_30px_80px_-20px_rgba(24,68,62,0.25)] lg:border lg:border-black/[0.04]">

        <div class="flex items-center gap-3">
            <a href="{{ route('beneficiaries') }}" wire:navigate class="size-10 rounded-full bg-surface lg:bg-background shadow-sm flex items-center justify-center text-text shrink-0" aria-label="Back">
                <i class="ri-arrow-left-s-line text-[22px]"></i>
            </a>
            <p class="font-heading font-medium text-muted text-[14px] tracking-wide">CALL PREVIEW</p>
        </div>

        @if ($beneficiary)
            <div class="flex flex-col items-center gap-3 pt-2">
                <div class="relative">
                    <span class="absolute inset-0 rounded-full bg-primary/10 scale-125"></span>
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
                    <i class="ri-timer-line text-muted text-[18px]"></i>
                    <p class="font-sans text-muted text-[12px]">Connected Minutes</p>
                    <p class="font-heading font-semibold text-text text-[22px] leading-none">00:00</p>
                </div>
                <div class="flex-1 min-w-0 bg-surface lg:bg-background rounded-[20px] px-4 py-5 flex flex-col items-center gap-1.5 text-center shadow-[0_4px_9px_rgba(194,194,194,0.1)] lg:shadow-none">
                    <i class="ri-money-dollar-circle-line text-primary text-[18px]"></i>
                    <p class="font-sans text-muted text-[12px]">Live Earnings</p>
                    <p class="font-heading font-semibold text-primary text-[22px] leading-none"><x-money :usd="$rate" /><span class="text-[12px] text-muted font-sans font-normal">/min</span></p>
                </div>
            </div>

            <div class="bg-secondary/20 rounded-[16px] px-4 py-3.5 flex gap-2.5">
                <i class="ri-information-line text-primary text-[18px] shrink-0 mt-0.5"></i>
                <div class="flex flex-col gap-1">
                    <p class="font-heading font-semibold text-text text-[13px]">Earnings start only after the call connects.</p>
                    <p class="font-sans text-muted text-[11px] leading-relaxed">No connection means the timer stays at 00:00 and no minute is generated.</p>
                </div>
            </div>

            <div class="flex gap-3 mt-auto pt-2">
                <a href="{{ route('beneficiaries') }}" wire:navigate class="flex-1 h-[52px] rounded-full bg-danger/15 text-danger flex items-center justify-center gap-2 font-sans font-semibold text-[15px] hover:bg-danger/20 transition">
                    <i class="ri-close-line text-[18px]"></i> Cancel
                </a>
                <a href="{{ route('call.active', ['n' => $beneficiary->id]) }}" wire:navigate class="flex-[1.3] h-[52px] rounded-full bg-primary text-surface flex items-center justify-center gap-2 font-sans font-semibold text-[15px] hover:opacity-95 transition shadow-[0_8px_20px_rgba(24,68,62,0.25)]">
                    <i class="ri-phone-fill text-[17px]"></i> Start Call
                </a>
            </div>
        @else
            <div class="bg-surface rounded-[20px] p-8 flex flex-col items-center gap-3 text-center mt-6">
                <span class="size-12 rounded-full bg-background flex items-center justify-center text-muted"><i class="ri-phone-line text-[22px]"></i></span>
                <p class="font-sans text-muted text-[14px]">You don't have a number to call yet.</p>
                <a href="{{ route('plans') }}" wire:navigate class="inline-flex items-center gap-2 bg-primary text-surface rounded-[30px] px-5 py-2.5 font-sans text-[14px]">Activate a Plan</a>
            </div>
        @endif
    </div>
</div>
