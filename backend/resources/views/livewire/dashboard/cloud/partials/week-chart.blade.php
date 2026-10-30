{{-- 7-day N-Cloud Calls sparkline. Tooltip strings are pre-formatted server-side so the
     figures follow the reader's display currency. --}}

@php
    $amounts = array_column($week, 'amount');
    $vmax = max(0.01, max($amounts));
    $best = $week[array_search(max($amounts), $amounts, true)] ?? null;

    $cw = 280; $ch = 64; $cpad = 5;
    $cstep = count($week) > 1 ? $cw / (count($week) - 1) : 0;
    $cxs = []; $cys = [];
    foreach ($week as $i => $d) {
        $cxs[] = round($i * $cstep, 1);
        $cys[] = round($ch - $cpad - ($d['amount'] / $vmax) * ($ch - $cpad * 2), 1);
    }
    $pts = implode(' ', array_map(fn ($i) => $cxs[$i].','.$cys[$i], array_keys($week)));
    $area = '0,'.$ch.' '.$pts.' '.$cw.','.$ch;
@endphp

<div class="{{ $card }} p-5">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="{{ $eyebrow }}">Last 7 days</p>
            <p class="font-heading font-medium text-text text-[20px] leading-none mt-1.5 tabular-nums"><x-money :usd="$weekTotal" /></p>
        </div>
        @if ($best && $best['amount'] > 0)
            <div class="shrink-0 text-right">
                <p class="font-sans text-muted text-[11px]">Best day</p>
                <p class="font-sans text-text text-[12px] mt-0.5">{{ $best['date'] }} · {{ $best['display'] }}</p>
            </div>
        @endif
    </div>

    <div class="relative mt-4" x-data="{
            hover: -1,
            cx: @js($cxs), cy: @js($cys),
            labels: @js(array_column($week, 'date')),
            vals: @js(array_column($week, 'display')),
            w: {{ $cw }}, h: {{ $ch }},
            move(e) { const r = e.currentTarget.getBoundingClientRect(); const i = Math.round(((e.clientX - r.left) / r.width) * (this.cx.length - 1)); this.hover = Math.max(0, Math.min(this.cx.length - 1, i)); },
         }"
         @mousemove="move($event)" @mouseleave="hover = -1"
         @touchstart="move($event.touches[0])" @touchmove.prevent="move($event.touches[0])" @touchend="hover = -1">
        <svg viewBox="0 0 {{ $cw }} {{ $ch }}" preserveAspectRatio="none" class="w-full h-16 text-primary block overflow-visible">
            <defs>
                <linearGradient id="cloudWeekFill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="currentColor" stop-opacity="0.18"></stop>
                    <stop offset="100%" stop-color="currentColor" stop-opacity="0"></stop>
                </linearGradient>
            </defs>
            <polygon points="{{ $area }}" fill="url(#cloudWeekFill)"></polygon>
            <polyline points="{{ $pts }}" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"></polyline>
            <template x-if="hover >= 0">
                <g>
                    <line :x1="cx[hover]" :x2="cx[hover]" y1="0" :y2="h" stroke="currentColor" stroke-width="1" stroke-dasharray="3 3" opacity="0.4" vector-effect="non-scaling-stroke"></line>
                    <circle :cx="cx[hover]" :cy="cy[hover]" r="4" fill="currentColor" stroke="#fff" stroke-width="1.5" vector-effect="non-scaling-stroke"></circle>
                </g>
            </template>
        </svg>
        <div x-show="hover >= 0" x-cloak
             class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full bg-text text-surface rounded-[10px] px-2.5 py-1.5 shadow-lg whitespace-nowrap"
             :style="`left: ${(cx[hover] / w) * 100}%; top: ${(cy[hover] / h) * 100}%; margin-top: -6px;`">
            <span class="block font-sans text-[10px] opacity-70" x-text="labels[hover]"></span>
            <span class="block font-heading text-[12px]" x-text="vals[hover]"></span>
        </div>
    </div>

    <div class="flex justify-between mt-2">
        @foreach ($week as $d)
            <span class="font-sans text-[10px] {{ $loop->last ? 'text-text' : 'text-muted' }}">{{ $loop->last ? 'Today' : $d['label'] }}</span>
        @endforeach
    </div>

    @if ($weekTotal <= 0)
        <p class="font-sans text-muted text-[12px] mt-3 leading-relaxed">No N-Cloud Calls in the last 7 days yet — a completed sponsored call is all it takes.</p>
    @endif
</div>
