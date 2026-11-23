<div>
    @php
        $ctaPrimary = 'inline-flex items-center justify-center gap-2 h-[52px] px-7 rounded-full bg-primary text-white font-sans font-semibold text-[15px] hover:bg-primary/90 transition-colors';
        $ctaGhost = 'inline-flex items-center justify-center gap-2 h-[52px] px-7 rounded-full border border-muted/25 text-text font-sans font-semibold text-[15px] hover:border-primary/50 hover:text-primary transition-colors';
        $plans = array_values(array_filter($products, fn ($item) => $item['source'] === 'package'));
        $extras = array_values(array_filter($products, fn ($item) => $item['source'] === 'product'));
    @endphp

    {{-- ── Hero ─────────────────────────────────────────────────────────────────────── --}}
    <section class="relative overflow-hidden">
        <img src="{{ asset('images/public/hero-gradient.svg') }}" alt=""
             class="absolute -top-24 right-0 w-[130%] max-w-[900px] md:max-w-[1300px] h-auto pointer-events-none select-none opacity-70">

        <div class="relative max-w-[1280px] mx-auto px-5 md:px-[60px] pt-12 md:pt-20 pb-10 md:pb-16">
            <div class="flex flex-col items-start gap-5 max-w-[760px]">
                <span class="inline-flex items-center gap-1.5 bg-background border-[0.5px] border-primary rounded-full px-3 py-1.5">
                    <span class="size-1.5 rounded-full bg-primary"></span>
                    <span class="font-sans text-[12px] text-text">{{ $hero['eyebrow'] }}</span>
                </span>

                <h1 class="font-heading font-semibold text-primary text-[34px] md:text-[60px] leading-[1.03]">
                    {!! $hero['heading'] !!}
                </h1>

                <p class="font-sans text-muted text-[15px] md:text-[17px] leading-relaxed max-w-[620px]">{{ $hero['body'] }}</p>

                <div class="flex flex-wrap items-center gap-3">
                    <a href="#products" class="{{ $ctaPrimary }}">
                        <i class="ri-shopping-bag-3-line text-[17px]"></i>
                        {{ $hero['primary_cta'] }}
                    </a>
                    <a href="{{ product_main_url() }}" class="{{ $ctaGhost }}">
                        <i class="ri-smartphone-line text-[17px]"></i>
                        {{ $hero['secondary_cta'] }}
                    </a>
                </div>

                <div class="flex flex-wrap items-center gap-x-5 gap-y-2 font-sans text-[13px] text-muted">
                    <span class="inline-flex items-center gap-1.5"><i class="ri-shield-check-line text-primary"></i> Secure card payment</span>
                    <span class="inline-flex items-center gap-1.5"><i class="ri-mail-send-line text-primary"></i> Sent to your email</span>
                    <span class="inline-flex items-center gap-1.5"><i class="ri-user-unfollow-line text-primary"></i> Guest checkout</span>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Features ─────────────────────────────────────────────────────────────────── --}}
    <section id="features" class="bg-surface/40 border-y border-muted/10">
        <div class="max-w-[1280px] mx-auto px-5 md:px-[60px] py-14 md:py-20">
            <div class="flex flex-col gap-3 mb-10 md:mb-14">
                <p class="font-heading text-muted text-[15px] uppercase tracking-[0.16em]">{{ $features['eyebrow'] }}</p>
                <h2 class="font-heading font-semibold text-[28px] md:text-[42px] leading-tight max-w-[760px]">
                    <span class="text-primary">{{ $features['heading'] }}</span>
                    <span class="text-muted/70">{{ $features['heading_muted'] }}</span>
                </h2>
                <p class="font-sans text-muted text-[14px] md:text-[16px] leading-relaxed max-w-[680px]">{{ $features['body'] }}</p>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-5">
                @foreach ($features['items'] as $feature)
                    <article wire:key="feature-{{ $loop->index }}"
                             class="rounded-[20px] md:rounded-[24px] border border-primary/15 bg-background p-6 flex flex-col gap-3">
                        <span class="size-11 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                            <i class="{{ $feature['icon'] }} text-[20px]"></i>
                        </span>
                        <h3 class="font-heading font-medium text-text text-[16px]">{{ $feature['title'] }}</h3>
                        <p class="font-sans text-muted text-[13.5px] leading-relaxed">{{ $feature['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── Products: what this store sells ──────────────────────────────────────────── --}}
    <section id="products" class="bg-background">
        <div class="max-w-[1280px] mx-auto px-5 md:px-[60px] py-14 md:py-20">
            <div class="flex flex-col gap-3 mb-10">
                <p class="font-heading text-muted text-[15px] uppercase tracking-[0.16em]">{{ $products_heading['eyebrow'] }}</p>
                <h2 class="font-heading font-semibold text-primary text-[28px] md:text-[42px] leading-tight max-w-[820px]">
                    {{ $products_heading['heading'] }}
                </h2>
                <p class="font-sans text-muted text-[14px] md:text-[16px] leading-relaxed max-w-[720px]">{{ $products_heading['body'] }}</p>
            </div>

            @if ($products === [])
                <div class="rounded-[20px] border border-muted/15 bg-surface/40 p-8 text-center">
                    <p class="font-heading font-medium text-text text-[16px]">Our store is momentarily closed</p>
                    <p class="font-sans text-muted text-[13px] mt-1.5">Please check back shortly, or contact support and we will sort you out.</p>
                </div>
            @else
                {{-- eSIM plans first: they are what the network is built around. --}}
                @if ($plans !== [])
                    <p class="font-heading font-medium text-text text-[18px] mb-5">{{ $plans_heading }}</p>
                    <div class="grid sm:grid-cols-2 gap-5 md:gap-6 mb-12 md:mb-16">
                        @foreach ($plans as $plan)
                            @include('livewire.product.partials.card', ['item' => $plan])
                        @endforeach
                    </div>
                @endif

                {{-- …then everything else the catalog sells, image cards first. --}}
                @if ($extras !== [])
                    <p class="font-heading font-medium text-text text-[18px] mb-5">{{ $extras_heading }}</p>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5 md:gap-6">
                        @foreach ($extras as $item)
                            @include('livewire.product.partials.card', ['item' => $item])
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    </section>

    {{-- ── Benefits ─────────────────────────────────────────────────────────────────── --}}
    <section class="bg-surface/40 border-y border-muted/10">
        <div class="max-w-[1280px] mx-auto px-5 md:px-[60px] py-14 md:py-20">
            <div class="grid lg:grid-cols-[0.85fr_1.15fr] gap-10 lg:gap-16 items-center">
                <div class="flex flex-col gap-4">
                    <p class="font-heading text-muted text-[15px] uppercase tracking-[0.16em]">{{ $benefits['eyebrow'] }}</p>
                    <h2 class="font-heading font-semibold text-[28px] md:text-[40px] leading-tight">
                        <span class="text-primary">{{ $benefits['heading'] }}</span>
                    </h2>
                    <p class="font-sans text-muted text-[14px] md:text-[16px] leading-relaxed">{{ $benefits['body'] }}</p>
                    @if ($benefits['note'])
                        <p class="font-heading font-semibold text-primary text-[30px] md:text-[38px] leading-none">{{ $benefits['note'] }}</p>
                    @endif
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach ($benefits['items'] as $benefit)
                        <article wire:key="benefit-{{ $loop->index }}" class="rounded-[20px] border border-primary/15 bg-background p-5 flex flex-col gap-2.5">
                            <span class="size-10 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                                <i class="{{ $benefit['icon'] }} text-[18px]"></i>
                            </span>
                            <h3 class="font-heading font-medium text-text text-[15px]">{{ $benefit['title'] }}</h3>
                            <p class="font-sans text-muted text-[13px] leading-relaxed">{{ $benefit['body'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ── FAQ ──────────────────────────────────────────────────────────────────────── --}}
    <section id="faq" class="bg-background" x-data="{ open: null }">
        <div class="max-w-[1280px] mx-auto px-5 md:px-[60px] py-14 md:py-20 grid lg:grid-cols-[1fr_1.15fr] gap-10 lg:gap-16 items-start">
            <div class="flex flex-col gap-3">
                <p class="font-heading text-muted text-[15px] uppercase tracking-[0.16em]">FAQ</p>
                <h2 class="font-heading font-semibold text-primary text-[28px] md:text-[40px] leading-tight">Frequently asked questions</h2>
                <p class="font-sans text-muted text-[14px] md:text-[15px] leading-relaxed max-w-[460px]">
                    Everything buyers ask us before they pay — and the honest answers.
                </p>
                <p class="font-sans text-muted text-[13.5px] leading-relaxed">
                    Still unsure?
                    @if (config('settings.contact.email'))
                        Email <a href="mailto:{{ config('settings.contact.email') }}" class="text-primary hover:underline">{{ config('settings.contact.email') }}</a>
                        and a human replies.
                    @else
                        Use the contact page on the main site and a human replies.
                    @endif
                </p>
            </div>

            <div class="w-full">
                @foreach ($faqs as $i => $faq)
                    <div wire:key="faq-{{ $i }}" class="border-t border-muted/15 last:border-b">
                        <button type="button"
                                @click="open === {{ $i }} ? open = null : open = {{ $i }}"
                                class="w-full flex items-center justify-between gap-5 px-1 py-5 text-left">
                            <span class="font-heading font-medium text-text text-[16px] md:text-[17px] leading-snug flex-1 min-w-0">{{ $faq['q'] }}</span>
                            <span class="size-9 rounded-full border flex-shrink-0 flex items-center justify-center transition-colors"
                                  :class="open === {{ $i }} ? 'bg-text border-text' : 'border-muted/25'">
                                <i x-show="open !== {{ $i }}" class="ri-add-line text-[16px] text-text"></i>
                                <i x-show="open === {{ $i }}" x-cloak class="ri-subtract-line text-[16px] text-background"></i>
                            </span>
                        </button>
                        <div x-show="open === {{ $i }}" x-collapse x-cloak class="px-1 pb-5">
                            <p class="font-sans text-muted text-[14px] leading-relaxed">{{ $faq['a'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── CTA ──────────────────────────────────────────────────────────────────────── --}}
    <section class="bg-background px-5 md:px-[60px] pb-14 md:pb-20">
        <div class="relative max-w-[1280px] mx-auto rounded-[24px] md:rounded-[32px] overflow-hidden bg-primary text-white">
            <img src="{{ asset('images/public/cta-bg.png') }}" alt="" class="absolute inset-0 w-full h-full object-cover opacity-20 pointer-events-none">

            <div class="relative px-7 md:px-14 py-12 md:py-16 flex flex-col gap-5 max-w-[720px]">
                <span class="inline-flex items-center gap-1.5 self-start rounded-full bg-white/15 px-3 py-1.5 font-sans text-[12px]">
                    <i class="ri-smartphone-line"></i>
                    {{ $final['eyebrow'] }}
                </span>

                <h2 class="font-heading font-semibold text-[28px] md:text-[44px] leading-[1.08] uppercase">{{ $final['heading'] }}</h2>
                <p class="font-sans text-white/85 text-[14px] md:text-[16px] leading-relaxed">{{ $final['body'] }}</p>

                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ product_main_url() }}" class="inline-flex items-center justify-center gap-2 h-[50px] px-6 rounded-full bg-white text-primary font-sans font-semibold text-[14px] hover:bg-white/90 transition-colors">
                        <i class="ri-download-2-line text-[17px]"></i>
                        {{ $final['primary_cta'] }}
                    </a>
                    <a href="#products" class="inline-flex items-center justify-center gap-2 h-[50px] px-6 rounded-full border border-white/35 text-white font-sans font-semibold text-[14px] hover:bg-white/10 transition-colors">
                        Browse the store
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>
