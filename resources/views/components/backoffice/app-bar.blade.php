{{-- Admin header: mobile menu button, small brand on mobile, search hint (desktop),
     account shortcut. No customer-facing chrome. --}}
@props(['title' => null, 'subtitle' => null, 'back' => null])

@php
    $admin = auth('admin')->user();
    $initials = $admin->initials();
    $backHref = is_string($back) ? $back : route('backoffice.dashboard');
@endphp

<div class="sticky top-0 z-30 -mx-4 md:-mx-8 lg:-mx-10 px-4 md:px-8 lg:px-10 bg-background/90 backdrop-blur-md border-b border-muted/10">
    <div class="h-[58px] flex items-center justify-between gap-3">
        <div class="flex items-center gap-2.5 min-w-0 lg:flex-1">
            <button type="button" @click="$dispatch('open-app-menu')"
                    class="lg:hidden size-10 rounded-full bg-surface shadow-sm flex items-center justify-center text-text shrink-0" aria-label="Open menu">
                <i class="ri-menu-2-line text-[20px]"></i>
            </button>
            <a href="{{ route('backoffice.dashboard') }}" wire:navigate class="lg:hidden inline-flex items-center gap-2 min-w-0 bg-surface rounded-lg px-2 py-1 ring-1 ring-black/5" aria-label="Admin home">
                <img src="{{ asset('images/logo-long.png') }}" alt="TrustLens Store" class="h-[22px] w-auto object-contain">
            </a>
            <div class="hidden lg:flex items-center gap-2.5 h-10 w-full max-w-[420px] rounded-full bg-surface px-4 shadow-sm text-muted">
                <i class="ri-dashboard-3-line text-[18px]"></i>
                <span class="font-sans text-[13px] truncate">{{ config('backoffice.name') }} · authorized personnel only</span>
            </div>
        </div>

        <div class="flex items-center gap-2.5 shrink-0">
            <a href="{{ route('backoffice.account.profile') }}" wire:navigate
               class="size-10 rounded-full bg-primary/10 overflow-hidden flex items-center justify-center shrink-0 ring-1 ring-black/[0.04]" aria-label="Your account">
                <span class="font-heading font-semibold text-primary text-[14px]">{{ $initials }}</span>
            </a>
        </div>
    </div>
</div>

@if ($title)
    <div class="flex items-center gap-3">
        @if ($back)
            <a href="{{ $backHref }}" wire:navigate class="size-10 rounded-full bg-surface shadow-sm flex items-center justify-center text-text shrink-0 hover:bg-surface/70" aria-label="Back">
                <i class="ri-arrow-left-line text-[19px]"></i>
            </a>
        @endif
        <div class="min-w-0 flex flex-col gap-1.5">
            <p class="font-heading font-semibold text-text text-[22px] leading-tight truncate">{{ $title }}</p>
            @if ($subtitle)
                <p class="font-sans text-muted text-[13px] truncate">{{ $subtitle }}</p>
            @endif
        </div>
        @if (isset($action) && ! $action->isEmpty())
            <div class="ml-auto shrink-0">{{ $action }}</div>
        @endif
    </div>
@endif
