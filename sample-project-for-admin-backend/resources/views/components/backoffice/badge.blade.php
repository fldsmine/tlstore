{{--
    Small status pill. Props:
      - tone: success | warning | danger | info | blue | purple | muted (default info)
--}}
@props(['tone' => 'info'])

@php
    $tones = [
        'success' => 'bg-success/12 text-success',
        'warning' => 'bg-[#d79937]/15 text-[#d79937]',
        'danger' => 'bg-danger/12 text-danger',
        'info' => 'bg-primary/10 text-primary',
        'blue' => 'bg-[#2f6feb]/12 text-[#2f6feb]',
        'purple' => 'bg-[#7c5cff]/14 text-[#7c5cff]',
        'muted' => 'bg-muted/10 text-muted',
    ];
    $class = $tones[$tone] ?? $tones['info'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-sans text-[12px] {$class}"]) }}>
    {{ $slot }}
</span>
