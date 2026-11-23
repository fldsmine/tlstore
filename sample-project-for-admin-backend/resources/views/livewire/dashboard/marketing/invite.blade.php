<div class="min-h-screen bg-background">
    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-12 flex flex-col gap-[22px]">

        <x-app.app-bar title="Invite & Team" />

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            <div class="lg:col-span-5 flex flex-col gap-[22px]">

                <div class="relative overflow-hidden rounded-[20px] border border-[#f8f8f8] bg-surface p-5 min-h-[150px] flex flex-col justify-center shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]"
                     style="background-image: url('{{ asset('images/invite-gradient.svg') }}'); background-size: cover; background-position: center;">
                    <img src="{{ asset('images/invite-gift.png') }}" alt="" class="absolute -top-4 -right-10 w-[210px] pointer-events-none select-none">
                    <div class="relative flex flex-col gap-2 max-w-[235px]">
                        <p class="font-sans font-semibold text-text text-[18px] leading-tight">Invite your friends and get rewarded!</p>
                        <p class="font-sans text-muted text-[14px] leading-snug">Enjoy your rewards with your friends through our reward points system.</p>
                    </div>
                    <p class="relative text-right font-sans font-semibold text-[10px] text-text mt-3"><span class="text-primary">*</span>Terms &amp; Condition apply</p>
                </div>

                <div x-data="{ copied: false }" class="bg-surface rounded-[20px] p-5 flex flex-col gap-2.5 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                    <p class="font-sans text-[11px] uppercase tracking-wide text-muted">Your promo code</p>
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-heading font-semibold text-primary text-[24px] leading-none">{{ $code }}</p>
                        <button type="button" @click="nextelCopy(@js($code)).then(ok => { if (!ok) return; copied = true; setTimeout(() => { copied = false }, 1500) })"
                                class="shrink-0 inline-flex items-center gap-1.5 bg-primary/10 text-primary rounded-full px-3 py-1.5 font-sans text-[13px]">
                            <i class="text-[15px]" :class="copied ? 'ri-check-line' : 'ri-file-copy-line'"></i>
                            <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                        </button>
                    </div>
                </div>

                {{-- Referral link temporarily hidden (per team request); promo code above stays. --}}
                {{--
                <div x-data="{ copied: false }" class="bg-surface rounded-[20px] p-5 flex flex-col gap-3 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                    <p class="font-sans text-[11px] uppercase tracking-wide text-muted">Your referral link</p>
                    <div class="bg-background rounded-[14px] px-3.5 py-3">
                        <p class="font-sans text-text text-[13px] truncate">{{ $link }}</p>
                    </div>
                    <div class="flex gap-2.5">
                        <button type="button" @click="nextelCopy(@js($link)).then(ok => { if (!ok) return; copied = true; setTimeout(() => { copied = false }, 1500) })"
                                class="flex-1 h-[44px] rounded-full bg-primary text-surface flex items-center justify-center gap-2 font-sans font-semibold text-[13px] hover:opacity-95 transition">
                            <i class="text-[16px]" :class="copied ? 'ri-check-line' : 'ri-file-copy-line'"></i>
                            <span x-text="copied ? 'Copied!' : 'Copy Link'">Copy Link</span>
                        </button>
                        <button type="button" @click="navigator.share ? navigator.share({ title: 'Join me on Nextel', url: @js($link) }) : nextelCopy(@js($link)).then(ok => { if (!ok) return; copied = true; setTimeout(() => copied = false, 1500) })"
                                class="flex-1 h-[44px] rounded-full bg-[#8a38f5] text-white flex items-center justify-center gap-2 font-sans font-semibold text-[13px] hover:opacity-95 transition">
                            <i class="ri-share-forward-line text-[16px]"></i> Share
                        </button>
                    </div>
                </div>
                --}}
            </div>

            <div class="lg:col-span-7 flex flex-col gap-[15px]" x-data="{ tab: 'direct' }">

                <div class="grid grid-cols-3 gap-2.5">
                    @foreach ([['Team', $directCount + $indirectCount, 'text-primary'], ['Direct', $directCount, 'text-success'], ['Earned', money($earned, 0), 'text-text']] as [$label, $value, $color])
                        <div class="bg-surface rounded-[16px] py-4 flex flex-col items-center gap-1 shadow-[0_4px_9px_rgba(194,194,194,0.07)]">
                            <p class="font-heading font-medium {{ $color }} text-[20px] leading-none">{{ $value }}</p>
                            <p class="font-sans text-muted text-[12px]">{{ $label }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center gap-2.5 sm:justify-between">
                    <div class="flex items-center gap-2 bg-surface rounded-full p-1 self-start">
                        <button type="button" @click="tab = 'direct'" class="px-4 py-2 rounded-full font-sans text-[13px] transition-colors" :class="tab === 'direct' ? 'bg-primary text-surface' : 'text-muted'">Direct ({{ $directCount }})</button>
                        <button type="button" @click="tab = 'indirect'" class="px-4 py-2 rounded-full font-sans text-[13px] transition-colors" :class="tab === 'indirect' ? 'bg-primary text-surface' : 'text-muted'">Indirect ({{ $indirectCount }})</button>
                    </div>

                    {{-- Search both levels by name or username. --}}
                    <label class="relative flex-1 sm:max-w-[260px]">
                        <i class="ri-search-line absolute left-3.5 top-1/2 -translate-y-1/2 text-muted text-[15px]"></i>
                        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name or username"
                               class="w-full h-[42px] rounded-full bg-surface border border-muted/15 pl-10 pr-4 font-sans text-[13px] text-text placeholder:text-muted focus:outline-none focus:border-primary/40 transition">
                    </label>
                </div>

                <div x-show="tab === 'direct'">
                    @if ($direct->isEmpty())
                        <div class="bg-surface rounded-[20px] p-8 flex flex-col items-center gap-2.5 text-center shadow-[0_4px_9px_rgba(194,194,194,0.07)]">
                            <span class="size-12 rounded-full bg-background flex items-center justify-center text-muted"><i class="{{ $searching ? 'ri-search-line' : 'ri-team-line' }} text-[22px]"></i></span>
                            @if ($searching)
                                <p class="font-sans text-muted text-[14px]">No team member matches “{{ trim($search) }}”.</p>
                                <button type="button" wire:click="$set('search', '')" class="font-sans font-semibold text-primary text-[13px]">Clear search</button>
                            @else
                                <p class="font-sans text-muted text-[14px]">No one has joined with your code yet — share your link to grow your team.</p>
                            @endif
                        </div>
                    @else
                        <div class="bg-surface rounded-[20px] p-2 flex flex-col divide-y divide-muted/10 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                            @foreach ($direct as $member)
                                <x-app.team-member :member="$member" wire:click="showMember('{{ $member->id }}')" class="cursor-pointer hover:bg-background/60 rounded-[14px] transition-colors" />
                            @endforeach
                        </div>
                    @endif
                </div>

                <div x-show="tab === 'indirect'" x-cloak>
                    @if ($indirect->isEmpty())
                        <div class="bg-surface rounded-[20px] p-8 flex flex-col items-center gap-2.5 text-center shadow-[0_4px_9px_rgba(194,194,194,0.07)]">
                            <span class="size-12 rounded-full bg-background flex items-center justify-center text-muted"><i class="{{ $searching ? 'ri-search-line' : 'ri-team-line' }} text-[22px]"></i></span>
                            @if ($searching)
                                <p class="font-sans text-muted text-[14px]">No team member matches “{{ trim($search) }}”.</p>
                                <button type="button" wire:click="$set('search', '')" class="font-sans font-semibold text-primary text-[13px]">Clear search</button>
                            @else
                                <p class="font-sans text-muted text-[14px]">No indirect members yet — they appear when your team invites others.</p>
                            @endif
                        </div>
                    @else
                        <div class="bg-surface rounded-[20px] p-2 flex flex-col divide-y divide-muted/10 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                            @foreach ($indirect as $member)
                                <x-app.team-member :member="$member" wire:click="showMember('{{ $member->id }}')" class="cursor-pointer hover:bg-background/60 rounded-[14px] transition-colors" />
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <x-app.bottom-nav />

    <x-app.referral-details :member="$selectedMember" :level="$selectedLevel" :upline="$selectedUpline" />
</div>
