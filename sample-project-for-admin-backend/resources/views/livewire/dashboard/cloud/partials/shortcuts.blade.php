{{-- Secondary destinations that belong to Cloud but live on their own screens. --}}

<div class="{{ $card }} overflow-hidden">
    @if ($canSms ?? false)
        <a href="{{ route('cloud.inbox') }}" wire:navigate class="flex items-center gap-3 px-4 py-3.5 border-b border-muted/10 hover:bg-background/60 transition">
            <span class="size-11 rounded-[14px] bg-primary/10 flex items-center justify-center text-primary shrink-0"><i class="ri-mail-line text-[21px]"></i></span>
            <div class="min-w-0 flex-1">
                <p class="font-heading font-medium text-text text-[14.5px]">Cloud Inbox</p>
                <p class="font-sans text-muted text-[12px] truncate">Read sponsored messages to earn</p>
            </div>
            @if ($inboxUnread > 0)
                <span class="shrink-0 min-w-[22px] h-[22px] px-1.5 rounded-full bg-primary text-surface text-[12px] font-sans font-semibold flex items-center justify-center">{{ $inboxUnread > 99 ? '99+' : $inboxUnread }}</span>
            @endif
            <i class="ri-arrow-right-s-line text-muted text-[20px] shrink-0"></i>
        </a>
    @endif

    <a href="{{ route('special-coupon') }}" wire:navigate class="flex items-center gap-3 px-4 py-3.5 border-b border-muted/10 hover:bg-background/60 transition">
        <span class="size-11 rounded-[14px] bg-success/10 flex items-center justify-center text-success shrink-0"><i class="ri-gift-2-line text-[21px]"></i></span>
        <div class="min-w-0 flex-1">
            <p class="font-heading font-medium text-text text-[14.5px]">Revenue Authentication</p>
            <p class="font-sans text-muted text-[12px] truncate">Activate additional storage</p>
        </div>
        <i class="ri-arrow-right-s-line text-muted text-[20px] shrink-0"></i>
    </a>

    <a href="{{ route('shareholder') }}" wire:navigate class="flex items-center gap-3 px-4 py-3.5 border-b border-muted/10 hover:bg-background/60 transition">
        <span class="size-11 rounded-[14px] bg-secondary/25 flex items-center justify-center text-primary shrink-0"><i class="ri-pie-chart-2-line text-[21px]"></i></span>
        <div class="min-w-0 flex-1">
            <p class="font-heading font-medium text-text text-[14.5px]">Shareholder Portal</p>
            <p class="font-sans text-muted text-[12px] truncate">Claim your share of the call-revenue pool</p>
        </div>
        <i class="ri-arrow-right-s-line text-muted text-[20px] shrink-0"></i>
    </a>

    <a href="{{ route('withdraw', ['wallet' => 'cloud_call']) }}" wire:navigate class="flex items-center gap-3 px-4 py-3.5 hover:bg-background/60 transition">
        <span class="size-11 rounded-[14px] bg-primary/10 flex items-center justify-center text-primary shrink-0"><i class="ri-bank-card-line text-[21px]"></i></span>
        <div class="min-w-0 flex-1">
            <p class="font-heading font-medium text-text text-[14.5px]">Withdraw N-Cloud Calls</p>
            <p class="font-sans text-muted text-[12px] truncate">Balance <x-money :usd="$balance" /></p>
        </div>
        <i class="ri-arrow-right-s-line text-muted text-[20px] shrink-0"></i>
    </a>
</div>
