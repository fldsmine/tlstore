<section id="how-it-works" class="bg-surface">

    <div class="md:hidden flex flex-col gap-[60px] px-[20px] py-[100px]">

        <div class="flex flex-col gap-[20px] items-center text-center w-full">
            <div class="flex flex-col gap-[10px] items-center">
                <p class="font-sans font-semibold text-primary text-[14px]">The Process</p>
                <p class="font-heading font-medium text-text text-[36px] leading-tight">How it Works?</p>
            </div>
            <p class="font-sans text-muted text-[14px] leading-relaxed">
                Follow our streamlined process to activate your eSIM and start earning in minutes.
            </p>
        </div>

        <div class="flex flex-col gap-[60px] w-full">

            <div class="flex flex-col gap-[20px]">
                <div class="flex items-center gap-[6px]">
                    <img src="{{ asset('images/how-it-works/step-1-icon.svg') }}" alt="" class="w-[40px] h-[40px]" />
                    <span class="font-heading font-medium text-[24px] leading-none" style="color: rgba(24,68,62,0.1)">01</span>
                </div>
                <div class="flex flex-col gap-[10px]">
                    <p class="font-heading font-medium text-text text-[24px] leading-tight">Register for free.</p>
                    <p class="font-sans text-muted text-[14px] leading-relaxed">Create your account in just a few steps and access our complete suite of telecom products within seconds.</p>
                </div>
            </div>

            <div class="flex flex-col gap-[20px]">
                <div class="flex items-center gap-[6px]">
                    <img src="{{ asset('images/how-it-works/step-2-icon.svg') }}" alt="" class="w-[40px] h-[40px]" />
                    <span class="font-heading font-medium text-[24px] leading-none" style="color: rgba(24,68,62,0.1)">02</span>
                </div>
                <div class="flex flex-col gap-[10px]">
                    <p class="font-heading font-medium text-text text-[24px] leading-tight">Activate eSIM</p>
                    <p class="font-sans text-muted text-[14px] leading-relaxed">Once registered, navigate to your dashboard and select Activate eSIM to browse and connect with our network of verified eSIM retailers.</p>
                </div>
            </div>

            <div class="flex flex-col gap-[20px]">
                <div class="flex items-center gap-[6px]">
                    <img src="{{ asset('images/how-it-works/step-3-icon.svg') }}" alt="" class="w-[40px] h-[40px]" />
                    <span class="font-heading font-medium text-[24px] leading-none" style="color: rgba(24,68,62,0.1)">03</span>
                </div>
                <div class="flex flex-col gap-[10px] pb-[20px]">
                    <p class="font-heading font-medium text-text text-[24px] leading-tight">Connect, grow your business, and earn.</p>
                    <p class="font-sans text-muted text-[14px] leading-relaxed">Launch targeted ads, connect with verified users, and let our users make the ad worth every penny. The value they deliver stands as an economic opportunity for them to earn.</p>
                </div>
            </div>

        </div>

        <div class="flex flex-col gap-[20px] items-center justify-center w-full">
            <p class="font-heading text-muted text-[16px] text-center">READY TO START?</p>
            <a href="{{ route('auth.register') }}"
               class="inline-flex items-center gap-[16px] bg-primary text-surface font-heading font-medium text-[15px] rounded-[90px] px-[30px] py-[20px]">
                Create Your Free Account
                <img src="{{ asset('images/how-it-works/arrow.svg') }}" alt="" class="w-[18px] h-[18px]" />
            </a>
        </div>

    </div>

    <div class="hidden md:block py-[150px]">
        <div class="max-w-[1280px] mx-auto px-[60px]">

        <div class="text-center mb-[80px]">
            <p class="font-sans text-[24px] font-semibold text-primary mb-3">The Process</p>
            <h2 class="font-heading font-medium text-text text-[50px] leading-tight mb-5">How it Works?</h2>
            <p class="font-sans text-muted text-[24px] max-w-2xl mx-auto">Follow our streamlined process to activate your eSIM and start earning in minutes.</p>
        </div>

        {{-- Fixed height anchors the absolutely-placed steps --}}
        <div class="relative mx-auto" style="height: 739px; max-width: 1140px;">

            <div class="absolute left-1/2 top-0 bottom-0 w-px -translate-x-1/2" style="background: rgba(140,140,140,0.25);"></div>

            {{-- Step 1 — text left, icon+number right --}}
            <div class="absolute" style="left: 0; top: 36px; width: 420px;">
                <h3 class="font-heading font-medium text-text text-[32px] mb-3">Register for free.</h3>
                <p class="font-sans text-muted text-[22px] leading-relaxed">Create your account in just a few steps and access our complete suite of telecom products within seconds.</p>
            </div>
            <div class="absolute flex items-center gap-4" style="left: 620px; top: 36px;">
                <div class="w-[80px] h-[80px] rounded-full border border-primary/25 flex items-center justify-center flex-shrink-0">
                    <svg class="w-9 h-9 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                    </svg>
                </div>
                <span class="font-heading font-medium text-[46px] leading-none select-none" style="color: rgba(24,68,62,0.1)">01</span>
            </div>

            {{-- Step 2 — number+icon left, text right --}}
            <div class="absolute flex items-center gap-4" style="left: 340px; top: 323px;">
                <span class="font-heading font-medium text-[46px] leading-none select-none" style="color: rgba(24,68,62,0.1)">02</span>
                <div class="w-[80px] h-[80px] rounded-full border border-primary/25 flex items-center justify-center flex-shrink-0">
                    <svg class="w-9 h-9 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
            </div>
            <div class="absolute" style="left: 620px; top: 323px; width: 420px;">
                <h3 class="font-heading font-medium text-text text-[32px] mb-3">Activate eSIM</h3>
                <p class="font-sans text-muted text-[22px] leading-relaxed">Once registered, navigate to your dashboard and select Activate eSIM to browse and connect with our network of verified eSIM retailers.</p>
            </div>

            {{-- Step 3 — text left, icon+number right --}}
            <div class="absolute" style="left: 0; bottom: 0; width: 420px;">
                <h3 class="font-heading font-medium text-text text-[32px] mb-3">Connect, grow your business, and earn.</h3>
                <p class="font-sans text-muted text-[22px] leading-relaxed">Launch targeted ads, connect with verified users, and let our users make the ad worth every penny. The value they deliver stands as an economic opportunity for them to earn.</p>
            </div>
            <div class="absolute flex items-center gap-4" style="left: 600px; top: 597px;">
                <div class="w-[80px] h-[80px] rounded-full border border-primary/25 flex items-center justify-center flex-shrink-0">
                    <svg class="w-9 h-9 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <span class="font-heading font-medium text-[46px] leading-none select-none" style="color: rgba(24,68,62,0.1)">03</span>
            </div>

        </div>

        <div class="text-center mt-[80px]">
            <p class="font-heading text-[22px] text-muted mb-6">READY TO START?</p>
            <a href="{{ route('auth.register') }}"
               class="inline-flex items-center gap-[16px] bg-primary text-surface font-heading font-medium text-[24px] rounded-[90px] px-[40px] py-[29px]">
                Create Your Free Account
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                </svg>
            </a>
        </div>
        </div>

    </div>

</section>
