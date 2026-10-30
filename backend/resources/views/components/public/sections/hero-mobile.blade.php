<div class="md:hidden relative overflow-hidden bg-background" style="height: 874px;">

    {{-- max-w-none: preflight would otherwise cap the fixed 1464px width --}}
    <img src="{{ asset('images/hero/lines-bg-mobile.svg') }}" alt=""
         class="absolute pointer-events-none max-w-none"
         style="left: -752.51px; top: -167px; width: 1464.517px; height: 1047px;" />

    <div class="absolute -translate-x-1/2 flex flex-col items-center gap-[40px] justify-center w-[323px]"
         style="left: calc(50% - 0.41px); top: 176px;">

        <div class="flex items-center gap-[5px] px-[10px] py-[6px] rounded-[66px] bg-background"
             style="border: 0.5px solid #18443e;">
            <span class="w-[6px] h-[6px] rounded-full bg-primary flex-shrink-0"></span>
            <span class="font-sans text-[12px] text-text whitespace-nowrap">eSIM verified ad network.</span>
        </div>

        <h1 class="font-heading font-semibold text-primary text-[24px] leading-tight text-center">
            <span class="text-text">Launch your campaign:</span><br>Reach 100% verified human audiences globally.
        </h1>

        <a href="{{ route('auth.register') }}"
           class="bg-primary text-surface font-sans font-semibold text-[14px] h-[50px] px-[25px]
                  rounded-[39px] flex items-center justify-center whitespace-nowrap">
            Start Earning Now
        </a>
    </div>

    <div class="absolute -translate-x-1/2"
         style="left: calc(50% - 14px); top: 445px; width: 300px; height: 246px;">

        <div class="absolute flex flex-col gap-[3px] items-start justify-end overflow-hidden
                    pb-[29px] pt-[20px] px-[16px] rounded-[40px] bg-surface"
             style="inset: 0 38.67% 27.64% 0; box-shadow: 0 12px 26px -8px rgba(24,68,62,0.12), 0 32px 56px -16px rgba(24,68,62,0.20);">

            <div class="relative shrink-0 mb-1" style="width: 64px; height: 30px;">
                <img src="{{ asset('images/hero/avatar-mobile-1.svg') }}" alt=""
                     class="absolute w-[30px] h-[30px] rounded-full object-cover ring-2 ring-surface"
                     style="left: 0; top: 0; z-index: 3;" />
                <img src="{{ asset('images/hero/avatar-mobile-2.svg') }}" alt=""
                     class="absolute w-[30px] h-[30px] rounded-full object-cover ring-2 ring-surface"
                     style="left: 17px; top: 0; z-index: 2;" />
                <img src="{{ asset('images/hero/avatar-mobile-1.svg') }}" alt=""
                     class="absolute w-[30px] h-[30px] rounded-full object-cover ring-2 ring-surface"
                     style="left: 34px; top: 0; z-index: 1;" />
            </div>

            <p class="font-heading font-medium text-primary text-[38px] leading-none">1M+</p>
            <p class="font-sans text-muted text-[18px] leading-none">Active Subscribers</p>
        </div>

        <div class="absolute flex items-center gap-[8px] px-[9px] py-[7px] rounded-full overflow-hidden bg-surface"
             style="inset: 77.24% 0 0 26.67%; box-shadow: 0 8px 18px -6px rgba(24,68,62,0.12), 0 22px 44px -12px rgba(24,68,62,0.22);">
            <div class="flex-shrink-0 w-[42px] h-[42px] rounded-full flex items-center justify-center"
                 style="background: rgba(220,235,106,0.6); border: 1px solid rgba(255,255,255,0.5);">
                <span style="font-size: 25px; line-height: 1;">🙂</span>
            </div>
            <div class="flex flex-col whitespace-nowrap">
                <p class="font-heading font-medium text-primary text-[14px] leading-normal">500M+ Call Minutes</p>
                <p class="font-sans text-muted text-[12px] leading-normal">Sponsored minutes delivered</p>
            </div>
        </div>
    </div>

    <div class="absolute flex flex-col gap-[15px]" style="left: 20px; top: 739px;">
        <img src="{{ asset('images/hero/coin-icon-mobile.svg') }}" alt="" class="w-[45px] h-[45px]" />
        <p class="font-heading text-text text-[16px] leading-normal whitespace-nowrap">
            Register for free, activate<br>
            your eSIM, and grow<br>
            your business!
        </p>
    </div>

</div>
