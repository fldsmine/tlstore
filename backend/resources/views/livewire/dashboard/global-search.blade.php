<div x-data="{ open: false }"
     @open-search.window="open = true; $nextTick(() => $refs.q?.focus())"
     @keydown.escape.window="open = false"
     @keydown.window.meta.k.prevent="open = true; $nextTick(() => $refs.q?.focus())"
     @keydown.window.ctrl.k.prevent="open = true; $nextTick(() => $refs.q?.focus())"
     x-cloak>

    <div x-show="open" x-transition.opacity.duration.200ms @click="open = false"
         class="fixed inset-0 z-[75] bg-black/40 backdrop-blur-sm"></div>

    <div x-show="open" class="fixed inset-x-0 top-0 z-[80] flex justify-center px-4 pt-[11vh] pointer-events-none">
        <div x-show="open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             @click.outside="open = false"
             class="pointer-events-auto w-full max-w-[520px] bg-surface rounded-[20px] shadow-[0_24px_70px_rgba(0,0,0,0.28)] overflow-hidden flex flex-col max-h-[72vh]">

            <div class="flex items-center gap-3 px-4 h-[56px] border-b border-muted/10 shrink-0">
                <i class="ri-search-line text-muted text-[20px]"></i>
                <input x-ref="q" type="text" wire:model.live.debounce.250ms="q"
                       placeholder="Search pages, numbers, transactions…"
                       class="flex-1 bg-transparent outline-none font-sans text-[15px] text-text placeholder:text-muted">
                <button type="button" @click="open = false" class="font-sans text-muted text-[10px] border border-muted/25 rounded px-1.5 py-0.5">ESC</button>
            </div>

            <div class="overflow-y-auto p-2 flex flex-col gap-0.5">
                @if (trim($q) === '')
                    <p class="px-3 py-8 text-center font-sans text-muted text-[13px]">Search across the whole app — pages, your numbers, and transactions.</p>
                @elseif ($empty)
                    <p class="px-3 py-8 text-center font-sans text-muted text-[13px]">No results for "{{ $q }}".</p>
                @endif

                @if ($pages->isNotEmpty())
                    <p class="px-3 pt-2 pb-1 font-sans text-[11px] uppercase tracking-wide text-muted/60">Pages</p>
                    @foreach ($pages as $p)
                        <a href="{{ route($p['route']) }}" wire:navigate @click="open = false"
                           class="flex items-center gap-3 rounded-[12px] px-3 py-2.5 hover:bg-background transition-colors">
                            <span class="size-9 rounded-full bg-background flex items-center justify-center text-primary shrink-0"><i class="{{ $p['icon'] }} text-[17px]"></i></span>
                            <span class="font-sans text-text text-[14px]">{{ $p['label'] }}</span>
                            <i class="ri-corner-down-left-line text-muted/50 text-[14px] ml-auto"></i>
                        </a>
                    @endforeach
                @endif

                @if ($numbers->isNotEmpty())
                    <p class="px-3 pt-2 pb-1 font-sans text-[11px] uppercase tracking-wide text-muted/60">Beneficiaries</p>
                    @foreach ($numbers as $n)
                        <a href="{{ route('call.preview', ['n' => $n->id]) }}" wire:navigate @click="open = false"
                           class="flex items-center gap-3 rounded-[12px] px-3 py-2.5 hover:bg-background transition-colors">
                            <img src="{{ $n->logo }}" alt="" class="size-9 rounded-full object-cover shrink-0">
                            <span class="flex flex-col min-w-0">
                                <span class="font-sans text-text text-[14px] truncate">{{ $n->masked }}</span>
                                <span class="font-sans text-muted text-[11px]">{{ $n->network }}</span>
                            </span>
                            <span class="ml-auto text-primary text-[11px] font-sans">Call</span>
                        </a>
                    @endforeach
                @endif

                @if ($transactions->isNotEmpty())
                    <p class="px-3 pt-2 pb-1 font-sans text-[11px] uppercase tracking-wide text-muted/60">Transactions</p>
                    @foreach ($transactions as $t)
                        <a href="{{ route('transactions') }}" wire:navigate @click="open = false"
                           class="flex items-center gap-3 rounded-[12px] px-3 py-2.5 hover:bg-background transition-colors">
                            <span class="size-9 rounded-full flex items-center justify-center shrink-0 {{ $t->is_credit ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger' }}"><i class="{{ $t->type->icon() }} text-[16px]"></i></span>
                            <span class="flex flex-col min-w-0">
                                <span class="font-sans text-text text-[14px] truncate">{{ $t->description ?: $t->type->getName() }}</span>
                                <span class="font-sans text-muted text-[11px]">{{ $t->created_at?->diffForHumans() }}</span>
                            </span>
                            <span class="ml-auto font-sans font-semibold text-[13px] {{ $t->is_credit ? 'text-success' : 'text-danger' }}">{{ $t->is_credit ? '+' : '-' }}<x-money :usd="$t->amount" /></span>
                        </a>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>
