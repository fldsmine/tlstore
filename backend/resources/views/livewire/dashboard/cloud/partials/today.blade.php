{{-- "Your Cloud today" — the single panel that answers what you earned, what's left, and what to do next.
     Inherits $card/$eyebrow and the render() payload from the @include scope. --}}

@php
    $ready = $myNumbers->where('disabled', false)->values();
    $next = $ready->first();

    $dailyPct = $dailyCap > 0 ? min(100, (int) round($todayEarned / $dailyCap * 100)) : 0;
    $dailyLeft = $dailyCap > 0 ? max(0, $dailyCap - $todayEarned) : 0;
    $dailyDone = $dailyCap > 0 && $todayEarned >= $dailyCap;
    $hourlyDone = $hourlyCap > 0 && $hourlyEarned >= $hourlyCap;

    $bonusTotal = (float) $bonuses->sum('amount');
    $slotsLeft = $quota > 0 ? max(0, $quota - $quotaUsed) : null;

    // One next step, highest-value first — the page's answer to "what do I do now?".
    [$stepIcon, $stepText] = match (true) {
        $quotaUsed === 0 => ['ri-user-add-line', 'Add a sponsor contact to start earning from calls.'],
        $bonusTotal > 0 => ['ri-gift-line', 'You have '.money($bonusTotal).' in sponsor bonuses waiting to be claimed.'],
        $dailyDone => ['ri-checkbox-circle-line', 'Daily cap reached — your earning limit resets at midnight.'],
        $hourlyDone => ['ri-time-line', 'Hourly cap reached — more earning room opens at the top of the next hour.'],
        $ready->isNotEmpty() => ['ri-phone-line', $ready->count().' '.Str::plural('contact', $ready->count()).' ready to call right now.'],
        $inboxUnread > 0 => ['ri-mail-open-line', 'Read '.$inboxUnread.' sponsored '.Str::plural('message', $inboxUnread).' to keep earning.'],
        default => ['ri-sparkling-line', 'All caught up. New sponsored calls and messages land through the day.'],
    };

@endphp

<div class="{{ $card }} relative overflow-hidden p-5 lg:p-6 flex flex-col gap-5 h-full">
    <div class="pointer-events-none absolute -top-28 -right-20 size-72 rounded-full opacity-70"
         style="background: radial-gradient(circle, rgba(220,235,106,0.45), rgba(220,235,106,0) 68%);"></div>

    <div class="relative flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="{{ $eyebrow }}">Earned today</p>
            <p class="font-heading font-semibold text-text text-[30px] leading-none mt-2"><x-money :usd="$todayEarned" /></p>
            <p class="font-sans text-muted text-[12px] mt-2">
                {{ $callsToday }} {{ Str::plural('call', $callsToday) }} today
            </p>
        </div>

        <div class="shrink-0 flex flex-col items-end gap-1.5">
            @if ($dailyDone)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-secondary/30 text-primary font-sans text-[11px] px-2.5 py-1"><i class="ri-checkbox-circle-fill text-[13px]"></i> Cap reached</span>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full bg-success/12 text-success font-sans text-[11px] px-2.5 py-1"><span class="size-1.5 rounded-full bg-success"></span> Earning open</span>
            @endif
            @if ($hourlyCap > 0)
                <span class="font-sans text-[11px] {{ $hourlyDone ? 'text-warning' : 'text-muted' }}">This hour {{ money($hourlyEarned) }} / {{ money($hourlyCap) }}</span>
            @endif
        </div>
    </div>

    @if ($dailyCap > 0)
        <div class="relative">
            <div class="h-2 rounded-full bg-background overflow-hidden">
                <div class="h-full rounded-full {{ $dailyDone ? 'bg-secondary' : 'bg-primary' }} transition-all duration-500" style="width: {{ $dailyPct }}%"></div>
            </div>
            <div class="flex items-center justify-between gap-3 mt-1.5">
                <span class="font-sans text-muted text-[11px]">{{ $dailyPct }}% of today's cap</span>
                <span class="font-sans text-[11px] {{ $dailyDone ? 'text-muted' : 'text-text' }}">{{ $dailyDone ? 'Resets at midnight' : money($dailyLeft).' still earnable' }}</span>
            </div>
        </div>
    @endif

    <div class="relative flex flex-col sm:flex-row gap-2.5 mt-auto">
        @if ($next)
            <button type="button" @click="$dispatch('cloud-start-call', @js($next))"
                    class="flex-1 h-[48px] rounded-[30px] bg-primary text-surface font-sans font-semibold text-[14px] inline-flex items-center justify-center gap-2 hover:opacity-95 transition">
                <i class="ri-phone-line text-[18px]"></i> Call {{ Str::limit($next['name'], 18) }}
                <span class="font-sans font-normal text-secondary text-[12px]">+{{ money($next['earning']) }}</span>
            </button>
        @else
            <button type="button" @click="addOpen = true; q = ''" @disabled($slotsLeft === 0)
                    class="flex-1 h-[48px] rounded-[30px] bg-primary text-surface font-sans font-semibold text-[14px] inline-flex items-center justify-center gap-2 hover:opacity-95 transition disabled:opacity-40 disabled:cursor-not-allowed">
                <i class="ri-user-add-line text-[18px]"></i> {{ $quotaUsed === 0 ? 'Add your first contact' : 'Add another contact' }}
            </button>
        @endif

        @if ($next)
            <button type="button" @click="addOpen = true; q = ''" @disabled($slotsLeft === 0)
                    class="sm:flex-none h-[48px] px-5 rounded-[30px] bg-background text-text font-sans font-semibold text-[14px] inline-flex items-center justify-center gap-2 hover:bg-muted/15 transition disabled:opacity-40 disabled:cursor-not-allowed">
                <i class="ri-user-add-line text-[17px]"></i> Add contact
            </button>
        @endif
    </div>

    <div class="relative flex items-start gap-2 rounded-[14px] bg-primary/[0.06] px-3.5 py-2.5">
        <i class="{{ $stepIcon }} text-primary text-[16px] leading-[1.35] shrink-0"></i>
        <p class="font-sans text-text/80 text-[12px] leading-relaxed">{{ $stepText }}</p>
    </div>
</div>
