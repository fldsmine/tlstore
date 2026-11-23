<div>
    <x-public.page-hero badge="About Us" title="Our<br>Platform" breadcrumb="About Us" />

    <x-public.shell class="bg-surface">
        <div class="max-w-[760px] mx-auto flex flex-col gap-[30px]">

            <h2 class="font-heading font-medium text-primary text-[24px] md:text-[34px] leading-tight">
                Connecting Brands. Empowering People!
            </h2>

            <div class="flex items-center justify-between gap-4">
                <img src="{{ asset('images/about/network-card.jpg') }}" alt="Earnings dashboard"
                     class="rounded-[12px] w-[55%] max-w-[220px] aspect-[220/207] object-cover">
                <img src="{{ asset('images/about/person.png') }}" alt=""
                     class="w-[40%] max-w-[150px] h-auto object-contain">
            </div>

            <p class="font-heading font-medium text-[18px] md:text-[22px] leading-snug text-primary">
                Who We Are!
            </p>

            <p class="font-sans text-[14px] md:text-[16px] text-muted leading-relaxed">
                Nextel is a virtual telecom operator (VTO) and performance advertising network built for the
                modern digital economy.
            </p>

            <p class="font-sans text-[14px] md:text-[16px] text-muted leading-relaxed">
                By fusing mobile telecom connectivity with direct performance marketing, we&rsquo;ve built an
                ecosystem where high-value brands connect with real, verified audiences. At the same time,
                users turn everyday digital interactions into an economic opportunity to earn money.
            </p>

            <div class="flex flex-col sm:flex-row items-stretch gap-4 py-4">

                {{-- Sponsored-reach mini chart (live HTML — no baked copy) --}}
                <div class="flex-1 rounded-[20px] bg-white shadow-sm p-5 flex flex-col gap-4">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-primary"></span>
                        <span class="font-sans text-[13px] text-muted">Sponsored reach</span>
                    </div>
                    <div class="flex items-end gap-2 h-[120px]">
                        @foreach([45, 70, 55, 88, 62, 96, 78] as $h)
                            <div class="flex-1 rounded-t-[6px] bg-primary/80" style="height: {{ $h }}%"></div>
                        @endforeach
                    </div>
                    <p class="font-sans text-[12px] text-muted">Ad delivery across the week</p>
                </div>

                {{-- Live stat cards mirroring the home hero --}}
                <div class="flex-1 flex flex-col gap-4">
                    <div class="rounded-[20px] bg-white shadow-sm p-5 flex flex-col gap-1.5">
                        <i class="ri-group-line text-primary text-[24px]"></i>
                        <p class="font-heading font-medium text-primary text-[32px] leading-none">1M+</p>
                        <p class="font-sans text-muted text-[14px]">Active Subscribers</p>
                    </div>
                    <div class="rounded-[20px] bg-white shadow-sm p-4 flex items-center gap-3">
                        <span class="shrink-0 w-[42px] h-[42px] rounded-full bg-primary/10 flex items-center justify-center">
                            <i class="ri-phone-line text-primary text-[20px]"></i>
                        </span>
                        <div>
                            <p class="font-heading font-medium text-primary text-[15px] leading-tight">500M+ Call Minutes</p>
                            <p class="font-sans text-muted text-[12px]">Sponsored minutes delivered</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-8">
                <a href="{{ route('dashboard') }}" class="inline-flex flex-col gap-1.5">
                    <span class="inline-flex items-center gap-2 font-sans text-[14px] text-text">
                        View Dashboard
                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 17L17 7M9 7h8v8"/>
                        </svg>
                    </span>
                    <span class="h-px w-full bg-text/40"></span>
                </a>
                <a href="{{ route('contact') }}" wire:navigate class="inline-flex flex-col gap-1.5">
                    <span class="inline-flex items-center gap-2 font-sans text-[14px] text-text">
                        Get Support
                        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 17L17 7M9 7h8v8"/>
                        </svg>
                    </span>
                    <span class="h-px w-full bg-text/40"></span>
                </a>
            </div>
        </div>
    </x-public.shell>

    <x-public.cta />
</div>
