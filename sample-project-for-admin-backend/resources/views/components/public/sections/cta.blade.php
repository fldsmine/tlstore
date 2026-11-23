<section class="md:hidden bg-background py-[50px] px-[16px]">
    <div class="relative rounded-[20px] overflow-hidden px-[20px] py-[40px]">

        <img src="{{ asset('images/cta/cta-mobile-bg.jpg') }}"
             alt=""
             class="absolute inset-0 w-full h-full object-cover pointer-events-none rounded-[20px]" />

        <div class="relative z-10 flex flex-col gap-[30px] items-center text-center">
            <div class="flex flex-col gap-[10px] items-center">
                <p class="font-heading font-medium text-[#edefee] text-[24px] leading-tight">Get Started In Minutes</p>
                <p class="font-sans text-[#faf9f8] text-[12px] leading-relaxed">Sign up for free, choose your package, and start earning.</p>
            </div>
            <a href="{{ route('auth.register') }}"
               class="flex items-center justify-center w-full h-[50px] font-sans font-semibold text-primary text-[14px] rounded-[39px] bg-white">
                Start Earning Now
            </a>
        </div>

    </div>
</section>

<section class="hidden md:block bg-background pt-[20px] pb-[100px]">
    <div class="max-w-[1280px] mx-auto px-[60px]">
    <div class="relative rounded-[60px] overflow-hidden flex flex-col items-center justify-center gap-[40px] px-[100px] py-[90px] w-full">

        <img src="{{ asset('images/cta/cta-desktop-bg.jpg') }}"
             alt=""
             class="absolute inset-0 w-full h-full object-cover pointer-events-none rounded-[60px]" />

        <p class="relative z-10 font-heading font-semibold text-[#faf9f8] text-[50px] leading-tight whitespace-nowrap">Get Started in Minutes</p>
        <p class="relative z-10 font-heading font-medium text-[#faf9f8] text-[24px] text-center leading-relaxed max-w-3xl">
            Sign up for free, choose your package, and start earning. Your dashboard and personalized website are ready to track your success.
        </p>
        <a href="{{ route('auth.register') }}"
           class="relative z-10 inline-flex items-center justify-center font-heading font-medium text-primary text-[18px] rounded-[35px] bg-background px-[30px] py-[20px] w-[306px] hover:opacity-90 transition-opacity">
            Start Earning Now
        </a>

    </div>
    </div>
</section>
