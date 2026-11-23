{{-- Call earnings carry a network logo; other types fall back to a tinted icon. --}}
@props(['tx'])

@php
    $statusColor = [
        'completed' => 'text-success',
        'pending' => 'text-[#D79937]',
        'failed' => 'text-danger',
    ][$tx->status] ?? 'text-muted';
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-2.5 px-2.5 py-2.5']) }}>
    @if ($tx->network_logo)
        <img src="{{ $tx->network_logo }}" alt="" class="size-[45px] rounded-full object-cover shrink-0">
    @else
        <span class="size-[45px] rounded-full flex items-center justify-center shrink-0 {{ $tx->is_credit ? 'bg-success/15 text-success' : 'bg-danger/15 text-danger' }}">
            <i class="{{ $tx->type->icon() }} text-[19px]"></i>
        </span>
    @endif

    <div class="flex-1 min-w-0 flex flex-col">
        <p class="font-sans font-semibold text-[14px] {{ $tx->is_credit ? 'text-success' : 'text-danger' }}">{{ $tx->is_credit ? '+' : '-' }}<x-money :usd="$tx->amount" /></p>
        <p class="font-sans text-muted text-[12px] truncate">{{ $tx->description ?: $tx->type->getName() }}</p>
    </div>

    <div class="flex flex-col items-end shrink-0 text-right">
        <p class="font-sans text-[12px] capitalize {{ $statusColor }}">{{ $tx->status }}</p>
        <p class="font-sans text-muted text-[10px]">{{ $tx->created_at?->format('j M Y, g:i A') }}</p>
    </div>
</div>
