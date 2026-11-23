@props(['label' => '', 'value' => 0, 'money' => false, 'icon' => 'ri-bar-chart-line', 'hint' => null, 'href' => null, 'tone' => 'primary', 'hintFull' => null])

@php
    $tones = [
        'primary' => 'bg-primary/10 text-primary',
        'success' => 'bg-success/12 text-success',
        'warning' => 'bg-[#d79937]/15 text-[#d79937]',
        'danger' => 'bg-danger/12 text-danger',
    ];
    $iconClass = $tones[$tone] ?? $tones['primary'];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif
    class="bg-surface rounded-[20px] p-5 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)] flex flex-col gap-3 {{ $href ? 'hover:shadow-[0_6px_16px_rgba(194,194,194,0.25)] transition-shadow' : '' }}">
    <div class="flex items-center justify-between">
        <span class="size-10 rounded-[12px] flex items-center justify-center {{ $iconClass }}"><i class="{{ $icon }} text-[20px]"></i></span>
        @if ($hint)
            <span class="font-sans text-[12px] text-muted {{ $hintFull ? 'cursor-help' : '' }}" @if ($hintFull) title="{{ $hintFull }}" @endif>{{ $hint }}</span>
        @endif
    </div>
    <div class="flex flex-col gap-0.5">
        <x-figure :value="$value" :money="$money" class="font-heading font-semibold text-text text-[24px] leading-none" />
        <p class="font-sans text-muted text-[13px]">{{ $label }}</p>
    </div>
</{{ $tag }}>
