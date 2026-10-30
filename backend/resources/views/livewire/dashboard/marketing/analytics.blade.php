<div class="min-h-screen bg-background">
    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-12 flex flex-col gap-[22px]">

        <x-app.app-bar :title="'My Website'" :subtitle="'Your call activity and earnings made easy on your personalised website'" />

        @if (! $websiteActive)
            <div class="flex flex-col items-center text-center gap-5 py-10 lg:py-16 max-w-[440px] mx-auto w-full">
                <span class="size-20 rounded-full bg-primary/10 flex items-center justify-center text-primary"><i class="ri-global-line text-[40px]"></i></span>
                <div class="flex flex-col gap-2">
                    <h2 class="font-heading font-semibold text-text text-[22px]">{{ $expiredTitle }}</h2>
                    <p class="font-sans text-muted text-[14px] leading-relaxed whitespace-pre-line">{{ $expiredMessage }}</p>
                    @if ($expiredAt)<p class="font-sans text-muted text-[12px]">Expired {{ $expiredAt->format('d M, Y') }}.</p>@endif
                </div>
                @if ($renewalMode === 'code')
                    <div class="w-full flex flex-col gap-2.5">
                        <input type="text" wire:model="renewalCode" placeholder="Enter renewal code"
                               class="h-[52px] rounded-[30px] bg-surface border border-muted/15 px-5 text-center font-sans text-[15px] tracking-wider text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('renewalCode') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        <button type="button" wire:click="renewWithCode" wire:loading.attr="disabled" wire:target="renewWithCode"
                                class="h-[52px] rounded-[30px] bg-primary text-surface font-sans font-semibold text-[15px] flex items-center justify-center gap-2 hover:opacity-95 transition disabled:opacity-60">
                            <span wire:loading.remove wire:target="renewWithCode"><i class="ri-refresh-line text-[18px]"></i> Renew · {{ $renewalDays }} days</span>
                            <span wire:loading wire:target="renewWithCode" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Renewing…</span>
                        </button>
                        <p class="font-sans text-muted text-[11px]">Buy a renewal code from a distributor on the <a href="{{ route('agents') }}" wire:navigate class="text-primary underline">Distributors</a> page, then enter it here.</p>
                    </div>
                @else
                    <form method="POST" action="{{ route('website.renew.start') }}" class="w-full flex flex-col gap-2.5">
                        @csrf
                        <button type="submit" class="h-[52px] rounded-[30px] bg-primary text-surface font-sans font-semibold text-[15px] flex items-center justify-center gap-2 hover:opacity-95 transition">
                            <i class="ri-refresh-line text-[18px]"></i> Renew for <x-money :usd="$renewalPrice" /> · {{ $renewalDays }} days
                        </button>
                        <p class="font-sans text-muted text-[11px]">Secure payment{{ $renewalProvider ? ' via '.$renewalProvider : '' }} — you'll return here once it's confirmed.</p>
                    </form>
                @endif
            </div>
        @else
        <div class="flex flex-col gap-[22px] lg:grid lg:grid-cols-3 lg:gap-6 lg:items-start">

            <div class="lg:col-span-2 flex flex-col gap-[22px]">

                <div class="relative overflow-hidden rounded-[22px] p-5 lg:p-6 flex flex-col gap-4 text-white" style="background: radial-gradient(120% 150% at 90% 8%, #2f7468 0%, #18443E 56%); background-color: #18443E;">
                    <div class="flex items-start justify-between gap-3">
                        <span class="inline-flex items-center gap-1.5 bg-white/12 rounded-[35px] px-2.5 py-[5px]">
                            <span class="size-1.5 rounded-full {{ $hasPackage ? 'bg-success' : 'bg-[#d79937]' }} animate-pulse"></span>
                            <span class="font-sans text-white/85 text-[12px]">{{ $hasPackage ? 'Network Active' : 'No active plan' }}</span>
                        </span>
                        <i class="ri-bar-chart-grouped-line text-white/30 text-[26px]"></i>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <p class="font-heading font-medium text-white text-[22px] leading-tight">{{ $greeting }}, {{ $name }}</p>
                        <p class="font-sans text-white/70 text-[14px] leading-relaxed max-w-[460px]">Here's how your network is performing — your call activity and earnings at a glance.</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2.5 lg:grid-cols-4 lg:gap-4">
                    @foreach ($stats as $stat)
                        <div class="bg-surface rounded-[16px] p-3.5 lg:p-4 flex flex-col gap-2.5 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                            <div class="flex items-start justify-between">
                                <span class="size-[30px] rounded-full bg-primary/10 flex items-center justify-center text-primary shrink-0"><i class="{{ $stat['icon'] }} text-[16px]"></i></span>
                                @if ($stat['trend'])
                                    <span class="inline-flex items-center gap-0.5 bg-primary/10 rounded-full px-[6px] py-0.5">
                                        <i class="ri-arrow-up-line text-primary text-[8px]"></i>
                                        <span class="font-sans text-primary text-[10px]">{{ $stat['trend'] }}</span>
                                    </span>
                                @endif
                            </div>
                            <div class="flex flex-col gap-0.5 min-w-0">
                                <p class="font-heading font-medium text-text text-[17px] leading-none">
                                    @if (! is_null($stat['usd'] ?? null))
                                        <x-money :usd="$stat['usd']" :decimals="0" />
                                    @else
                                        {{ $stat['value'] }}
                                    @endif
                                </p>
                                <p class="font-sans text-muted text-[11px] leading-tight">{{ $stat['label'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                @php $ticks = collect(range(5, 0))->map(fn ($t) => (int) round($chartMax * $t / 5)); @endphp
                <div class="bg-surface rounded-[20px] p-5 lg:p-6 flex flex-col gap-5 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                    <div class="flex items-center justify-between">
                        <div class="flex flex-col">
                            <p class="font-heading font-medium text-text text-[15px]">Call Activity</p>
                            <p class="font-sans text-muted text-[12px]">Minutes per day · last 7 days</p>
                        </div>
                        <span class="inline-flex items-center gap-1.5 bg-background rounded-full px-3 py-1.5 font-sans text-muted text-[12px]"><i class="ri-phone-line text-[13px] text-primary"></i> {{ number_format($totalCalls) }} calls</span>
                    </div>
                    <div class="flex">
                        <div class="flex flex-col justify-between text-right pr-2.5 pb-[37px]" style="height: 185px;">
                            @foreach ($ticks as $tick)
                                <span class="font-sans text-muted/70 text-[11px] leading-none">{{ $tick }}</span>
                            @endforeach
                        </div>
                        <div class="relative flex-1">
                            <div class="absolute inset-x-0 top-0 flex flex-col justify-between" style="height: 150px;">
                                @foreach (range(0, 5) as $line)
                                    <div class="h-px w-full bg-muted/10"></div>
                                @endforeach
                            </div>
                            <div class="relative flex items-end justify-between gap-1.5" style="height: 150px;">
                                @foreach ($chart as $bar)
                                    <div class="group relative flex-1 flex items-end justify-center h-full">
                                        <div class="w-full max-w-[30px] rounded-t-[8px] rounded-b-[3px] bg-gradient-to-t from-primary to-[#2f7468] transition-all hover:from-[#2f7468] hover:to-primary"
                                             style="height: {{ max(3, $bar['pct']) }}%;"
                                             title="{{ $bar['label'] }}: {{ rtrim(rtrim(number_format($bar['minutes'], 1), '0'), '.') }} mins"></div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="flex items-center justify-between gap-1.5 mt-3">
                                @foreach ($chart as $bar)
                                    <span class="flex-1 text-center font-sans text-muted text-[11px]">{{ $bar['label'] }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-[22px]">

                <div class="bg-surface rounded-[20px] p-5 flex flex-col gap-4 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                    <div class="flex flex-col gap-1">
                        <p class="font-sans text-muted text-[13px]">Wallet Balance</p>
                        <x-money :usd="$walletBalance" class="font-heading font-semibold text-text text-[26px] leading-none" />
                        <p class="font-sans text-muted text-[12px]">Available across your wallets</p>
                    </div>
                    <a href="{{ route('withdraw') }}?wallet=shareholder" wire:navigate class="h-[44px] rounded-[30px] bg-primary text-surface font-sans text-[14px] flex items-center justify-center gap-2 hover:opacity-95 transition">
                        <i class="ri-bank-card-line text-[16px]"></i> Withdraw
                    </a>
                </div>

                {{-- Only once the user has a plan or residual balance. --}}
                @if ($hasCloud)
                    <div class="relative overflow-hidden rounded-[20px] p-5 text-white shadow-[0_14px_34px_-12px_rgba(24,68,62,0.5)]"
                         style="background: radial-gradient(125% 130% at 88% 12%, #2f7468 0%, #18443E 58%); background-color:#18443E;">
                        <span class="absolute -right-8 -top-10 size-32 rounded-full bg-white/[0.06]"></span>
                        <div class="relative flex flex-col gap-1">
                            <p class="font-sans text-white/60 text-[13px] inline-flex items-center gap-1.5"><i class="ri-cloud-line"></i> N-Cloud Earnings</p>
                            <x-money :usd="$cloudBalance" class="font-heading font-semibold text-[26px] leading-none mt-1" />
                            {{-- Blade will not parse a directive glued to a word char ("today@else"), so the branch
                                 wraps the whole line rather than sitting mid-sentence. --}}
                            @if ($cloudTodayEarned > 0)
                                <p class="font-sans text-white/70 text-[12px]"><span class="text-secondary">+<x-money :usd="$cloudTodayEarned" /></span> earned today</p>
                            @else
                                <p class="font-sans text-white/70 text-[12px]">No earnings yet today</p>
                            @endif
                        </div>
                        <div class="relative mt-3 flex flex-wrap items-center gap-1.5">
                            @if ($cloudCanCall)
                                <span class="inline-flex items-center gap-1 rounded-full bg-white/12 px-2.5 py-1 font-sans text-white/85 text-[11px]"><i class="ri-phone-line text-[12px]"></i> Calls</span>
                            @endif
                            <span class="inline-flex items-center gap-1 rounded-full bg-white/12 px-2.5 py-1 font-sans text-white/85 text-[11px]"><i class="ri-message-3-line text-[12px]"></i> Messages @if ($cloudUnread > 0)<span class="ml-0.5 rounded-full bg-secondary text-primary px-1.5 font-semibold">{{ $cloudUnread }}</span>@endif</span>
                            @unless ($cloudCanCall)
                                <span class="inline-flex items-center gap-1 rounded-full bg-secondary/25 px-2.5 py-1 font-sans text-secondary text-[11px]"><i class="ri-vip-crown-2-line text-[12px]"></i> Upgrade for calls</span>
                            @endunless
                        </div>

                        <a href="{{ route('cloud') }}" wire:navigate class="relative mt-4 h-[44px] rounded-[30px] bg-white/15 hover:bg-white/25 text-white font-sans text-[14px] flex items-center justify-center gap-2 transition">
                            <i class="ri-cloud-line text-[16px]"></i> Open Nextel Cloud
                        </a>
                    </div>
                @endif

                <div class="grid grid-cols-2 gap-2.5">
                    <a href="{{ route('plans') }}" wire:navigate class="bg-surface rounded-[16px] p-4 flex flex-col gap-2 shadow-[0_4px_9px_rgba(194,194,194,0.07)] hover:shadow-[0_6px_16px_rgba(194,194,194,0.2)] transition-shadow">
                        <span class="size-9 rounded-full bg-primary/10 flex items-center justify-center text-primary"><i class="ri-add-circle-line text-[18px]"></i></span>
                        <span class="font-sans text-text text-[13px]">Buy shares</span>
                    </a>
                    <a href="{{ route('rewards') }}" wire:navigate class="bg-surface rounded-[16px] p-4 flex flex-col gap-2 shadow-[0_4px_9px_rgba(194,194,194,0.07)] hover:shadow-[0_6px_16px_rgba(194,194,194,0.2)] transition-shadow">
                        <span class="size-9 rounded-full bg-secondary/40 flex items-center justify-center text-primary"><i class="ri-gift-line text-[18px]"></i></span>
                        <span class="font-sans text-text text-[13px]">Rewards</span>
                    </a>
                </div>

                {{-- Share & earn happens right here; the Ads page is the earnings breakdown. --}}
                @if ($ads->isNotEmpty())
                    @php $fmt = fn ($n) => $n >= 1000 ? rtrim(rtrim(number_format($n / 1000, 1), '0'), '.').'k' : number_format($n); @endphp
                    <div class="bg-surface rounded-[18px] p-4 flex flex-col gap-3 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                        <div class="flex items-center justify-between">
                            <span class="font-heading font-medium text-text text-[13px] tracking-wide">SPONSORED</span>
                            <span class="inline-flex items-center bg-secondary/30 rounded-full px-2 py-0.5 font-sans text-primary text-[10px]">On your site</span>
                        </div>

                        @foreach ($ads as $ad)
                            @php $earned = in_array($ad->id, $earnedAdIds, true); @endphp
                            <div wire:key="ad-{{ $ad->id }}" class="flex items-center justify-between gap-3 {{ ! $loop->last ? 'border-b border-muted/10 pb-3' : '' }}">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span class="size-9 rounded-[10px] bg-primary/10 flex items-center justify-center text-primary shrink-0">
                                        @if ($ad->image_url)
                                            <img src="{{ $ad->image_url }}" alt="" class="size-full object-cover rounded-[10px]">
                                        @else
                                            <i class="ri-advertisement-line text-[17px]"></i>
                                        @endif
                                    </span>
                                    <div class="min-w-0 flex flex-col">
                                        <p class="font-sans font-semibold text-text text-[13px] leading-tight truncate">{{ $ad->name }}</p>
                                        <p class="font-sans text-muted text-[11px] truncate">{{ $ad->advertiser }} · <i class="ri-eye-line text-[11px]"></i> {{ $fmt($ad->impressions) }}</p>
                                        @if ($ad->description)
                                            <p class="font-sans text-text/70 text-[11.5px] leading-snug mt-0.5 line-clamp-2">{{ $ad->description }}</p>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex flex-col items-end gap-1.5 shrink-0">
                                    <span class="font-heading font-semibold text-primary text-[13px] leading-none">+<x-money :usd="$ad->reward" :decimals="'auto'" /></span>
                                    {{-- Opens the native share sheet (WhatsApp, Facebook…) and credits on return. --}}
                                    <button type="button" x-data
                                            @click="
                                                const url = @js($ad->target_url ?: url('/'));
                                                const payload = { title: @js($ad->name), text: @js($ad->description ?: 'Check out '.$ad->name.' on Nextel'), url };
                                                const earn = () => $wire.share(@js($ad->id));
                                                // Credit unless the user deliberately dismissed the share sheet —
                                                // a clipboard rejection must never cost them the reward.
                                                if (navigator.share) {
                                                    navigator.share(payload).then(earn).catch((e) => { if (!e || e.name !== 'AbortError') nextelCopy(url).finally(earn); });
                                                } else {
                                                    nextelCopy(url).finally(earn);
                                                }
                                            "
                                            class="inline-flex items-center gap-1 rounded-[30px] px-3.5 py-1.5 font-sans text-[11px] transition {{ $earned ? 'bg-background text-muted' : 'bg-primary text-surface hover:opacity-95' }}">
                                        <i class="{{ $earned ? 'ri-check-line' : 'ri-share-forward-line' }} text-[12px]"></i> {{ $earned ? 'Shared' : 'Share' }}
                                    </button>
                                </div>
                            </div>
                        @endforeach

                        {{-- Ads system being phased out — link hidden for now (revert by uncommenting):
                        <a href="{{ route('ads') }}" wire:navigate class="font-sans text-primary text-[12px] inline-flex items-center gap-1.5 hover:underline">
                            <i class="ri-line-chart-line text-[13px]"></i> View your ads revenue
                        </a>
                        --}}
                    </div>
                @endif
            </div>
        </div>
        @endif
    </div>

    <x-app.bottom-nav />
</div>
