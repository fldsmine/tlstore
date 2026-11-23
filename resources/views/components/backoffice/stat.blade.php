@props(['label' => '', 'value' => 0, 'money' => false, 'icon' => 'ri-bar-chart-line', 'hint' => null, 'href' => null, 'tone' => 'primary'])

@php
    $tones = [
        'primary' => 'bg-primary/10 text-primary',
        'success' => 'bg-success/12 text-success',
        'warning' => 'bg-[#d79937]/15 text-[#d79937]',
        'danger' => 'bg-danger/12 text-danger',
        'muted' => 'bg-muted/10 text-muted',
    ];
    $iconClass = $tones[$tone] ?? $tones['primary'];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif
    class="card flex flex-col gap-3 {{ $href ? 'hover:shadow-[0_6px_16px_rgba(194,194,194,0.35)] transition-shadow' : '' }}">
    <div class="flex items-center justify-between">
        <span class="size-10 rounded-[12px] flex items-center justify-center {{ $iconClass }}"><i class="{{ $icon }} text-[20px]"></i></span>
        @if ($hint)
            <span class="font-sans text-[12px] text-muted">{{ $hint }}</span>
        @endif
    </div>
    <div class="flex flex-col gap-0.5">
        <span class="font-heading font-semibold text-text text-[24px] leading-none">{{ $value }}</span>
        <p class="font-sans text-muted text-[13px]">{{ $label }}</p>
    </div>
</{{ $tag }}>
