<div x-data="cloudCalls()" wire:poll.30s="heartbeat"
     @cloud-call-rejected.window="reset()"
     @cloud-incoming-answered.window="playAnswered($event.detail.call)"
     @cloud-start-call.window="call($event.detail)">

    {{-- Incoming broadcast, server-detected via the poll. --}}
    @if ($incoming)
        <div x-show="!active" wire:key="incoming-{{ $incoming['broadcastId'] }}" data-lock-scroll
             class="fixed inset-0 z-[94] bg-background lg:flex lg:items-center lg:justify-center lg:p-10 overflow-y-auto">
            <div class="mx-auto w-full max-w-[440px] lg:max-w-[470px] min-h-full lg:min-h-0 px-4 lg:px-9 pt-8 lg:pt-10 pb-12 lg:pb-10 flex flex-col items-center gap-7 lg:bg-surface lg:rounded-[32px] lg:shadow-[0_30px_80px_-20px_rgba(24,68,62,0.25)] lg:border lg:border-black/[0.04]">
                {{-- Rings while mounted; stops on unmount. --}}
                <div x-data="cloudIncomingRing()" class="hidden" aria-hidden="true"></div>
                <p class="font-heading font-medium text-muted text-[13px] tracking-[0.16em] uppercase mt-2 animate-pulse">Incoming Sponsored Call</p>

                <div class="flex flex-col items-center gap-3 mt-6">
                    <div class="relative flex items-center justify-center">
                        <span class="absolute size-[148px] rounded-full bg-primary/10 animate-ping"></span>
                        <span class="absolute size-[128px] rounded-full bg-primary/10"></span>
                        <span class="relative size-[104px] rounded-full bg-primary text-white flex items-center justify-center ring-4 ring-surface shadow-[0_10px_30px_rgba(24,68,62,0.25)] font-heading font-semibold text-[38px]">{{ strtoupper(substr($incoming['caller'] ?: $incoming['name'], 0, 1)) }}</span>
                    </div>
                    <div class="flex flex-col items-center gap-1.5 text-center">
                        <p class="font-heading font-semibold text-text text-[24px] leading-none">{{ $incoming['caller'] ?: $incoming['name'] }}</p>
                        <span class="inline-flex items-center gap-1.5 bg-background lg:bg-surface rounded-full px-3 py-1 shadow-sm">
                            <span class="size-1.5 rounded-full bg-primary"></span>
                            <span class="font-sans text-muted text-[13px]">{{ trim(($incoming['brand'] ? $incoming['brand'].' · ' : '').($incoming['line'] ?? '')) }}</span>
                        </span>
                        @if ($incoming['caller'])<p class="font-sans text-muted/70 text-[12px]">re: {{ $incoming['name'] }}</p>@endif
                    </div>
                </div>

                <div class="bg-secondary/10 border border-secondary/40 rounded-[16px] px-4 py-3 flex items-center gap-2.5 w-full">
                    <i class="ri-money-dollar-circle-line text-primary text-[18px] shrink-0"></i>
                    <p class="font-sans text-muted text-[12px] leading-relaxed">Answer and listen to the full message to earn from this sponsored call.</p>
                </div>

                <div class="flex items-center justify-center gap-12 mt-auto pt-6">
                    <button type="button" wire:click="declineBroadcast('{{ $incoming['broadcastId'] }}')" class="flex flex-col items-center gap-2" aria-label="Decline">
                        <span class="size-16 rounded-full bg-danger text-white flex items-center justify-center shadow-[0_8px_20px_rgba(206,60,60,0.3)]"><i class="ri-phone-fill text-[24px] rotate-[135deg]"></i></span>
                        <span class="font-sans text-muted text-[12px]">Decline</span>
                    </button>
                    <button type="button" wire:click="answerBroadcast('{{ $incoming['broadcastId'] }}')" class="flex flex-col items-center gap-2" aria-label="Accept">
                        <span class="size-16 rounded-full bg-success text-white flex items-center justify-center shadow-[0_8px_20px_rgba(34,140,90,0.3)] animate-bounce"><i class="ri-phone-fill text-[24px]"></i></span>
                        <span class="font-sans text-muted text-[12px]">Accept</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Call screen overlay, driven by Web Speech API. --}}
    <template x-if="active">
        {{-- The payload's earning is USD, so the screen gets the reader's per-USD rate and its
             symbol — otherwise it contradicts the contact card that dispatched the call. --}}
        <div class="fixed inset-0 z-[95] bg-background lg:flex lg:items-center lg:justify-center lg:p-10 overflow-y-auto" data-lock-scroll
             x-data="{ rate: {{ \App\Support\DisplayCurrency::convert(1) }}, symbol: @js(\App\Support\DisplayCurrency::symbol()) }">
            <div class="mx-auto w-full max-w-[440px] lg:max-w-[470px] min-h-full lg:min-h-0 px-4 lg:px-9 pt-6 lg:pt-9 pb-12 lg:pb-9 flex flex-col gap-6 lg:bg-surface lg:rounded-[32px] lg:shadow-[0_30px_80px_-20px_rgba(24,68,62,0.25)] lg:border lg:border-black/[0.04]">

                <div class="flex items-center gap-3">
                    <p class="font-heading font-medium text-muted text-[13px] tracking-[0.14em] uppercase" x-text="(active.direction === 'incoming' ? 'Incoming' : 'Sponsored') + ' Call'">Sponsored Call</p>
                    <span class="inline-flex items-center gap-1.5 ml-auto">
                        <span class="size-2 rounded-full" :class="phase === 'connecting' ? 'bg-[#D79937] animate-pulse' : (phase === 'complete' ? 'bg-success' : 'bg-success animate-pulse')"></span>
                        <span class="font-sans text-[12px]" :class="phase === 'connecting' ? 'text-[#D79937]' : 'text-success'" x-text="phase === 'connecting' ? 'Ringing…' : (phase === 'complete' ? (done ? 'Reward claimed' : 'Message complete') : 'Connected')">Connected</span>
                    </span>
                </div>

                <div class="flex flex-col items-center gap-3 pt-1">
                    <div class="relative flex items-center justify-center">
                        <span class="absolute size-[120px] rounded-full" :class="phase === 'connecting' ? 'bg-[#D79937]/20 animate-ping' : (phase === 'complete' ? 'bg-success/10' : 'bg-success/15 animate-ping')"></span>
                        <span class="absolute size-[108px] rounded-full" :class="phase === 'connecting' ? 'bg-[#D79937]/10' : 'bg-success/10'"></span>
                        <span class="relative size-[88px] rounded-full bg-primary text-white flex items-center justify-center ring-4 ring-surface shadow-[0_8px_24px_rgba(24,68,62,0.18)] overflow-hidden">
                            <template x-if="active.avatar"><img :src="active.avatar" alt="" class="size-full object-cover"></template>
                            <span x-show="!active.avatar" class="font-heading font-semibold text-[32px]" x-text="(active.caller || active.name || '?').charAt(0)"></span>
                        </span>
                    </div>
                    <div class="flex flex-col items-center gap-1.5 text-center">
                        <p class="font-heading font-semibold text-text text-[24px] leading-none" x-text="active.caller || active.name"></p>
                        <span class="inline-flex items-center gap-1.5 bg-surface lg:bg-background rounded-full px-3 py-1 shadow-sm">
                            <span class="size-1.5 rounded-full bg-primary"></span>
                            <span class="font-sans text-muted text-[13px]" x-text="(active.brand ? active.brand + ' · ' : '') + active.line"></span>
                        </span>
                    </div>
                </div>

                <div class="flex gap-3">
                    <div class="flex-1 min-w-0 bg-surface lg:bg-background rounded-[20px] px-3 sm:px-4 py-5 flex flex-col items-center gap-1.5 text-center shadow-[0_4px_9px_rgba(194,194,194,0.1)] lg:shadow-none">
                        <p class="font-sans text-muted text-[12px]">On the line</p>
                        <p class="font-heading font-semibold text-text text-[26px] sm:text-[30px] leading-none tabular-nums" x-text="fmt(elapsed)">0:00</p>
                    </div>
                    <div class="flex-1 min-w-0 bg-primary rounded-[20px] px-3 sm:px-4 py-5 flex flex-col items-center gap-1.5 text-center shadow-[0_8px_20px_rgba(24,68,62,0.2)]">
                        <p class="font-sans text-white/70 text-[12px]" x-text="done ? 'Earned' : 'You\'ll earn'">You'll earn</p>
                        <p class="font-heading font-semibold text-secondary text-[26px] sm:text-[30px] leading-none tabular-nums" x-text="symbol + (Number(active.earning || 0) * rate).toFixed(2)">{{ money(0) }}</p>
                    </div>
                </div>

                {{-- Earn only after fully listening. --}}
                <div class="bg-surface lg:bg-background rounded-[16px] px-4 py-3.5 flex flex-col gap-2.5 shadow-[0_4px_9px_rgba(194,194,194,0.08)] lg:shadow-none">
                    <div class="flex items-start gap-2.5">
                        <i class="ri-volume-up-line text-primary text-[18px] shrink-0 mt-0.5" :class="phase === 'speaking' && 'animate-pulse'"></i>
                        <p class="font-sans text-muted text-[12px] leading-relaxed flex-1"
                           x-text="phase === 'connecting' ? 'Connecting your sponsored call…' : (done ? 'Reward claimed — added to your Cloud balance.' : (phase === 'complete' ? 'Message complete — tap “Claim offer” below to collect your reward.' : 'Listen to the full message, then claim your reward. Hanging up early forfeits it.'))">Listen to the full message to earn.</p>
                    </div>
                    <div class="h-1.5 rounded-full bg-muted/15 overflow-hidden">
                        <div class="h-full rounded-full bg-secondary transition-all duration-200" :style="'width:' + progress + '%'"></div>
                    </div>
                </div>

                {{-- Slides in part-way through the call. --}}
                <div x-show="showPrompt" x-transition.opacity.duration.300ms
                     class="bg-secondary/10 border border-secondary/40 rounded-[18px] px-4 py-4 flex flex-col gap-3">
                    <span class="inline-flex items-center gap-1.5 self-start rounded-full bg-primary/10 text-primary px-2.5 py-1 font-sans text-[11px] uppercase tracking-wide"><i class="ri-megaphone-line"></i> Sponsor message</span>
                    <p class="font-sans text-text text-[14px] leading-relaxed" x-text="active.popup"></p>
                    {{-- Clickable only once the message completes; books reward + opens offer. --}}
                    <button type="button" @click="claim()" :disabled="phase !== 'complete' || done"
                            class="w-full inline-flex items-center justify-center gap-1.5 h-11 rounded-[14px] bg-secondary text-primary font-sans font-semibold text-[13px] sm:text-[14px] hover:brightness-95 transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="ri-gift-line shrink-0"></i>
                        <span class="truncate" x-text="done ? 'Reward claimed' : (phase === 'complete' ? (active.claimUrl ? 'Claim offer' : 'Claim reward') : 'Listen to the full message to claim')"></span>
                    </button>
                    <p class="font-sans text-muted text-[11px] text-center">This is a paid sponsor message. Listen fully, then claim to earn your reward.</p>
                </div>

                <button type="button" @click="hangUp()" x-show="!done"
                        class="mt-auto h-[56px] rounded-full bg-danger text-white flex items-center justify-center gap-2 font-sans font-semibold text-[15px] hover:opacity-95 transition shadow-[0_8px_20px_rgba(206,60,60,0.3)]">
                    <i class="ri-phone-fill text-[18px] rotate-[135deg]"></i>
                    <span x-text="(phase === 'speaking' || phase === 'complete') ? 'End call (forfeit reward)' : 'End call'">End call</span>
                </button>
                <button type="button" @click="reset()" x-show="done"
                        class="mt-auto h-[56px] rounded-full bg-primary text-white flex items-center justify-center gap-2 font-sans font-semibold text-[15px] hover:opacity-95 transition">
                    <i class="ri-check-line text-[18px]"></i> Done
                </button>
            </div>
        </div>
    </template>
</div>
