{{-- title is rendered unescaped, so it accepts HTML (e.g. "Our<br>Platform"). --}}
@props(['badge' => '', 'title' => '', 'breadcrumb' => ''])

<section class="relative overflow-hidden bg-background">
    <img src="{{ asset('images/public/hero-gradient.svg') }}" alt=""
         class="absolute -top-10 left-0 w-[120%] max-w-[700px] md:max-w-[1100px] h-auto pointer-events-none select-none">

    <div class="relative max-w-[1280px] mx-auto px-5 md:px-[60px] pt-[140px] md:pt-[220px] pb-[44px] md:pb-[90px]">
        <div class="flex flex-col gap-5 items-start">
            <span class="inline-flex items-center gap-1.5 bg-background border-[0.5px] border-primary rounded-full px-2.5 py-1.5">
                <span class="size-1.5 rounded-full bg-primary"></span>
                <span class="font-sans text-[12px] text-text">{{ $badge }}</span>
            </span>

            <h1 class="font-heading font-semibold text-primary text-[32px] md:text-[56px] leading-[1.05]">{!! $title !!}</h1>

            <nav class="flex items-center gap-2.5 text-[14px]" aria-label="Breadcrumb">
                <a href="{{ route('home') }}" wire:navigate class="font-sans font-semibold text-text hover:text-primary transition-colors">Home</a>
                <span class="w-4 h-px bg-muted/70"></span>
                <span class="font-sans text-muted">{{ $breadcrumb }}</span>
            </nav>
        </div>
    </div>
</section>
