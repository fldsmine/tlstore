<div>
    @php
        $card = 'bg-surface rounded-[20px] shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]';
    @endphp

    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-12 flex flex-col gap-[22px]">

        <x-app.app-bar :title="'Cloud Inbox'" subtitle="Read sponsored messages to earn" />

        <div x-data="{
                hidden: JSON.parse(localStorage.getItem('nx_hide_balance') || 'false'),
                toggle() {
                    this.hidden = ! this.hidden;
                    localStorage.setItem('nx_hide_balance', JSON.stringify(this.hidden));
                    window.dispatchEvent(new CustomEvent('nx-balance-visibility', { detail: this.hidden }));
                },
             }"
             x-init="window.addEventListener('nx-balance-visibility', e => hidden = e.detail)"
             class="relative overflow-hidden rounded-[22px] p-5 text-white shadow-[0_14px_34px_-12px_rgba(24,68,62,0.5)]"
             style="background: radial-gradient(125% 130% at 88% 12%, #2f7468 0%, #18443E 58%); background-color:#18443E;">
            <span class="absolute -right-8 -top-10 size-36 rounded-full bg-white/[0.06]"></span>
            <div class="relative flex items-start justify-between gap-3">
                <div>
                    <p class="font-sans text-white/60 text-[12px] uppercase tracking-wide">N-Cloud SMS</p>
                    <span x-show="!hidden"><x-money :usd="$balance" class="block font-heading font-semibold text-[30px] mt-1" /></span>
                    <span x-show="hidden" x-cloak class="block font-heading font-semibold text-[30px] mt-1">{{ \App\Support\DisplayCurrency::symbol() }}••••••</span>
                    <p class="font-sans text-white/70 text-[12px] mt-1 flex flex-wrap items-center gap-x-1">
                        <span>{{ $unread }} unread</span>
                        @if ($unclaimed > 0)
                            <span>·</span>
                            <span class="text-secondary inline-flex items-center gap-1"><span x-show="!hidden"><x-money :usd="$unclaimed" /></span><span x-show="hidden" x-cloak>••••</span> waiting to claim</span>
                        @endif
                        <span>·</span>
                        <span>{{ $smsClaimedToday }}@if ($smsLimit > 0) of {{ $smsLimit }}@endif {{ Str::plural('SMS', $smsClaimedToday) }} today</span>
                    </p>
                </div>
                <div class="shrink-0 flex items-center gap-2">
                    <button @click="toggle()" class="text-white/80 hover:text-white" aria-label="Toggle balance">
                        <i class="ri-eye-line text-[20px]" x-show="!hidden"></i>
                        <i class="ri-eye-off-line text-[20px]" x-show="hidden" x-cloak></i>
                    </button>
                    <a href="{{ route('cloud') }}" wire:navigate class="inline-flex items-center gap-1.5 rounded-full bg-white/15 hover:bg-white/25 transition px-3 py-1.5 font-sans text-[12px]"><i class="ri-cloud-line"></i> Cloud</a>
                </div>
            </div>
        </div>

        {{-- Add more Cloud storage — packs add extra contacts AND extra daily SMS access. --}}
        <div class="{{ $card }} p-4 flex items-center gap-3">
            <span class="size-10 rounded-[12px] bg-primary/10 flex items-center justify-center text-primary shrink-0"><i class="ri-database-2-line text-[20px]"></i></span>
            <div class="min-w-0 flex-1">
                <p class="font-heading text-text text-[14px]">Add more Cloud storage</p>
                <p class="font-sans text-muted text-[12px]">Storage packs add extra contacts and extra {{ Str::plural('SMS', 2) }} per day.</p>
            </div>
            <a href="{{ route('cloud', ['storage' => 1]) }}" wire:navigate
               class="shrink-0 h-9 px-4 rounded-full bg-primary text-surface font-sans font-semibold text-[12.5px] inline-flex items-center gap-1.5 hover:opacity-95 transition">
                <i class="ri-add-line"></i> Add
            </a>
        </div>

        {{-- Mobile shows one pane at a time (list OR thread); lg+ keeps both side by side. --}}
        <div class="lg:grid lg:grid-cols-3 lg:gap-5 lg:items-start">

            <div class="lg:col-span-1 {{ $conversation ? 'hidden lg:block' : '' }}">
                <div class="{{ $card }} overflow-hidden">
                    @forelse ($conversations as $c)
                        <button type="button" wire:key="conv-{{ md5($c['key']) }}" @click="$wire.openThread(@js($c['key']))"
                                class="w-full flex items-center gap-3 px-4 py-3.5 border-b border-muted/5 last:border-0 text-left hover:bg-background/50 transition
                                       {{ $conversation && $conversation['key'] === $c['key'] ? 'bg-primary/[0.06]' : ($c['unread'] > 0 ? 'bg-primary/[0.03]' : '') }}">
                            @if ($c['is_system'])
                                <span class="size-11 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0"><i class="ri-message-2-line text-[19px]"></i></span>
                            @elseif ($c['avatar'])
                                <img src="{{ $c['avatar'] }}" alt="" class="size-11 rounded-full object-cover bg-background shrink-0">
                            @else
                                <span class="size-11 rounded-full bg-secondary/25 text-primary flex items-center justify-center shrink-0 font-heading text-[15px]">{{ strtoupper(substr($c['name'], 0, 1)) }}</span>
                            @endif

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="font-heading text-text text-[14px] {{ $c['unread'] > 0 ? 'font-semibold' : 'font-medium' }} truncate">{{ $c['name'] }}</p>
                                    <span class="ml-auto shrink-0 font-sans text-muted text-[11px]">{{ $c['last_at']?->diffForHumans(null, true) }}</span>
                                </div>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <p class="font-sans text-muted text-[12.5px] truncate flex-1">{{ $c['snippet'] }}</p>
                                    @if ($c['unread'] > 0)
                                        <span class="shrink-0 min-w-[20px] h-5 px-1.5 rounded-full bg-primary text-surface font-sans font-semibold text-[11px] inline-flex items-center justify-center">{{ $c['unread'] > 99 ? '99+' : $c['unread'] }}</span>
                                    @endif
                                </div>
                                @if ($c['unclaimed'] > 0)
                                    <p class="font-sans text-[11.5px] mt-1 inline-flex items-center gap-1 text-success"><i class="ri-flashlight-fill"></i> <x-money :usd="$c['unclaimed']" /> to claim</p>
                                @endif
                            </div>
                        </button>
                    @empty
                        <div class="px-5 py-12 text-center flex flex-col items-center gap-3">
                            <span class="size-12 rounded-full bg-background inline-flex items-center justify-center text-muted"><i class="ri-inbox-line text-[22px]"></i></span>
                            <p class="font-sans text-muted text-[13px]">Your inbox is empty. Sponsored messages and alerts will arrive here.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="lg:col-span-2 {{ $conversation ? '' : 'hidden lg:block' }}">
                @if ($conversation)
                    <div class="{{ $card }} overflow-hidden flex flex-col">
                        <div class="flex items-center gap-3 px-4 py-3.5 border-b border-muted/10 bg-surface">
                            <button type="button" wire:click="back" class="lg:hidden size-9 rounded-full bg-background flex items-center justify-center text-text shrink-0" aria-label="Back to conversations">
                                <i class="ri-arrow-left-s-line text-[22px]"></i>
                            </button>

                            @if ($conversation['avatar'])
                                <img src="{{ $conversation['avatar'] }}" alt="" class="size-10 rounded-full object-cover bg-background shrink-0">
                            @elseif ($conversation['is_system'])
                                <span class="size-10 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0"><i class="ri-message-2-line text-[18px]"></i></span>
                            @else
                                <span class="size-10 rounded-full bg-secondary/25 text-primary flex items-center justify-center shrink-0 font-heading text-[14px]">{{ strtoupper(substr($conversation['name'], 0, 1)) }}</span>
                            @endif

                            <div class="min-w-0 flex-1">
                                <p class="font-heading font-medium text-text text-[15px] truncate">{{ $conversation['name'] }}</p>
                                <p class="font-sans text-muted text-[11.5px] truncate">{{ $conversation['number'] ?: 'Nextel alerts' }} · {{ $conversation['total'] }} {{ Str::plural('message', $conversation['total']) }}</p>
                            </div>

                            <span class="shrink-0 rounded-full {{ $conversation['is_system'] ? 'bg-primary/10 text-primary' : 'bg-secondary/25 text-primary' }} px-2 py-0.5 font-sans text-[9.5px] uppercase tracking-wide">{{ $conversation['is_system'] ? 'Nextel' : 'Sponsored' }}</span>
                        </div>

                        @if ($smsLimitReached)
                            <div class="flex items-center gap-2 bg-warning/12 px-4 py-2.5 font-sans text-[12px] text-text">
                                <i class="ri-time-line text-warning shrink-0"></i>
                                <span>Daily SMS limit reached ({{ $smsClaimedToday }} of {{ $smsLimit }}).
                                    <a href="{{ route('cloud', ['storage' => 1]) }}" wire:navigate class="text-primary font-semibold underline">Add a Cloud storage plan</a>
                                    for more SMS access.</span>
                            </div>
                        @endif

                        <div wire:key="thread-{{ md5($conversation['key']) }}" x-data x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
                             class="bg-background px-4 py-4 flex flex-col gap-3 overflow-y-auto max-h-[58vh] lg:max-h-[calc(100vh-300px)] lg:min-h-[380px]">

                            @if ($earlier > 0)
                                <button type="button" wire:click="loadEarlier"
                                        class="self-center rounded-full bg-surface text-muted font-sans text-[12px] px-4 py-1.5 hover:text-text transition">
                                    Load earlier messages ({{ $earlier }})
                                </button>
                            @endif

                            @php $lastDay = null; @endphp
                            @foreach ($messages as $m)
                                @php $day = $m->created_at?->toDateString(); @endphp
                                @if ($day !== $lastDay)
                                    @php $lastDay = $day; @endphp
                                    <p class="self-center rounded-full bg-surface text-muted font-sans text-[11px] px-3 py-1">{{ $m->created_at?->isToday() ? 'Today' : ($m->created_at?->isYesterday() ? 'Yesterday' : $m->created_at?->format('j M Y')) }}</p>
                                @endif

                                <div wire:key="bubble-{{ $m->id }}" class="max-w-[88%] lg:max-w-[72%] bg-surface rounded-[18px] rounded-tl-[6px] shadow-[0_2px_6px_rgba(150,150,150,0.08)] px-4 py-3 flex flex-col gap-1.5">
                                    @if ($m->subject)
                                        <p class="font-heading font-medium text-text text-[14px]">{{ $m->subject }}</p>
                                    @endif
                                    <p class="font-sans text-text text-[13.5px] leading-relaxed whitespace-pre-line">{{ $m->body }}</p>

                                    @if ($m->is_claimable)
                                        <div class="mt-1 rounded-[14px] bg-secondary/15 px-3 py-2.5 flex flex-wrap items-center justify-between gap-2">
                                            <p class="font-sans text-[12px] text-text inline-flex items-center gap-1.5">
                                                <i class="ri-flashlight-fill text-primary"></i> Reward <x-money :usd="$m->earn_rate" class="font-semibold" />
                                            </p>
                                            {{-- Open the sponsor link inside the click gesture (browsers block window.open otherwise), then book the reward. --}}
                                            <button type="button"
                                                    @click=" @if ($m->claim_url) window.open(@js($m->claim_url), '_blank', 'noopener'); @endif $wire.claim(@js($m->id)) "
                                                    wire:loading.attr="disabled" wire:target="claim" @disabled($smsLimitReached)
                                                    class="shrink-0 h-9 px-4 rounded-full bg-primary text-surface font-sans font-semibold text-[12.5px] hover:opacity-95 transition disabled:opacity-60 inline-flex items-center gap-1.5">
                                                <i class="ri-hand-coin-line"></i> {{ $m->claim_url ? 'Claim offer' : 'Claim' }}
                                            </button>
                                        </div>
                                        @if ($m->claim_host)
                                            <p class="font-sans text-[11px] text-muted inline-flex items-center gap-1 min-w-0"><i class="ri-external-link-line shrink-0"></i> <span class="truncate">{{ $m->claim_host }}</span></p>
                                        @endif
                                    @elseif ($m->is_sponsored && (float) $m->earn_rate > 0)
                                        <p class="font-sans text-[11.5px] text-success inline-flex items-center gap-1">
                                            <i class="ri-checkbox-circle-fill"></i> Claimed <x-money :usd="$m->earn_rate" />
                                        </p>
                                        @if ($m->claim_url)
                                            <a href="{{ $m->claim_url }}" target="_blank" rel="noopener" class="font-sans text-[11.5px] text-primary inline-flex items-center gap-1"><i class="ri-external-link-line"></i> Visit sponsor offer</a>
                                        @endif
                                    @endif

                                    <span class="self-end font-sans text-muted text-[10.5px]">{{ $m->created_at?->format('g:i A') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="{{ $card }} hidden lg:flex flex-col items-center justify-center text-center gap-3 px-6 py-20">
                        <span class="size-14 rounded-full bg-background inline-flex items-center justify-center text-muted"><i class="ri-chat-3-line text-[24px]"></i></span>
                        <p class="font-sans text-muted text-[13px] max-w-[280px]">Pick a conversation to read it. Sponsored messages pay out the moment you claim them.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <x-app.bottom-nav />
</div>
