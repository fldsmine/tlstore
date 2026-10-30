{{-- sections: array<string, array<int, array{label:string, icon:string, route?:string, href?:string}>> --}}
@props(['sections' => []])

<nav class="flex flex-col gap-5">
    @foreach ($sections as $heading => $items)
        <div class="flex flex-col gap-1">
            <p class="px-3 pb-1 font-sans text-[11px] uppercase tracking-[0.12em] text-white/40">{{ $heading }}</p>
            @foreach ($items as $item)
                @php
                    $hasRoute = isset($item['route']);
                    // `.index` route matches its whole group (users.index → users.*); explicit `match` overrides where siblings share a prefix.
                    $pattern = $item['match']
                        ?? (\Illuminate\Support\Str::endsWith($item['route'] ?? '', '.index')
                            ? \Illuminate\Support\Str::beforeLast($item['route'], '.index').'.*'
                            : ($item['route'] ?? ''));
                    $active = $hasRoute && request()->routeIs($pattern);
                    $href = $hasRoute ? route($item['route']) : ($item['href'] ?? '#');
                @endphp
                <a href="{{ $href }}" @if ($hasRoute) wire:navigate @endif @click="$dispatch('close-app-menu')"
                   class="group flex items-center gap-3 rounded-[12px] px-3 py-2.5 transition-colors {{ $active ? 'bg-white/15 text-secondary' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                    <i class="{{ $item['icon'] }} text-[19px] shrink-0 {{ $active ? 'text-secondary' : 'text-white/70 group-hover:text-white' }}"></i>
                    <span class="flex-1 min-w-0 font-sans text-[14px] truncate">{{ $item['label'] }}</span>
                    @if (array_key_exists('count', $item))
                        {{-- Bumped in real-time by the inbox notifier via `cloud-badge`. --}}
                        <span x-data="{ n: {{ (int) $item['count'] }} }"
                              @cloud-badge.window="n = $event.detail.count"
                              x-show="n > 0" x-cloak
                              class="shrink-0 min-w-[20px] h-5 px-1.5 rounded-full bg-secondary text-primary text-[11px] font-heading font-semibold flex items-center justify-center"
                              x-text="n > 99 ? '99+' : n">{{ $item['count'] > 99 ? '99+' : $item['count'] }}</span>
                    @elseif (! empty($item['tag']) || ! empty($item['wip']))
                        <span class="shrink-0 rounded-full bg-secondary/20 text-secondary px-1.5 py-0.5 font-sans text-[9px] uppercase tracking-wide">{{ $item['tag'] ?? 'WIP' }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    @endforeach
</nav>
