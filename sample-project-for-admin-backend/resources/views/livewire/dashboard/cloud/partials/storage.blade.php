{{-- Cloud Storage: how many sponsor contacts you may keep, where those slots came from, and how to
     add more. Status reads first; the buy/redeem flow stays folded away until asked for, so the card
     is short for the common case (nothing to do). The picker is client-side (deferred @entangle).
     A plan can be paid for directly (card, via Korapay) or settled with a vendor token — the
     vendor route stays as the fallback. --}}

@php
    $extraQuota = (int) $topUps->sum('extra_contacts');
    $storagePct = $quota > 0 ? min(100, (int) round($quotaUsed / $quota * 100)) : 0;
    $slotsLeft = $quota > 0 ? max(0, $quota - $quotaUsed) : null;
    $full = $slotsLeft === 0;

    // Which actions apply: with a plan already active the user Renews (near expiry) or switches; a
    // first-time user just buys storage. Plans stay hidden until one of these is chosen.
    $directPay = $directPay ?? false;
    $hasRenew = $plans->contains(fn ($p) => $p['mode'] === 'renew');
    $hasSwitch = $plans->contains(fn ($p) => $p['mode'] === 'switch');
    $hasCurrentPlan = $hasRenew || $hasSwitch;
@endphp

<div class="{{ $card }} p-5 flex flex-col gap-4"
     x-data="{ plan: @entangle('selectedPlanId') }">

    <div class="flex items-start gap-2.5">
        <span class="size-10 rounded-[12px] bg-primary/10 flex items-center justify-center text-primary shrink-0"><i class="ri-database-2-line text-[20px]"></i></span>
        <div class="min-w-0 flex-1">
            <p class="font-heading text-text text-[15px]">Cloud Storage</p>
            <p class="font-sans text-muted text-[12px]">How many sponsor contacts you can keep saved.</p>
        </div>
    </div>

    @if ($quota > 0)
        <div>
            <div class="flex items-end justify-between gap-3">
                <p class="font-heading font-semibold text-text text-[26px] leading-none tabular-nums">
                    {{ $slotsLeft }}<span class="font-sans font-normal text-muted text-[13px]"> {{ Str::plural('slot', $slotsLeft) }} free</span>
                </p>
                <span class="font-sans text-muted text-[11.5px] tabular-nums">{{ $quotaUsed }} / {{ $quota }} used</span>
            </div>

            <div class="h-1.5 rounded-full bg-background overflow-hidden mt-2.5">
                <div class="h-full rounded-full {{ $full ? 'bg-warning' : 'bg-primary' }} transition-all duration-300" style="width: {{ $storagePct }}%"></div>
            </div>

            <div class="flex flex-wrap items-center gap-1.5 mt-2.5">
                <span class="inline-flex items-center gap-1 rounded-full bg-background text-muted font-sans text-[11px] px-2 py-0.5">
                    <i class="ri-sim-card-line text-[12px]"></i> {{ $baseQuota }} with {{ $packageName }}
                </span>
                @if ($extraQuota > 0)
                    <span class="inline-flex items-center gap-1 rounded-full bg-secondary/40 text-primary font-sans text-[11px] px-2 py-0.5">
                        <i class="ri-add-line text-[12px]"></i> {{ $extraQuota }} from storage plans
                    </span>
                @endif
            </div>
        </div>
    @else
        <p class="font-sans text-muted text-[12.5px]">Unlimited slots on {{ $packageName }}.</p>
    @endif

    @if ($topUps->isNotEmpty())
        <div class="flex flex-col gap-1.5">
            <p class="{{ $eyebrow }}">Active storage plans</p>
            @foreach ($topUps as $topUp)
                @php
                    $expiring = $topUp->expires_at !== null && $topUp->expires_at->lessThan(now()->addDays(8));
                @endphp
                <div wire:key="top-up-{{ $topUp->id }}" class="flex items-center justify-between gap-2 rounded-[12px] bg-background px-3 py-2.5">
                    <div class="min-w-0">
                        <p class="font-sans text-text text-[12.5px] truncate">{{ $topUp->cloudStoragePlan?->name ?? 'Storage plan' }}</p>
                        <p class="font-sans text-muted text-[11px]">+{{ $topUp->extra_contacts }} {{ Str::plural('slot', $topUp->extra_contacts) }}@if ($topUp->extra_sms > 0) {{ ' +'.$topUp->extra_sms }} SMS/day @endif</p>
                    </div>
                    @if ($topUp->expires_at === null)
                        <span class="shrink-0 inline-flex items-center gap-1 rounded-full bg-secondary/40 text-primary font-sans text-[10.5px] px-2 py-0.5"><i class="ri-infinity-line text-[12px]"></i> Lifetime</span>
                    @else
                        <span class="shrink-0 inline-flex items-center gap-1 rounded-full font-sans text-[10.5px] px-2 py-0.5 {{ $expiring ? 'bg-warning/15 text-warning' : 'bg-muted/15 text-muted' }}" title="Expires {{ $topUp->expires_at->format('j M Y, g:i A') }}">
                            <i class="ri-time-line text-[12px]"></i> Till {{ $topUp->expires_at->format('j M Y') }}
                        </span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    @if ($plans->isEmpty())
        <p class="font-sans text-muted text-[12px] pt-3 border-t border-muted/10">No storage plans are on sale right now.</p>
    @else
        {{-- Two ways to settle a plan: pay directly with a card, or redeem a token bought from a
             vendor. With a plan active you Renew it (near expiry) or switch to another — one at a
             time; first-timers just buy storage. Plans stay hidden until an action is chosen. --}}
        <div class="pt-3.5 border-t border-muted/10 flex flex-col gap-3" wire:key="storage-picker">
            @if ($hasCurrentPlan)
                <div class="grid grid-cols-2 gap-2">
                    @if ($hasRenew)
                        <button type="button" wire:click="setPicker('renew')"
                                @class([
                                    'h-10 rounded-[12px] border font-sans text-[13px] transition inline-flex items-center justify-center gap-1.5',
                                    'border-primary bg-primary/[0.06] text-primary' => $pickerTab === 'renew',
                                    'border-muted/15 bg-background text-text hover:border-muted/30' => $pickerTab !== 'renew',
                                ])>
                            <i class="ri-refresh-line text-[15px]"></i> Renew
                        </button>
                    @endif
                    @if ($hasSwitch)
                        <button type="button" wire:click="setPicker('switch')"
                                @class([
                                    'h-10 rounded-[12px] border font-sans text-[13px] transition inline-flex items-center justify-center gap-1.5',
                                    'col-span-2' => ! $hasRenew,
                                    'border-primary bg-primary/[0.06] text-primary' => $pickerTab === 'switch',
                                    'border-muted/15 bg-background text-text hover:border-muted/30' => $pickerTab !== 'switch',
                                ])>
                            <i class="ri-swap-box-line text-[15px]"></i> Change plan
                        </button>
                    @endif
                </div>
            @else
                <button type="button" wire:click="setPicker('activate')"
                        class="w-full flex items-center justify-between gap-2 text-left">
                    <span class="min-w-0">
                        <span class="block font-sans text-text text-[13px]">{{ $full ? 'Storage full — buy more storage' : 'Buy more storage' }}</span>
                        <span class="block font-sans text-muted text-[11.5px]">{{ $directPay ? 'Pay directly or redeem a storage plan token' : 'Redeem a storage plan token' }}</span>
                    </span>
                    <i class="ri-arrow-down-s-line text-muted text-[18px] shrink-0 transition {{ $pickerTab === 'activate' ? 'rotate-180' : '' }}"></i>
                </button>
            @endif

            @if ($pickerTab)
                <div class="flex flex-col gap-2.5">
                    @foreach ($plans as $p)
                        @php
                            $group = $p['mode'] === 'renew' ? 'renew' : ($p['mode'] === 'switch' ? 'switch' : 'activate');
                        @endphp
                        @continue($group !== $pickerTab)
                        @php
                            $selectable = $p['available'];
                            $isRenew = $p['mode'] === 'renew';
                            $durationLabel = $p['duration_days'] === 0 ? 'lifetime' : $p['duration_days'].' '.Str::plural('day', $p['duration_days']);
                        @endphp
                        {{-- A plan can be settled two ways: pay for it directly (card/Korapay) or
                             redeem a plan token bought from a vendor. Selecting highlights it for the
                             redeem field; the Pay button posts straight to the purchase route. --}}
                        <div wire:key="plan-{{ $p['id'] }}"
                             @if ($selectable) :class="plan === @js($p['id']) ? 'border-primary bg-primary/[0.06]' : 'border-muted/15 bg-background hover:border-muted/30'" @endif
                             class="w-full rounded-[14px] border overflow-hidden transition {{ $selectable ? '' : 'opacity-60 border-muted/15 bg-background' }}">
                            <button type="button"
                                    @if ($selectable) @click="plan = (plan === @js($p['id']) ? null : @js($p['id']))" @else disabled @endif
                                    class="w-full text-left px-3.5 pt-3 {{ $directPay && $selectable ? 'pb-2' : 'pb-3' }} flex items-center gap-3 {{ $selectable ? '' : 'cursor-not-allowed' }}">
                                <span class="size-[18px] rounded-full shrink-0 flex items-center justify-center transition {{ $selectable ? 'border-2' : 'bg-muted/10 text-muted' }}"
                                      @if ($selectable) :class="plan === @js($p['id']) ? 'border-primary' : 'border-muted/40'" @endif>
                                    @if ($selectable)
                                        <span class="size-2 rounded-full bg-primary" x-show="plan === @js($p['id'])" x-cloak></span>
                                    @else
                                        <i class="ri-time-line text-[11px]"></i>
                                    @endif
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-sans text-text text-[13px] truncate">{{ $p['name'] }}</span>
                                    <span class="block font-sans text-muted text-[11.5px] truncate">+{{ $p['extra_contacts'] }} slots @if ($p['extra_sms'] > 0) {{ '+'.$p['extra_sms'] }} SMS/day @endif {{ $durationLabel }}</span>
                                    @if ($isRenew && ! $selectable)
                                        <span class="block font-sans text-warning text-[11px] mt-0.5">Available within a day of expiry {{ $p['opens_at'] ? ' (from '.$p['opens_at'].')' : '' }}</span>
                                    @endif
                                </span>
                                <span class="shrink-0 font-heading text-text text-[13px] tabular-nums"><x-money :usd="$p['price']" /></span>
                            </button>

                            @if ($directPay && $selectable)
                                <form method="POST" action="{{ route('cloud.storage.buy.start') }}" class="px-3.5 pb-3">
                                    @csrf
                                    <input type="hidden" name="plan_id" value="{{ $p['id'] }}">
                                    <input type="hidden" name="mode" value="{{ $p['mode'] }}">
                                    <button type="submit"
                                            class="w-full h-9 rounded-[10px] border border-primary/25 bg-primary/[0.06] text-primary font-sans text-[12px] inline-flex items-center justify-center gap-1.5 hover:bg-primary/10 transition">
                                        <i class="ri-secure-payment-line text-[14px]"></i> Pay directly with card
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endforeach

                <div class="flex gap-2">
                    <input type="text" wire:model="redeemCode" wire:keydown.enter="redeemToken" placeholder="TOKEN" autocomplete="off" aria-label="Cloud storage token"
                           class="flex-1 min-w-0 h-11 rounded-[12px] bg-background border border-muted/15 px-3 font-mono text-[13px] tracking-wider uppercase text-text placeholder:text-muted/50 placeholder:tracking-normal placeholder:font-sans focus:outline-none focus:border-primary/40 transition">
                    <button type="button" wire:click="redeemToken" wire:loading.attr="disabled" wire:target="redeemToken" x-bind:disabled="! plan"
                            class="shrink-0 inline-flex items-center justify-center gap-1.5 h-11 px-4 rounded-[12px] bg-primary text-surface font-sans text-[13px] hover:opacity-95 transition disabled:opacity-40 disabled:cursor-not-allowed">
                        <i class="ri-key-2-line text-[15px]"></i> Redeem
                    </button>
                </div>

                @error('selectedPlanId')<p class="font-sans text-danger text-[12px]">{{ $message }}</p>@enderror
                @error('redeemCode')<p class="font-sans text-danger text-[12px]">{{ $message }}</p>@enderror

                    {{-- Vendor tokens remain the fallback when a card is not an option. --}}
                    <a href="{{ route('agents') }}" class="inline-flex items-center gap-1.5 font-sans text-primary text-[12px] hover:underline">
                        <i class="ri-store-2-line"></i> Buy a storage plan from a vendor
                    </a>
                </div>
            @endif
        </div>
    @endif
</div>
