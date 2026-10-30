@props(['title' => null])

<div {{ $attributes->merge(['class' => 'bg-surface rounded-[20px] p-5 lg:p-6 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]']) }}>
    @if ($title)
        <p class="font-heading font-medium text-text text-[16px] mb-4">{{ $title }}</p>
    @endif
    {{ $slot }}
</div>
