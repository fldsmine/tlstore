{{-- Sponsor contacts: callable first, unavailable (disabled sponsor) below, plus the add picker.
     addOpen/q come from the page-root Alpine scope so the hero CTA can open this too. --}}

@php
    $readyNumbers = $myNumbers->where('disabled', false)->values();
    $paidNumbers = $myNumbers->where('disabled', true)->values();
    $slotsLeft = $quota > 0 ? max(0, $quota - $quotaUsed) : null;
    $full = $slotsLeft === 0;
    $storagePct = $quota > 0 ? min(100, (int) round($quotaUsed / $quota * 100)) : 0;
@endphp

<div>
    <div class="{{ $card }} overflow-hidden">
        <div class="flex items-start justify-between gap-3 px-5 py-4">
            <div class="min-w-0">
                <p class="font-heading text-text text-[16px]">Sponsor contacts</p>
                <p class="font-sans text-muted text-[12px] mt-0.5">Call a contact, listen to the full message, and get paid.</p>
            </div>
            {{-- Always openable so users can browse available sponsors even when storage is full;
                 the per-sponsor Add button (and the server) enforce the quota. --}}
            <button type="button" @click="addOpen = true; q = ''"
                    class="shrink-0 inline-flex items-center gap-1.5 h-9 px-4 rounded-full bg-primary text-surface font-sans text-[13px] hover:opacity-95 transition">
                <i class="ri-add-line"></i> Add
            </button>
        </div>

        @if ($quota > 0)
            <div class="px-5 pb-4">
                <div class="h-1.5 rounded-full bg-background overflow-hidden">
                    <div class="h-full rounded-full {{ $full ? 'bg-warning' : 'bg-primary' }} transition-all duration-300" style="width: {{ $storagePct }}%"></div>
                </div>
                <p class="font-sans text-muted text-[11px] mt-1.5">
                    {{ $quotaUsed }} of {{ $quota }} slots used
                    @if ($full)
                        · <span class="text-warning">storage full — redeem a storage plan for more</span>
                    @else
                        · {{ $slotsLeft }} free
                    @endif
                </p>
            </div>
        @endif

        @if ($myNumbers->isEmpty())
            <div class="px-5 py-12 text-center flex flex-col items-center gap-3 border-t border-muted/10">
                <span class="size-14 rounded-[18px] bg-primary/10 inline-flex items-center justify-center text-primary"><i class="ri-contacts-book-3-line text-[26px]"></i></span>
                <div>
                    <p class="font-heading text-text text-[15px]">No sponsor contacts yet</p>
                    <p class="font-sans text-muted text-[13px] mt-1 leading-relaxed max-w-[320px] mx-auto">Save a sponsor from the list and earn its per-call rate.</p>
                </div>
                <button type="button" @click="addOpen = true; q = ''" class="mt-1 inline-flex items-center gap-1.5 h-10 px-5 rounded-full bg-primary text-surface font-sans text-[13px] hover:opacity-95 transition"><i class="ri-add-line"></i> Browse sponsors</button>
            </div>
        @else
            {{-- One list; callable contacts sort first so the actionable ones stay on top. --}}
            @foreach ($readyNumbers->concat($paidNumbers) as $num)
                <div wire:key="num-{{ $num['id'] }}" class="flex items-center gap-3 px-5 py-3.5 border-b border-muted/5 last:border-0 {{ $num['disabled'] ? 'opacity-60' : '' }}">
                    @if ($num['avatar'])
                        <img src="{{ $num['avatar'] }}" alt="" class="size-11 rounded-[13px] object-cover bg-background shrink-0">
                    @else
                        <span class="size-11 rounded-[13px] bg-primary/10 flex items-center justify-center text-primary shrink-0 font-heading text-[15px]">{{ strtoupper(substr($num['brand'] ?: $num['name'], 0, 1)) }}</span>
                    @endif

                    <div class="min-w-0 flex-1">
                        <p class="font-sans text-text text-[14px] truncate">{{ $num['name'] }}</p>
                        <p class="font-sans text-muted text-[12px] truncate">
                            {{ $num['brand'] ? $num['brand'].' · ' : '' }}
                            @if ($num['disabled'])
                                <span>Unavailable</span>
                            @else
                                <span class="text-success"><x-money :usd="$num['earning']" /> per call</span>
                            @endif
                        </p>
                    </div>

                    @if ($num['disabled'])
                        <span class="shrink-0 inline-flex items-center gap-1.5 h-9 px-4 rounded-full bg-background text-muted font-sans text-[13px] cursor-not-allowed" title="This sponsor is currently unavailable"><i class="ri-phone-off-line"></i> Unavailable</span>
                    @else
                        <button type="button" @click="$dispatch('cloud-start-call', @js($num))"
                                class="shrink-0 inline-flex items-center gap-1.5 h-9 px-4 rounded-full bg-primary text-surface font-sans text-[13px] hover:opacity-95 transition"><i class="ri-phone-line"></i> Call</button>
                    @endif
                </div>
            @endforeach
        @endif
    </div>

    {{-- Add picker --}}
    <template x-if="addOpen">
        <div data-lock-scroll @keydown.escape.window="addOpen = false"
             class="fixed inset-0 z-[90] flex items-end justify-center sm:items-center sm:p-4">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="addOpen = false"></div>
            <div class="relative w-full sm:max-w-[440px] bg-surface rounded-t-[26px] sm:rounded-[26px] max-h-[88vh] flex flex-col shadow-[0_24px_70px_rgba(0,0,0,0.28)]"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">

                <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-muted/10 shrink-0">
                    <div class="min-w-0">
                        <p class="font-heading text-text text-[16px]">Add a sponsor</p>
                        <p class="font-sans text-muted text-[12px] mt-0.5">{{ $quotaUsed }} of {{ $quota > 0 ? $quota : '∞' }} slots used{{ $full ? ' · storage full' : '' }}</p>
                    </div>
                    <button type="button" @click="addOpen = false" class="shrink-0 size-9 rounded-full bg-background text-muted hover:text-text inline-flex items-center justify-center transition" aria-label="Close"><i class="ri-close-line text-[18px]"></i></button>
                </div>

                <div class="px-5 py-3 border-b border-muted/10 shrink-0">
                    <div class="flex items-center gap-2.5 h-11 rounded-[12px] bg-background px-3.5">
                        <i class="ri-search-line text-muted text-[18px]"></i>
                        <input type="text" x-model="q" placeholder="Search sponsors…" class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[14px] text-text placeholder:text-muted">
                        <button type="button" x-show="q" @click="q = ''" class="text-muted hover:text-text" aria-label="Clear"><i class="ri-close-circle-fill text-[16px]"></i></button>
                    </div>
                </div>

                <div class="overflow-y-auto flex-1">
                    @forelse ($available as $n)
                        @php $nlabel = strtolower(trim($n->name.' '.$n->brand)); @endphp
                        <div wire:key="avail-{{ $n->id }}" data-name="{{ $nlabel }}" x-show="!q.trim() || $el.dataset.name.includes(q.toLowerCase().trim())"
                             class="flex items-center gap-3 px-5 py-3.5 border-b border-muted/5 last:border-0">
                            @if ($n->avatar_url)
                                <img src="{{ $n->avatar_url }}" alt="" class="size-11 rounded-[13px] object-cover bg-background shrink-0">
                            @else
                                <span class="size-11 rounded-[13px] bg-background flex items-center justify-center text-muted shrink-0 font-heading text-[15px]">{{ strtoupper(substr($n->brand ?: $n->name, 0, 1)) }}</span>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="font-sans text-text text-[14px] truncate">{{ $n->name }}</p>
                                <p class="font-sans text-muted text-[12px] truncate">{{ $n->brand ? $n->brand.' · ' : '' }}<span class="text-success"><x-money :usd="(float) $n->earning_per_call" /> per call</span></p>
                            </div>
                            <button type="button" wire:click="addNumber('{{ $n->id }}')" wire:loading.attr="disabled" wire:target="addNumber" @disabled($full)
                                    class="shrink-0 inline-flex items-center gap-1.5 h-9 px-4 rounded-full bg-primary/10 text-primary font-sans text-[13px] hover:bg-primary/15 transition disabled:opacity-40 disabled:cursor-not-allowed"><i class="ri-add-line"></i> Add</button>
                        </div>
                    @empty
                        <div class="px-5 py-12 text-center flex flex-col items-center gap-2">
                            <span class="size-12 rounded-full bg-background inline-flex items-center justify-center text-success"><i class="ri-checkbox-circle-line text-[22px]"></i></span>
                            <p class="font-sans text-muted text-[13px]">You've added every sponsor available on your plan.</p>
                        </div>
                    @endforelse
                    @if ($available->isNotEmpty())
                        <div x-show="q.trim() !== '' && ![...$el.parentElement.querySelectorAll('[data-name]')].some(r => r.dataset.name.includes(q.toLowerCase().trim()))" style="display:none" class="px-5 py-10 text-center">
                            <p class="font-sans text-muted text-[13px]" x-text="'No sponsors match “' + q.trim() + '”.'"></p>
                        </div>
                    @endif
                </div>

                @if ($full)
                    <div class="px-5 py-3 border-t border-muted/10 shrink-0">
                        <p class="font-sans text-muted text-[12px] text-center">Storage full — redeem a storage plan to save more contacts.</p>
                    </div>
                @endif
            </div>
        </div>
    </template>
</div>
