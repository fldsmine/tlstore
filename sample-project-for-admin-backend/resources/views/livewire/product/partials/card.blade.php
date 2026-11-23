{{--
    One storefront card. Included with ['item' => $item] from the storefront grid.
    An item with an image is an image card; an eSIM plan (no image of its own) gets a drawn panel so
    the grid stays even — and every number on the card is the number the charge will ask for.
--}}
@php
    $orderable = (bool) $item['orderable'];
    $hasImage = filled($item['image']);
@endphp

<article wire:key="item-{{ $item['source'] }}-{{ $item['id'] }}"
         class="relative flex flex-col rounded-[20px] md:rounded-[24px] border {{ $item['is_featured'] ? 'border-primary/45' : 'border-muted/15' }} bg-surface/40 overflow-hidden">

    {{-- ── Visual ─────────────────────────────────────────────────────────────── --}}
    <div class="relative aspect-[4/3] overflow-hidden bg-background">
        @if ($hasImage)
            <img src="{{ $item['image'] }}" alt="{{ $item['image_alt'] }}"
                 class="absolute inset-0 size-full object-cover transition-transform duration-500 hover:scale-[1.03]">
        @else
            <div class="absolute inset-0 flex flex-col items-center justify-center gap-3"
                 style="background: linear-gradient(135deg, {{ $item['is_featured'] ? 'rgba(40,157,141,0.22)' : 'rgba(24,68,62,0.10)' }} 0%, rgba(24,68,62,0.03) 60%, rgba(24,68,62,0.10) 100%);">
                <span class="size-16 rounded-[20px] bg-background/80 border border-primary/20 flex items-center justify-center text-primary">
                    <i class="ri-sim-card-2-line text-[30px]"></i>
                </span>
                <span class="font-heading font-semibold text-primary text-[15px] tracking-[0.14em] uppercase">eSIM plan</span>
            </div>
        @endif

        <div class="absolute top-3 left-3 flex flex-col items-start gap-1.5">
            @if ($item['badge'])
                <span class="inline-flex items-center rounded-full bg-primary text-white px-3 py-1 font-sans text-[11px]">{{ $item['badge'] }}</span>
            @endif
            @if ($item['is_featured'])
                <span class="inline-flex items-center rounded-full bg-background/90 text-primary px-3 py-1 font-sans text-[11px] border border-primary/25">Most popular</span>
            @endif
        </div>

        @if (! $orderable)
            <span class="absolute top-3 right-3 inline-flex items-center rounded-full bg-background/90 text-muted px-3 py-1 font-sans text-[11px] border border-muted/20">Unavailable</span>
        @endif
    </div>

    {{-- ── Detail ─────────────────────────────────────────────────────────────── --}}
    <div class="p-5 md:p-6 flex flex-col gap-4 flex-1">
        <div class="flex flex-col gap-1.5">
            <div class="flex items-start justify-between gap-3">
                <h3 class="font-heading font-semibold text-primary text-[20px] md:text-[22px] leading-snug">{{ $item['name'] }}</h3>
                <span class="shrink-0 font-sans text-[11px] text-muted uppercase tracking-[0.12em] pt-1.5">{{ $item['kind'] }}</span>
            </div>
            @if (filled($item['tagline']))
                <p class="font-sans text-muted text-[13px] leading-relaxed">{{ $item['tagline'] }}</p>
            @endif
        </div>

        <div class="flex items-end gap-2">
            <span class="font-heading font-semibold text-text text-[28px] md:text-[32px] leading-none tabular-nums">{{ $item['formatted_price'] }}</span>
            <span class="font-sans text-muted text-[12px] pb-1">one-off</span>
        </div>

        @if ($item['features'] !== [])
            <ul class="flex flex-col gap-2">
                @foreach (array_slice($item['features'], 0, 4) as $feature)
                    <li class="flex items-start gap-2 font-sans text-text text-[13px] leading-snug">
                        <i class="ri-check-line text-primary text-[16px] mt-0.5 shrink-0"></i>
                        <span>{{ $feature }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($item['specs'] !== [])
            <dl class="mt-auto grid grid-cols-2 gap-x-4 gap-y-2.5 pt-4 border-t border-muted/10">
                @foreach (array_slice($item['specs'], 0, 6) as $spec)
                    <div>
                        <dt class="font-sans text-muted text-[11px]">{{ $spec['label'] }}</dt>
                        <dd class="font-sans text-text text-[13px] tabular-nums">{{ $spec['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        <div class="pt-1">
            @if ($orderable)
                <a href="{{ route('product.checkout', $item['slug']) }}"
                   class="w-full h-[50px] rounded-full {{ $item['is_featured'] ? 'bg-primary text-white hover:bg-primary/90' : 'bg-secondary text-primary hover:bg-secondary/80' }} flex items-center justify-center gap-2 font-sans font-semibold text-[14px] transition-colors">
                    {{ $item['cta_label'] }}
                    <i class="ri-arrow-right-line"></i>
                </a>
            @else
                <span class="w-full h-[50px] rounded-full bg-muted/10 text-muted flex items-center justify-center font-sans text-[13px]">
                    Currently unavailable
                </span>
            @endif
        </div>
    </div>
</article>
