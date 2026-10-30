{{-- Every money display goes through here: amounts are STORED in USD, this renders them in the
     currency the reader chose. Keeps one conversion path so no screen can drift out of sync.
     Large figures abbreviate (K/M/B) with the exact value on hover — local currencies push ordinary
     balances past six digits, where the raw number stops being readable. --}}
@props([
    'usd' => 0,
    'decimals' => 'auto',   // sub-cent earnings show their real value; whole amounts stay at 2 places
    'abbreviate' => true,   // false pins the exact figure (confirmations, receipts, inputs)
])
@php
    $amount = (float) ($usd ?? 0);
    // `decimals="auto"` keeps sub-cent precision only when the last two places are non-zero.
    $places = $decimals === 'auto'
        ? (\App\Support\DisplayCurrency::isLocal() ? 2 : \App\Support\DisplayCurrency::autoDecimals(\App\Support\DisplayCurrency::convert($amount, null, 6)))
        : (int) $decimals;
    $converted = \App\Support\DisplayCurrency::convert($amount, null, $places);
    $symbol = \App\Support\DisplayCurrency::symbol();

    $exact = $symbol.number_format($converted, $places);
    $isAbbreviated = $abbreviate && abs($converted) >= 100000;
    $display = $isAbbreviated ? $symbol.abbreviate_number($converted) : $exact;

@endphp

@if ($isAbbreviated)
    <span x-data="{ hover: false, pinned: false }"
          @mouseenter="hover = true" @mouseleave="hover = false"
          @click.outside="pinned = false"
          @keydown.escape.window="pinned = false"
          {{ $attributes->class('relative inline-flex items-center gap-1') }}>
        <span>{{ $display }}</span>
        {{-- Touch has no hover, so the exact amount must be reachable by tap. `p-2 -m-2` grows the
             hit area to ~34px without shifting layout; <span> (not <button>) keeps this valid when
             nested inside a surrounding button/link, and prevent.stop stops it navigating. --}}
        <span role="button" tabindex="0" aria-label="Show full amount"
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
