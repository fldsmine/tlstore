{{-- On the `on` event, spinner for `delay`ms then the modal, so it doesn't snap up the instant the request returns. --}}
@props([
    'on' => 'show-success',
    'action' => null,
    'title' => 'Success',
    'message' => '',
    'actionLabel' => 'Continue',
    'icon' => 'ri-check-line',
    'delay' => 700,
])

<div x-data="{ open: false, loading: false }"
     x-on:{{ $on }}.window="loading = true; setTimeout(() => { loading = false; open = true }, {{ (int) $delay }})"
     x-on:keydown.escape.window="open = false"
     x-cloak>

    <div x-show="loading" x-transition.opacity.duration.150ms
         class="fixed inset-0 z-[80] flex items-center justify-center bg-black/30 backdrop-blur-sm">
        <span class="size-12 rounded-full border-[3px] border-white/30 border-t-white animate-spin"></span>
    </div>

    <div x-show="open" class="fixed inset-0 z-[80] flex items-center justify-center px-6">
        <div x-show="open" x-transition.opacity.duration.200ms
             class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>

        <div x-show="open"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="opacity-0 scale-95 translate-y-1"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="relative w-full max-w-[340px] bg-surface rounded-[26px] px-7 py-10 flex flex-col items-center text-center gap-3 shadow-[0_24px_70px_rgba(0,0,0,0.25)]">
            <span class="size-[68px] rounded-full border-[3px] border-success flex items-center justify-center text-success shrink-0">
                <i class="{{ $icon }} text-[36px]"></i>
            </span>
            <p class="font-heading font-medium text-text text-[22px]">{{ $title }}</p>
            @if ($message)
                <p class="font-sans text-muted text-[14px]">{{ $message }}</p>
            @endif
            <button type="button"
                    @if ($action) wire:click="{{ $action }}" @else @click="open = false" @endif
                    class="mt-4 w-full h-[52px] rounded-full bg-primary text-surface font-sans text-[15px] hover:opacity-95 transition flex items-center justify-center">
                @if ($action)
                    <span wire:loading.remove wire:target="{{ $action }}">{{ $actionLabel }}</span>
                    <span wire:loading wire:target="{{ $action }}">Please wait…</span>
                @else
                    {{ $actionLabel }}
                @endif
            </button>
        </div>
    </div>
</div>
