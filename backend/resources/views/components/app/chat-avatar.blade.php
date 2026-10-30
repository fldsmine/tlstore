@props(['user' => null, 'size' => 44])

<span {{ $attributes->merge(['class' => 'rounded-full bg-secondary/25 text-primary inline-flex items-center justify-center shrink-0 overflow-hidden font-heading font-semibold']) }}
      style="width: {{ $size }}px; height: {{ $size }}px; font-size: {{ (int) round($size * 0.38) }}px;">
    @if ($user?->avatar)
        <img src="{{ media_url($user->avatar) }}" class="size-full object-cover" alt="">
    @else
        {{ strtoupper(substr($user?->username ?? '?', 0, 1)) }}
    @endif
</span>
