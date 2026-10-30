@props(['member'])

@php
    $active = (bool) $member->package_id;
    $initials = strtoupper(substr($member->first_name ?: $member->username, 0, 2));
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-3 px-3 py-2.5']) }}>
    <span class="size-10 rounded-full bg-primary/10 overflow-hidden flex items-center justify-center shrink-0">
        @if ($member->avatar)
            <img src="{{ $member->avatar }}" alt="" class="size-full object-cover">
        @else
            <span class="font-heading font-semibold text-primary text-[13px]">{{ $initials }}</span>
        @endif
    </span>
    <div class="flex-1 min-w-0">
        <p class="font-sans text-text text-[14px] truncate">{{ $member->full_name }}</p>
        <p class="font-sans text-muted text-[11px] truncate">&#64;{{ $member->username }} · joined {{ $member->created_at?->format('j M Y, g:i A') }}</p>
    </div>
    <span class="shrink-0 font-sans text-[11px] rounded-full px-2.5 py-1 {{ $active ? 'bg-success/10 text-success' : 'bg-[#d79937]/10 text-[#d79937]' }}">
        {{ $active ? 'Active' : 'Pending' }}
    </span>
</div>
