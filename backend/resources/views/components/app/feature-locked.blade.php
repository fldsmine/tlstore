@props([
    'title' => 'This feature is not available',
    'message' => null,
    'icon' => 'ri-time-line',
])

<div {{ $attributes->class('relative overflow-hidden bg-surface rounded-[24px] w-full px-7 py-16 lg:py-20 flex flex-col items-center text-center gap-5 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]') }}>
    <div class="pointer-events-none absolute -top-14 left-1/2 -translate-x-1/2 size-48 rounded-full bg-secondary/30 blur-3xl"></div>

    <div class="relative">
        <span class="pointer-events-none absolute inset-0 rounded-[24px] bg-secondary/40 blur-lg"></span>
        <span class="relative flex size-[78px] items-center justify-center rounded-[24px] bg-gradient-to-br from-secondary/60 via-secondary/25 to-transparent ring-1 ring-primary/10 shadow-[0_8px_20px_-8px_rgba(24,68,62,0.3)]">
            <i class="{{ $icon }} text-primary text-[34px]"></i>
        </span>
    </div>

    <div class="relative flex flex-col gap-2">
        <p class="font-heading font-semibold text-text text-[20px] leading-tight">{{ $title }}</p>
        @if (filled($message))
            <p class="font-sans text-muted text-[13.5px] leading-relaxed max-w-[380px] whitespace-pre-line">{{ $message }}</p>
        @endif
    </div>

    {{ $slot }}
</div>
