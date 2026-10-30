<div class="hidden md:block relative overflow-hidden bg-background" style="min-height: 100vh;">

    <img src="{{ asset('images/hero/hero-bg.png') }}" alt=""
         class="absolute inset-0 w-full h-full object-cover pointer-events-none" />

    <img src="{{ asset('images/hero/lines-bg-desktop.svg') }}" alt=""
         class="absolute inset-0 w-full h-full object-cover pointer-events-none" />

    {{-- 1440 box keeps the absolute floats aligned on wide screens --}}
    <div class="relative mx-auto" style="max-width: 1440px; min-height: 100vh;">

    <img src="{{ asset('images/hero/decoratives.svg') }}" alt=""
         class="absolute pointer-events-none"
         style="left: 296px; top: 286px; width: 847px; height: 568px;" />

    <div class="relative z-10 flex flex-col items-center gap-[25px]" style="padding-top: 16.3vh;">

        <div class="flex items-center gap-[8px] px-[23px] py-[15px] rounded-[66px] bg-background"
             style="border: 0.5px solid #18443e; box-shadow: 0 10px 25px rgba(24,68,62,0.3);">
            <img src="{{ asset('images/hero/badge-dot.svg') }}" alt="" class="w-[10px] h-[10px] flex-shrink-0" />
            <span class="font-sans text-[18px] text-primary whitespace-nowrap">eSIM verified ad network.</span>
        </div>

        <div class="font-heading font-normal text-[46px] leading-tight text-center max-w-[860px]">
            <p class="text-text">Launch your campaign:</p>
            <p class="text-primary">Reach 100% verified human audiences globally.</p>
        </div>

        <div class="flex items-center gap-[30px]">
            <a href="{{ route('auth.register') }}"
               class="flex items-center gap-[10px] bg-primary text-surface font-heading font-medium text-[24px]
                      px-[30px] py-[20px] rounded-[35px] hover:opacity-90 transition-opacity whitespace-nowrap">
                Start Earning Now
                <img src="{{ asset('images/hero/cta-arrow.svg') }}" alt="" class="w-[16px] h-[16px] flex-shrink-0" />
            </a>
            <a href="{{ route('auth.login') }}"
               class="flex items-center justify-center border border-primary text-primary font-heading font-medium text-[24px]
                      px-[30px] py-[20px] rounded-[35px] hover:bg-primary hover:text-surface transition-colors whitespace-nowrap"
               style="width: 241px;">
                Sign In
            </a>
        </div>
    </div>

    <div class="absolute flex flex-col gap-[15px] z-10" style="left: 321px; top: 701px;">
        <img src="{{ asset('images/hero/coin-icon.svg') }}" alt="" class="w-[45px] h-[45px]" />
        <p class="font-heading text-text text-[18px] leading-normal whitespace-nowrap">
            Register for free, activate<br>
            your eSIM, and grow<br>
            your business!
        </p>
    </div>

    <div class="absolute flex flex-col gap-[8px] items-end z-10" style="left: 932px; top: 691px;">
        <div class="bg-surface flex items-center gap-[10px] p-[10px] rounded-[23px]">
            <img src="{{ asset('images/hero/icon-customizable.svg') }}" alt="" class="w-[20px] h-[20px] flex-shrink-0" />
            <span class="font-heading text-text text-[18px] whitespace-nowrap">CUSTOMIZABLE</span>
        </div>
        <div class="bg-surface flex items-center gap-[10px] p-[10px] rounded-[23px]">
            <img src="{{ asset('images/hero/icon-flexible.svg') }}" alt="" class="w-[16px] h-[14px] flex-shrink-0" />
            <span class="font-heading text-text text-[18px] whitespace-nowrap">FLEXIBLE</span>
        </div>
        <div class="bg-surface flex items-center gap-[10px] p-[10px] rounded-[23px]">
            <img src="{{ asset('images/hero/icon-fullcontrol.svg') }}" alt="" class="w-[20px] h-[20px] flex-shrink-0" />
            <span class="font-heading text-text text-[18px] whitespace-nowrap">FULLCONTROL</span>
        </div>
    </div>

    <div class="absolute z-10 flex items-center justify-end rounded-[35px]"
         style="left: 558px; top: 672px; width: 80px; height: 38px; background: #dceb6a; padding: 4px 5px;">
        <div class="rounded-[35px] bg-surface" style="width: 39px; height: 30px;"></div>
    </div>

    <div class="absolute bg-surface rounded-[12px] z-10"
         style="left: 628px; top: 635px; width: 184px; height: 179px;">
        <div class="absolute border-b border-border" style="left: 10px; right: 10px; top: 29px;"></div>
        <div class="absolute border-b border-border" style="left: 10px; right: 10px; top: 49px;"></div>
        <div class="absolute border-b border-border" style="left: 10px; right: 10px; top: 69px;"></div>
        <p class="absolute font-heading font-semibold text-primary leading-none whitespace-nowrap"
           style="font-size: 38px; left: 10px; top: 89px;">1M+</p>
        <p class="absolute font-heading text-text leading-none whitespace-nowrap"
           style="font-size: 18px; left: 11px; top: 137px;">Active Subscribers</p>
        <img src="{{ asset('images/hero/avatar.png') }}" alt=""
             class="absolute rounded-full object-cover"
             style="width: 50px; height: 50px; left: 134px; top: 101px;" />
    </div>

    <div class="absolute bg-surface flex items-center gap-2 rounded-[12px] z-10"
         style="left: 628px; top: 824px; width: 184px; padding: 5px 7px;">
        <div class="flex-shrink-0 flex items-center justify-center rounded-full"
             style="width: 30px; height: 31px; background: rgba(220,235,106,0.2);">
            <span style="font-size: 16px; line-height: 1;">🙂</span>
        </div>
        <div class="flex flex-col whitespace-nowrap">
            <p class="font-sans font-medium text-primary leading-snug" style="font-size: 16px;">Track Activity</p>
            <p class="font-sans text-text leading-snug" style="font-size: 14px;">Your earning hub</p>
        </div>
    </div>

    </div>
</div>
