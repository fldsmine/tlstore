{{-- Spinner + disabled while the wire:target action runs. --}}
@props(['type' => 'submit', 'target' => null, 'loadingText' => 'Please wait…'])

<button type="{{ $type }}"
    @if ($target) wire:target="{{ $target }}" wire:loading.attr="disabled" @endif
    {{ $attributes->merge(['class' => 'relative h-[45px] w-full rounded-[30px] bg-primary text-surface font-sans text-[14px] flex items-center justify-center gap-2 hover:opacity-95 disabled:opacity-70 disabled:cursor-not-allowed transition']) }}>
    @if ($target)
        <svg wire:loading wire:target="{{ $target }}" class="animate-spin size-4 text-surface" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <span wire:loading.remove wire:target="{{ $target }}">{{ $slot }}</span>
        <span wire:loading wire:target="{{ $target }}">{{ $loadingText }}</span>
    @else
        {{ $slot }}
    @endif
</button>
