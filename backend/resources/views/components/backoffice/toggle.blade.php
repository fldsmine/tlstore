{{--
    Bind with wire:model[.modifiers] or x-model; x-modelable flips instantly client-side and survives morphs.
    Host is <div role="switch"> (not <button>) so it never submits a parent form and sizes to surrounding inputs.
--}}
@props(['label' => null, 'description' => null, 'disabled' => false])

@php
    // wire:model target, used to seed initial Alpine state from the server value. Null when bound via x-model.
    $boundProp = collect($attributes->whereStartsWith('wire:model')->getAttributes())->first();
@endphp

<div
    @if ($boundProp)
        x-data="{ on: !! $wire.$get(@js($boundProp)) }"
    @else
        x-data="{ on: false }"
    @endif
    x-modelable="on"
    {{ $attributes->whereStartsWith(['wire:model', 'x-model', 'aria-label']) }}
    role="switch"
    :aria-checked="on ? 'true' : 'false'"
    aria-disabled="{{ $disabled ? 'true' : 'false' }}"
    tabindex="{{ $disabled ? '-1' : '0' }}"
    @unless ($disabled)
        x-on:click="on = ! on"
        x-on:keydown.enter.prevent="on = ! on"
        x-on:keydown.space.prevent="on = ! on"
    @endunless
    @class([
        'group flex items-center justify-between gap-3 rounded-[12px] bg-background px-3.5 py-3 transition select-none outline-none',
        'cursor-pointer hover:bg-background/70' => ! $disabled,
        'opacity-60 cursor-not-allowed' => $disabled,
    ])
>
    @if ($label || $description)
        <span class="min-w-0 flex flex-col gap-0.5">
            @if ($label)<span class="font-sans text-text text-[13px]">{{ $label }}</span>@endif
            @if ($description)<span class="font-sans text-muted text-[12px] leading-snug">{{ $description }}</span>@endif
        </span>
    @endif

    <span class="relative shrink-0 inline-flex w-10 h-6 rounded-full transition-colors group-focus-visible:ring-2 group-focus-visible:ring-primary/40"
          :class="on ? 'bg-primary' : 'bg-muted/30'">
        <span class="pointer-events-none absolute top-0.5 left-0.5 size-5 rounded-full bg-white shadow-sm transition-transform"
              :class="on && 'translate-x-4'"></span>
    </span>
</div>
