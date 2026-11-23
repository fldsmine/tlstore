{{-- Package includes the sponsored inbox but not calling or contacts. --}}

<div class="flex flex-col gap-[22px] lg:grid lg:grid-cols-3 lg:gap-[22px] lg:items-start">
    <div class="lg:col-span-2 flex flex-col gap-[22px]">
        <div class="{{ $card }} relative overflow-hidden p-5 lg:p-6 flex flex-col gap-5">
            <div class="pointer-events-none absolute -top-28 -right-20 size-72 rounded-full opacity-70"
                 style="background: radial-gradient(circle, rgba(220,235,106,0.45), rgba(220,235,106,0) 68%);"></div>

            <div class="relative flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="{{ $eyebrow }}">Unread messages</p>
                    <p class="font-heading font-semibold text-text text-[30px] leading-none mt-2 tabular-nums">{{ $inboxUnread }}</p>
                    <p class="font-sans text-muted text-[12px] mt-2">Sponsored messages waiting in your Cloud inbox</p>
                </div>
                <span class="shrink-0 size-12 rounded-[16px] bg-primary/10 flex items-center justify-center text-primary"><i class="ri-message-3-line text-[24px]"></i></span>
            </div>

            <p class="relative font-sans text-muted text-[13px] leading-relaxed">
                {{ $packageName ?? 'Your plan' }} includes the sponsored inbox. Open a message, read it through, and the reward lands in your N-Cloud SMS wallet.
            </p>

            <div class="relative flex flex-col sm:flex-row gap-2.5">
                <a href="{{ route('cloud.inbox') }}" wire:navigate
                   class="flex-1 h-[48px] rounded-[30px] bg-primary text-surface font-sans font-semibold text-[14px] inline-flex items-center justify-center gap-2 hover:opacity-95 transition">
                    <i class="ri-inbox-line text-[18px]"></i> Open your inbox
                    @if ($inboxUnread > 0)<span class="min-w-[20px] h-5 px-1.5 rounded-full bg-surface/25 font-sans text-[11px] flex items-center justify-center">{{ $inboxUnread > 99 ? '99+' : $inboxUnread }}</span>@endif
                </a>
                @if ($balance > 0)
                    <a href="{{ route('withdraw', ['wallet' => 'cloud_sms']) }}" wire:navigate
                       class="flex-1 h-[48px] rounded-[30px] bg-background text-text font-sans font-semibold text-[14px] inline-flex items-center justify-center gap-2 hover:bg-muted/15 transition">
                        <i class="ri-arrow-right-up-line text-[17px]"></i> Withdraw <x-money :usd="$balance" />
                    </a>
                @endif
            </div>
        </div>

        @include('livewire.dashboard.cloud.partials.bonuses')

        {{-- Upgrades are bought as a token from a vendor, so send them to the marketplace. --}}
        <div class="{{ $card }} p-5 lg:p-6 flex flex-col gap-4">
            <div class="flex items-center gap-2.5">
                <span class="size-11 rounded-[14px] bg-secondary/30 flex items-center justify-center text-primary shrink-0"><i class="ri-vip-crown-2-line text-[21px]"></i></span>
                <div class="min-w-0">
                    <p class="font-heading text-text text-[16px]">Upgrade to the Royal eSIM</p>
                    <p class="font-sans text-muted text-[12px] mt-0.5">Everything you have now, plus the calling side of Cloud.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                @foreach ([
                    ['ri-phone-line', 'Sponsored calls', 'Call a sponsor and earn its per-call rate.'],
                    ['ri-contacts-book-3-line', 'Sponsor contacts', 'Save sponsors and call each one daily.'],
                    ['ri-gift-line', 'Promoter bonuses', 'Share in payouts from sponsors you saved.'],
                    ['ri-database-2-line', 'Cloud Storage', 'Add more slots with storage plans.'],
                ] as [$icon, $title, $copy])
                    <div class="rounded-[16px] bg-background px-4 py-3.5">
                        <i class="{{ $icon }} text-primary text-[18px]"></i>
                        <p class="font-heading text-text text-[13.5px] mt-1.5">{{ $title }}</p>
                        <p class="font-sans text-muted text-[11.5px] mt-1 leading-relaxed">{{ $copy }}</p>
                    </div>
                @endforeach
            </div>

            <p class="font-sans text-muted text-[12px] leading-relaxed">Buy an upgrade token from a Nextel vendor, then redeem it from your dashboard. Your messages, history and balance stay exactly as they are.</p>

            <div class="flex flex-col sm:flex-row gap-2.5">
                <a href="{{ route('agents') }}" class="flex-1 h-[46px] rounded-[30px] bg-primary text-surface font-sans font-semibold text-[14px] inline-flex items-center justify-center gap-2 hover:opacity-95 transition">
                    <i class="ri-store-2-line text-[17px]"></i> Find a vendor
                </a>
                <button type="button" x-data @click="Livewire.dispatch('open-activation')" class="flex-1 h-[46px] rounded-[30px] bg-background text-text font-sans font-semibold text-[14px] inline-flex items-center justify-center gap-2 hover:bg-muted/15 transition">
                    <i class="ri-key-2-line text-[17px]"></i> I have a token
                </button>
            </div>
        </div>
    </div>

    <div class="lg:col-span-1 flex flex-col gap-[22px]">
        <div class="{{ $card }} p-5 flex flex-col gap-4">
            <div>
                <p class="{{ $eyebrow }}">N-Cloud SMS</p>
                <p class="font-heading font-semibold text-text text-[24px] leading-none mt-2"><x-money :usd="$balance" /></p>
                <p class="font-sans text-muted text-[12px] mt-2">Earned from sponsored messages on {{ $packageName ?? 'your plan' }}.</p>
            </div>
            <a href="{{ route('withdraw', ['wallet' => 'cloud_sms']) }}" 
               class="h-[46px] rounded-[30px] bg-background text-text font-sans font-semibold text-[14px] inline-flex items-center justify-center gap-2 hover:bg-muted/15 transition">
                <i class="ri-bank-card-line text-[17px]"></i> Withdraw
            </a>
        </div>

        <a href="{{ route('shareholder') }}" wire:navigate class="{{ $card }} flex items-center gap-3 px-4 py-3.5 hover:bg-background/60 transition">
            <span class="size-11 rounded-[14px] bg-secondary/25 flex items-center justify-center text-primary shrink-0"><i class="ri-pie-chart-2-line text-[21px]"></i></span>
            <div class="min-w-0 flex-1">
                <p class="font-heading font-medium text-text text-[14.5px]">Shareholder Portal</p>
                <p class="font-sans text-muted text-[12px] truncate">Claim your share of the call-revenue pool</p>
            </div>
            <i class="ri-arrow-right-s-line text-muted text-[20px] shrink-0"></i>
        </a>

        <a href="{{ route('special-coupon') }}" wire:navigate class="{{ $card }} flex items-center gap-3 px-4 py-3.5 hover:bg-background/60 transition">
			<span class="size-11 rounded-[14px] bg-success/10 flex items-center justify-center text-success shrink-0"><i class="ri-gift-2-line text-[21px]"></i></span>
			<div class="min-w-0 flex-1">
				<p class="font-heading font-medium text-text text-[14.5px]">Revenue Authentication</p>
				<p class="font-sans text-muted text-[12px] truncate">Activate additional storage</p>
			</div>
			<i class="ri-arrow-right-s-line text-muted text-[20px] shrink-0"></i>
		</a>
		
    </div>
</div>
