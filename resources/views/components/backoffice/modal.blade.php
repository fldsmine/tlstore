{{-- Simple modal, mirrors the design reference. closeAction: Livewire expression run
     on backdrop/close click. size: md|lg|xl. --}}
@props(['show' => false, 'title' => null, 'closeAction' => "\$set('showModal', false)", 'size' => 'md'])

@php
    $maxWidth = match ($size) {
        'lg' => 'sm:max-w-[680px]',
        'xl' => 'sm:max-w-[820px]',
        default => 'sm:max-w-[460px]',
    };
@endphp

@if ($show)
    <div class="fixed inset-0 z-[80] flex items-center justify-center p-4" data-lock-scroll>
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" wire:click="{{ $closeAction }}"></div>
        <div class="relative w-full {{ $maxWidth }} bg-surface rounded-[22px] p-6 shadow-[0_20px_60px_rgba(0,0,0,0.25)] max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-5">
                <p class="font-heading font-semibold text-text text-[16px]">{{ $title }}</p>
                <button type="button" wire:click="{{ $closeAction }}" class="size-8 rounded-full bg-background flex items-center justify-center text-muted" aria-label="Close"><i class="ri-close-line"></i></button>
            </div>
            {{ $slot }}
        </div>
    </div>
@endif
