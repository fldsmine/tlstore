{{-- points: list of ['label' => string, 'value' => float] (or plain numbers). --}}
@props(['title' => null, 'total' => null, 'points' => [], 'color' => '#18443E', 'height' => 150, 'money' => false])

@php
    $vals = array_map(fn ($p) => is_array($p) ? (float) $p['value'] : (float) $p, $points);
    $labels = array_map(fn ($p) => is_array($p) ? (string) $p['label'] : '', $points);
    $n = count($vals);
    $max = $n ? max($vals) : 0;
    $max = $max > 0 ? $max : 1;
    $w = 600;
    $h = (int) $height;
    $pad = 6;
    $stepX = $n > 1 ? ($w - $pad * 2) / ($n - 1) : 0;

    $cx = [];
    $cy = [];
    foreach ($vals as $i => $v) {
        $cx[] = round($pad + $i * $stepX, 1);
        $cy[] = round($h - $pad - ($v / $max) * ($h - $pad * 2), 1);
    }
    $line = implode(' ', array_map(fn ($i) => $cx[$i].','.$cy[$i], range(0, max($n - 1, 0))));
    $area = $n > 1 ? ($pad.','.($h - $pad).' '.$line.' '.end($cx).','.($h - $pad)) : '';
    $hasData = $n > 0 && array_sum($vals) > 0;
@endphp

<x-backoffice.card>
    @if ($title || $total !== null)
        <div class="flex items-center justify-between mb-3">
            @if ($title)<p class="font-sans text-muted text-[13px]">{{ $title }}</p>@endif
            @if ($total !== null)<p class="font-heading font-semibold text-text text-[18px]">{{ $total }}</p>@endif
        </div>
    @endif

    @unless ($hasData)
        <div class="flex items-center justify-center font-sans text-muted text-[13px]" style="height: {{ $h }}px">No data in this range.</div>
    @else
        <div x-data="{
                hover: -1,
                cx: @js($cx), cy: @js($cy), labels: @js($labels), vals: @js($vals),
                w: {{ $w }}, h: {{ $h }}, money: {{ $money ? 'true' : 'false' }},
                move(e) {
                    const rect = e.currentTarget.getBoundingClientRect();
                    const ratio = (e.clientX - rect.left) / rect.width;
                    let i = Math.round(ratio * (this.cx.length - 1));
                    this.hover = Math.max(0, Math.min(this.cx.length - 1, i));
                },
                fmt(v) { return this.money ? '$' + Number(v).toLocaleString() : Number(v).toLocaleString(); },
             }"
             class="relative"
             @mousemove="move($event)" @mouseleave="hover = -1">
            <svg viewBox="0 0 {{ $w }} {{ $h }}" class="w-full block" style="height: {{ $h }}px" preserveAspectRatio="none">
                @if ($area)<polygon points="{{ $area }}" fill="{{ $color }}" opacity="0.10"></polygon>@endif
                <polyline points="{{ $line }}" fill="none" stroke="{{ $color }}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"></polyline>
                <template x-if="hover >= 0">
                    <g>
                        <line :x1="cx[hover]" :x2="cx[hover]" y1="0" :y2="h" stroke="{{ $color }}" stroke-width="1" stroke-dasharray="3 3" opacity="0.4" vector-effect="non-scaling-stroke"></line>
                        <circle :cx="cx[hover]" :cy="cy[hover]" r="3.5" fill="{{ $color }}" stroke="#fff" stroke-width="1.5" vector-effect="non-scaling-stroke"></circle>
                    </g>
                </template>
            </svg>

            <div x-show="hover >= 0" x-cloak
                 class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full bg-text text-surface rounded-[10px] px-2.5 py-1.5 shadow-lg whitespace-nowrap"
                 :style="`left: ${(cx[hover] / w) * 100}%; top: ${(cy[hover] / h) * 100}%; margin-top: -8px;`">
                <span class="block font-sans text-[10px] opacity-70" x-text="labels[hover]"></span>
                <span class="block font-heading text-[13px]" x-text="fmt(vals[hover])"></span>
            </div>
        </div>
        <div class="flex items-center justify-between mt-1.5 font-sans text-[11px] text-muted">
            <span>{{ $labels[0] ?? '' }}</span>
            <span>{{ $labels[$n - 1] ?? '' }}</span>
        </div>
    @endunless
</x-backoffice.card>
