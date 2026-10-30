{{-- region: 'nigeria' | 'foreign' --}}
@props(['region' => 'nigeria'])

@php
    $foreign = $region === 'foreign';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-sans text-[11px] whitespace-nowrap '.($foreign ? 'bg-[#2f6feb]/12 text-[#2f6feb]' : 'bg-success/12 text-success')]) }}>
    {{ $foreign ? 'Foreign' : 'Nigeria' }}
</span>
