{{-- Auth scaffold with decorative header + curved sheet (Figma 2037:1139). Desktop (lg+) flows inside the layout's brand-rail split. --}}
@props(['title' => '', 'back' => null])

<div class="relative min-h-screen flex flex-col bg-background overflow-hidden lg:min-h-0 lg:overflow-visible lg:block">

    <x-auth.decor :back="$back" />

    {{-- Spacer pushing the sheet below the decorative header (mobile only) --}}
    <div class="h-[128px] shrink-0 lg:hidden"></div>

    <div class="relative z-10 flex-1 bg-background rounded-t-[40px] px-4 pt-[30px] pb-[36px] flex flex-col gap-5 items-center lg:mt-0 lg:flex-none lg:rounded-none lg:px-0 lg:pt-0 lg:pb-0 lg:items-start lg:max-w-[420px] lg:w-full lg:mx-auto">
        {{-- Desktop-only Back; the decorative header's Back is hidden on desktop --}}
        <a href="{{ $back ?? route('home') }}" wire:navigate class="hidden lg:inline-flex items-center gap-2 text-primary self-start mb-2">
            <svg class="w-2.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            <span class="font-sans text-[16px]">Back</span>
        </a>

        @if ($title)
            <h1 class="font-heading font-semibold text-primary text-[28px] leading-none lg:text-[32px] lg:text-left lg:self-start">{{ $title }}</h1>
        @endif
        {{ $slot }}
    </div>
</div>
