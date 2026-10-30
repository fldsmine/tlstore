@props([
    'heading' => 'Get Started In Minutes',
    'sub' => 'Sign up for free, choose your package, and start earning.',
])

<section class="py-[50px] md:py-[80px]">
    <div class="max-w-[1280px] mx-auto px-5 md:px-[60px]">
        <div class="relative overflow-hidden rounded-[20px] md:rounded-[32px] px-5 md:px-16 py-10 md:py-[72px] flex flex-col items-center gap-[30px] text-center">
            <img src="{{ asset('images/public/cta-bg.png') }}" alt="" class="absolute inset-0 w-full h-full object-cover pointer-events-none">

            <div class="relative z-10 flex flex-col gap-2.5 items-center">
                <h2 class="font-heading font-medium text-[#edefee] text-[24px] md:text-[40px] leading-tight">{{ $heading }}</h2>
                <p class="font-sans text-surface text-[12px] md:text-[16px] max-w-md leading-relaxed">{{ $sub }}</p>
            </div>

            <a href="{{ route('auth.register') }}" wire:navigate
               class="relative z-10 w-full md:w-auto md:px-20 h-[50px] md:h-[56px] bg-white rounded-[39px] flex items-center justify-center font-sans font-semibold text-primary text-[14px] md:text-[16px] hover:bg-secondary transition-colors">
                Start Earning Now
            </a>
        </div>
    </div>
</section>
