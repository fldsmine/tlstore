{{-- Plain auth scaffold, no decorative header (Figma 10:549 / 11:124 / 11:181 / 201:964). Desktop (lg+) flows inside the layout's brand-rail split. --}}
@props(['title' => '', 'subtitle' => null, 'back' => null])

<div class="min-h-screen flex flex-col gap-[50px] bg-background pt-[60px] px-4 pb-[36px] lg:min-h-0 lg:pt-0 lg:px-0 lg:pb-0 lg:gap-8 lg:max-w-[420px] lg:w-full lg:mx-auto">
    <a href="{{ $back ?? route('home') }}" wire:navigate class="inline-flex items-center gap-2.5 text-text shrink-0 w-fit">
        <svg class="w-2.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
        </svg>
        <span class="font-sans text-[16px]">Back</span>
    </a>

    @if ($title)
        <div class="flex flex-col gap-[5px] w-full shrink-0">
            <h1 class="font-heading font-medium text-text text-[24px] leading-none lg:text-[28px]">{{ $title }}</h1>
            @if ($subtitle)
                <p class="font-sans text-[14px] text-muted">{{ $subtitle }}</p>
            @endif
        </div>
    @endif

    {{ $slot }}
</div>
