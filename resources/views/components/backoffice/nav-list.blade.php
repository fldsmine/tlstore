{{-- Vertical navigation list used by the backoffice sidebar (desktop + mobile drawer). --}}
@props(['sections' => []])

<nav class="flex flex-col gap-6" aria-label="Backoffice navigation">
    @foreach ($sections as $section => $items)
        <div class="flex flex-col gap-1">
            <p class="px-3 font-sans text-[10.5px] uppercase tracking-[0.16em] text-white/40">{{ $section }}</p>
            <ul class="flex flex-col gap-0.5">
                @foreach ($items as $item)
                    @php
                        $match = $item['match'] ?? $item['route'];
                        $active = is_array($match) ? request()->routeIs(...$match) : request()->routeIs($match);
                    @endphp
                    <li>
                        <a href="{{ route($item['route']) }}" wire:navigate
                           class="flex items-center gap-3 rounded-[12px] px-3 py-2.5 font-sans text-[13.5px] transition-colors
                                  {{ $active ? 'bg-secondary text-primary font-semibold' : 'text-white/80 hover:text-white hover:bg-white/10' }}">
                            <i class="{{ $item['icon'] }} text-[18px] {{ $active ? 'text-primary' : 'text-white/70' }}"></i>
                            <span class="truncate">{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>
