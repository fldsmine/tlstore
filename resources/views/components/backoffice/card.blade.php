@props(['title' => null, 'class' => ''])

<div {{ $attributes->merge(['class' => "card {$class}"]) }}>
    @if ($title)
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-heading font-semibold text-text text-[15px]">{{ $title }}</h2>
            @if (isset($action))<div class="shrink-0">{{ $action }}</div>@endif
        </div>
    @endif
    {{ $slot }}
</div>
