{{-- Admin header. Mirrors the user app-bar minus the global search. --}}
@props(['title' => null, 'subtitle' => null, 'back' => null])

@php
    $admin = auth('admin')->user();
    $initials = strtoupper(substr($admin->name, 0, 2));
    $backHref = is_string($back) ? $back : route('backoffice.dashboard');
@endphp

<div class="sticky top-0 z-30 -mx-4 md:-mx-8 lg:-mx-10 px-4 md:px-8 lg:px-10 bg-background/90 backdrop-blur-md border-b border-muted/10">
    <div class="h-[58px] flex items-center justify-between gap-3">
        <div class="flex items-center gap-2.5 min-w-0 lg:flex-1">
            <button type="button" @click="$dispatch('open-app-menu')"
                    class="lg:hidden size-10 rounded-full bg-surface shadow-sm flex items-center justify-center text-text shrink-0" aria-label="Open menu">
                <i class="ri-menu-2-line text-[20px]"></i>
            </button>
            <a href="{{ route('backoffice.dashboard') }}" wire:navigate class="lg:hidden flex items-center gap-2 min-w-0" aria-label="Admin home">
                <img src="{{ asset('images/public/logo-dark.png') }}" alt="Nextel Connect" class="h-[24px] w-auto object-contain">
                <span class="font-heading font-medium text-primary text-[11px] uppercase tracking-[0.14em]">Backoffice</span>
            </a>
            <button type="button" @click="$dispatch('open-search')"
                    class="hidden lg:flex items-center gap-2.5 h-10 w-full max-w-[380px] rounded-full bg-surface px-4 shadow-sm text-muted hover:bg-surface/70 transition" aria-label="Search">
                <i class="ri-search-line text-[18px]"></i>
                <span class="font-sans text-[13px]">Search users, tokens, withdrawals…</span>
                <span class="ml-auto font-sans text-muted/70 text-[10px] border border-muted/25 rounded px-1 leading-tight">⌘K</span>
            </button>
        </div>

        <div class="flex items-center gap-2.5 shrink-0">
            <button type="button" @click="$dispatch('open-search')"
                    class="lg:hidden size-10 rounded-full bg-surface shadow-sm flex items-center justify-center text-text" aria-label="Search">
                <i class="ri-search-line text-[19px]"></i>
            </button>
            <a href="{{ route('backoffice.account.profile') }}" wire:navigate
               class="size-10 rounded-full bg-primary/10 overflow-hidden flex items-center justify-center shrink-0 ring-1 ring-black/[0.04]" aria-label="Your account">
                @if ($admin->avatar)
                    <img src="{{ $admin->avatar }}" alt="" class="size-full object-cover">
                @else
                    <span class="font-heading font-semibold text-primary text-[14px]">{{ $initials }}</span>
                @endif
            </a>
        </div>
    </div>
</div>

@if ($title)
    <div class="flex items-center gap-3">
        <div class="min-w-0 flex flex-col gap-1.5">
            <p class="font-heading font-medium text-text text-[22px] leading-tight truncate">{{ $title }}</p>
            @if ($subtitle)
                <p class="font-sans text-muted text-[13px] truncate">{{ $subtitle }}</p>
            @endif
        </div>
        @if (isset($action) && ! $action->isEmpty())
            <div class="ml-auto shrink-0">{{ $action }}</div>
        @endif
    </div>
@endif
