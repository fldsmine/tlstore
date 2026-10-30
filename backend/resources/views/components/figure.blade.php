{{-- Numbers >= 100,000 abbreviate (K/M/B/T) with an exact-value popover; the info icon stops click
     propagation so it never triggers a surrounding link. --}}
@props(['value' => 0, 'money' => false, 'precision' => 2])

@php
    $number = (float) $value;
    $prefix = $money ? '$' : '';
    $display = $prefix.abbreviate_number($number, (int) $precision);
    $isAbbreviated = abs($number) >= 100000;
    $exact = $prefix.number_format($number, $money ? 2 : 0);
@endphp

@if ($isAbbreviated)
    <span x-data="{ hover: false, pinned: false }"
          @mouseenter="hover = true" @mouseleave="hover = false"
          @click.outside="pinned = false"
          @keydown.escape.window="pinned = false"
          {{ $attributes->class('relative inline-flex items-center gap-1') }}>
        <span>{{ $display }}</span>
        {{-- Touch has no hover, so the exact value must be reachable by tap. `p-2 -m-2` grows the
             hit area to ~34px without shifting layout; <span> (not <button>) keeps this valid when
             nested inside a surrounding button/link. --}}
        <span role="button" tabindex="0" aria-label="Show full value"
              @click.prevent.stop="pinned = ! pinned"
              @keydown.enter.prevent.stop="pinned = ! pinned"
              class="shrink-0 leading-none cursor-pointer p-2 -m-2 text-muted hover:text-primary transition-colors">
            <i class="ri-information-line text-[14px] align-middle"></i>
        </span>
        <span x-show="hover || pinned" x-cloak
              @click.prevent.stop="pinned = false"
              class="absolute bottom-full left-1/2 z-30 mb-1.5 -translate-x-1/2 whitespace-nowrap rounded-[10px] bg-text px-2.5 py-1.5 font-sans text-[12px] font-normal leading-none text-surface shadow-lg">
            {{ $exact }}
        </span>
    </span>
@else
    <span {{ $attributes }}>{{ $display }}</span>
@endif
