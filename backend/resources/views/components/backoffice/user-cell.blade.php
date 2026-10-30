@props(['user' => null, 'label' => null])

<div class="flex items-center gap-3 min-w-0">
    <span class="size-9 rounded-full bg-primary/10 overflow-hidden flex items-center justify-center font-heading text-primary text-[12px] shrink-0">
        @if ($user?->avatar)
            <img src="{{ $user->avatar }}" alt="" class="size-full object-cover">
        @else
            {{ $user ? strtoupper(substr($user->first_name ?: $user->username, 0, 2)) : '—' }}
        @endif
    </span>
    <div class="min-w-0">
        @if ($label)<p class="font-sans text-muted/70 text-[10px] uppercase tracking-wide leading-tight">{{ $label }}</p>@endif
        <p class="font-sans text-text text-[13px] truncate">{{ $user?->full_name ?? '—' }}</p>
        <p class="font-sans text-muted text-[12px] truncate">{{ $user ? '@'.$user->username : '' }}</p>
    </div>
</div>
