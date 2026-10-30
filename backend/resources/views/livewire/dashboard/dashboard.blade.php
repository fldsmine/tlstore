<div class="min-h-screen bg-background">
    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-12 flex flex-col gap-6">

        <x-app.app-bar :title="'Hi, '.($user->first_name ?: $user->username)" :badge="$user->package?->name ?? 'No active plan'" />

        <x-app.push-button />

        <x-app.announcements />

        {{-- Promotional popups: queued at random, shown one at a time on load. --}}
        <livewire:dashboard.popup />

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            <div class="lg:col-span-7 xl:col-span-8 flex flex-col gap-6">

                <x-app.wallet-switcher :balances="$user->walletBalances()" size="38px"
                    :currency="true">
                    <p x-show="balances[i].today" x-cloak class="font-sans text-[15px] text-muted mt-2">
                        <span class="text-success">+<span x-text="balances[i].today"></span></span> today
                    </p>
                </x-app.wallet-switcher>

                @php
                    $quickRows = [
                        [
                            ['Activate eSIM', 'ri-sim-card-2-line', route('agents'), false],
                            ['Buy Airtime', 'ri-smartphone-line', route('vas'), false],
                            ['Telegram', 'ri-telegram-2-line', config('settings.contact.social.telegram') ?: '#', true],
                            ['Calls & SMS', 'ri-cloud-line', route('cloud'), false],
                        ],
                        [
                            ['Beneficiaries', 'ri-shopping-cart-2-line', route('beneficiaries'), false],
                            ['Withdraw', 'ri-arrow-left-down-line', route('withdraw'), false],
                            ['Shares', 'ri-line-chart-line', route('shareholder'), false],
                            ['My Website', 'ri-global-line', route('my-website'), false],
                        ],
                    ];
                @endphp
                <div x-data="{ more: false }" class="flex flex-col gap-4">
                    @foreach ($quickRows as $ri => $rowActions)
                        <div @if ($ri === 1) x-show="more" x-collapse x-cloak @endif class="flex items-start justify-between max-w-[380px] lg:max-w-none lg:justify-start lg:gap-10">
                            @foreach ($rowActions as [$label, $icon, $href, $external])
                                <a href="{{ $href }}"
                                   @if ($external) target="_blank" rel="noopener" @elseif (! str_starts_with($href, '#')) wire:navigate @endif
                                   class="flex flex-col items-center gap-2.5 w-[70px]">
                                    <span class="size-[50px] rounded-full border border-muted/25 bg-surface flex items-center justify-center text-primary"><i class="{{ $icon }} text-[20px]"></i></span>
                                    <span class="font-sans text-text text-[12px] text-center leading-tight">{{ $label }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                    <button type="button" @click="more = ! more" class="self-start inline-flex items-center gap-1 text-primary font-sans text-[13px] font-medium">
                        <span x-text="more ? 'View less' : 'View more'"></span>
                        <i class="ri-arrow-down-s-line text-[16px] transition-transform" :class="more && 'rotate-180'"></i>
                    </button>
                </div>

                @php
                    $segments = $performance['segments'];
                    $stops = [];
                    $cursor = 0;
                    foreach ($segments as $seg) {
                        $end = $cursor + $seg['pct'];
                        $stops[] = $seg['color'].' '.$cursor.'% '.$end.'%';
                        $cursor = $end;
                    }
                    $gradient = 'conic-gradient('.(empty($stops) ? '#e6e3da 0% 100%' : implode(', ', $stops)).')';

                    $legend = [
                        ['#18443e', number_format($performance['totalDuration']), 'min'],
                        ['#ce3c3c', number_format($performance['activeNumbers']), 'Numbers'],
                        ['#8c8c8c', number_format($performance['totalCalls']), 'Calls'],
                        ['#10b981', money($performance['avgPerMin']).'/', 'min'],
                    ];
                @endphp
                <div class="flex flex-col gap-[15px]">
                    <div class="flex items-center justify-between">
                        <p class="font-heading font-medium text-text text-[18px]">Call Performance</p>
                        <a href="{{ route('my-website') }}" wire:navigate class="font-sans text-[13px] text-primary">View all</a>
                    </div>
                    <div x-data="{ tip: false }" class="bg-surface rounded-[20px] p-5 lg:p-6 flex flex-col items-center gap-5 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                        <div class="relative size-[150px] mt-1" @mouseenter="tip = true" @mouseleave="tip = false">
                            <button type="button" @click="tip = !tip" class="size-full rounded-full flex items-center justify-center cursor-pointer" style="background: {{ $gradient }};" aria-label="Call performance breakdown">
                                <span class="size-[90px] rounded-full bg-surface flex flex-col items-center justify-center text-muted gap-0.5">
                                    <i class="ri-information-line text-[16px]"></i>
                                    <span class="font-sans text-[9px]">Tap</span>
                                </span>
                            </button>

                            <div x-show="tip" x-cloak x-transition.opacity
                                 class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 z-20 w-[180px] bg-text text-white rounded-[14px] px-3 py-2.5 shadow-[0_12px_30px_rgba(0,0,0,0.35)]">
                                <p class="font-sans text-[10px] text-white/55 mb-1.5">Call duration by type</p>
                                @forelse ($segments as $seg)
                                    <div class="flex items-center justify-between gap-2 py-[3px]">
                                        <span class="flex items-center gap-1.5 min-w-0">
                                            <span class="size-2 rounded-full shrink-0" style="background: {{ $seg['color'] }}"></span>
                                            <span class="font-sans text-[11px] truncate">{{ $seg['name'] }}</span>
                                        </span>
                                        <span class="font-sans text-[11px] font-semibold shrink-0">{{ rtrim(rtrim(number_format($seg['duration'], 1), '0'), '.') }}m · {{ $seg['pct'] }}%</span>
                                    </div>
                                @empty
                                    <p class="font-sans text-[11px] text-white/70">No calls yet.</p>
                                @endforelse
                            </div>
                        </div>

                        <div class="w-full grid grid-cols-4 gap-2">
                            @foreach ($legend as [$c, $v, $u2])
                                <div class="flex flex-col gap-1">
                                    <span class="w-5 h-[5px] rounded-full" style="background: {{ $c }}"></span>
                                    <p class="font-heading font-medium text-[16px] leading-none" style="color: {{ $c }}">{{ $v }}</p>
                                    <p class="font-sans text-[10px] text-muted leading-none">{{ $u2 }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-5 xl:col-span-4 flex flex-col gap-6">

                <div class="rounded-[20px] p-5 flex flex-col gap-3 text-white" style="background: linear-gradient(135deg, #18443E 0%, #149983 100%);">
					<div class="flex items-center justify-between">
						<div class="flex items-center gap-2.5">
							<span class="size-10 rounded-full bg-white/15 flex items-center justify-center text-secondary"><i class="ri-rocket-2-line text-[20px]"></i></span>
							<p class="font-heading font-medium text-white text-[18px]">Do you loan</p>
						</div>
					</div>
                    
                    <div class="flex flex-col gap-0.5">
                        <p class="font-sans text-white/70 text-[14px]">Getting a loan is now easier than ever with Nextel! Access the loan application feature with just one click.</p>
                    </div>

					<a href="{{ route('loan') }}" wire:navigate class="self-start mt-1 inline-flex items-center gap-2 bg-secondary text-primary rounded-[30px] px-4 py-2 font-sans font-semibold text-[14px] hover:opacity-95 transition">
						Get loan now <i class="ri-arrow-right-line"></i>
					</a>
                </div>

                @if ($minutes['hasSharePlan'])
                @php $usedPct = $minutes['usedPct']; @endphp
                <div class="bg-surface rounded-[20px] p-5 flex flex-col gap-4 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                    <div class="relative h-[26px]">
                        <span class="absolute -translate-x-1/2 flex flex-col items-center" style="left: {{ max(8, min($usedPct / 2, 40)) }}%">
                            <span class="px-2.5 py-1 rounded-full bg-secondary text-primary font-sans text-[11px] leading-none">{{ rtrim(rtrim(number_format($minutes['used'], 1), '0'), '.') }}mins</span>
                            <span class="w-0 h-0 border-x-4 border-x-transparent border-t-[5px] border-t-secondary"></span>
                        </span>
                        <span class="absolute -translate-x-1/2 flex flex-col items-center" style="left: {{ min(max($usedPct + (100 - $usedPct) / 2, 60), 92) }}%">
                            <span class="px-2.5 py-1 rounded-full bg-primary text-white font-sans text-[11px] leading-none">{{ rtrim(rtrim(number_format($minutes['remaining'], 1), '0'), '.') }}mins</span>
                            <span class="w-0 h-0 border-x-4 border-x-transparent border-t-[5px] border-t-primary"></span>
                        </span>
                    </div>
                    <div class="h-[18px] rounded-full bg-primary overflow-hidden">
                        <div class="h-full bg-secondary" style="width: {{ $usedPct }}%"></div>
                    </div>
                    <p class="font-heading font-medium text-text text-[15px]">{{ rtrim(rtrim(number_format($minutes['allocated'], 1), '0'), '.') }} share minutes this term</p>
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-4">
                            <span class="flex items-center gap-1.5 font-sans text-[10px] text-muted"><span class="size-3 rounded-[2px] bg-secondary"></span> Minutes Used</span>
                            <span class="flex items-center gap-1.5 font-sans text-[10px] text-muted"><span class="size-3 rounded-[2px] bg-primary"></span> Minutes Remaining</span>
                        </div>
                        @if (config('settings.minutes.enabled'))
                            <a href="{{ route('plans') }}" wire:navigate class="shrink-0 text-[10px] font-semibold text-primary border border-primary rounded-full px-2.5 py-1">Add shares</a>
                        @endif
                    </div>
                    <p class="font-sans hidden text-muted text-[11px]">Daily earning cap: <x-money :usd="$minutes['dailyCapRemaining']" /> of <x-money :usd="$minutes['dailyCap']" /> remaining · resets at midnight.</p>
                    <p class="font-sans hidden text-muted text-[11px]">Term expires {{ $minutes['expiresAt']?->format('M j, Y') }}. Unused share minutes do not carry into a renewed or upgraded term.</p>
                </div>
                @elseif ($minutes['hasPackage'])
                <div class="rounded-[20px] p-5 flex flex-col gap-3 text-white" style="background: linear-gradient(135deg, #18443E 0%, #225e54 100%);">

					<div class="flex items-center justify-between">
						<div class="flex items-center gap-2.5">
							<span class="size-10 rounded-full bg-white/15 flex items-center justify-center text-secondary"><i class="ri-time-line text-[20px]"></i></span>
							<p class="font-heading font-medium text-white text-[18px]">Share term not active</p>
						</div>
					</div>
                    <div class="flex flex-col gap-0.5">
                        <p class="font-sans text-white/70 text-[14px]">Your eSIM remains active. Share claims require a configured, current share term.</p>
                    </div>
                    @if ($minutes['shareRenewalAvailable'] && $directPackagePay)
                        <form method="POST" action="{{ route('plans.buy.start') }}" class="self-start mt-1">
                            @csrf
                            <input type="hidden" name="package_id" value="{{ $user->package_id }}">
                            <input type="hidden" name="mode" value="renew">
                            <button type="submit" class="inline-flex items-center gap-2 bg-secondary text-primary rounded-[30px] px-4 py-2 font-sans font-semibold text-[14px] hover:opacity-95 transition">
                                Renew share plan <i class="ri-arrow-right-line"></i>
                            </button>
                        </form>
                    @elseif (! $minutes['shareRenewalAvailable'])
                        <p class="font-sans text-white/65 text-[12px]">The package share quota or daily earning cap has not been configured yet.</p>
                    @else
                        <p class="font-sans text-white/65 text-[12px]">Direct share-plan renewal is not available through the current payment channel right now. Contact support for help.</p>
                    @endif
                </div>
                @else
                <div class="rounded-[20px] p-5 flex flex-col gap-3 text-white" style="background: linear-gradient(135deg, #18443E 0%, #149983 100%);">
					<div class="flex items-center justify-between">
						<div class="flex items-center gap-2.5">
							<span class="size-10 rounded-full bg-white/15 flex items-center justify-center text-secondary"><i class="ri-rocket-2-line text-[20px]"></i></span>
							<p class="font-heading font-medium text-white text-[18px]">No Active Shares</p>
						</div>
					</div>

                    <div class="flex flex-col gap-0.5">
                        <p class="font-sans text-white/70 text-[14px]">Your dashboard is ready. Start earning with cloud share and its features.</p>
                    </div>

					<a href="{{ route('shareholder') }}" wire:navigate class="self-start mt-1 inline-flex items-center gap-2 bg-secondary text-primary rounded-[30px] px-4 py-2 font-sans font-semibold text-[14px] hover:opacity-95 transition">
						Buy Shares <i class="ri-arrow-right-line"></i>
					</a>
                </div>

                <div class="rounded-[20px] p-5 flex flex-col gap-3 text-white" style="background: linear-gradient(135deg, #18443E 0%, #225e54 100%);">
					<div class="flex items-center justify-between">
						<div class="flex items-center gap-2.5">
							<span class="size-10 rounded-full bg-white/15 flex items-center justify-center text-secondary"><i class="ri-rocket-2-line text-[20px]"></i></span>
							<p class="font-heading font-medium text-white text-[18px]">No eSIM package yet</p>
						</div>
					</div>

                    <div class="flex flex-col gap-0.5">
                        <p class="font-sans text-white/70 text-[14px]">Your dashboard is ready. Activate an eSIM package to access eSIM features and start an eligible share term.</p>
                    </div>
					
                    <button type="button" x-data @click="Livewire.dispatch('open-activation')" class="self-start mt-1 inline-flex items-center gap-2 bg-secondary text-primary rounded-[30px] px-4 py-2 font-sans font-semibold text-[14px] hover:opacity-95 transition">
                        Activate an eSIM <i class="ri-arrow-right-line"></i>
                    </button>
                </div>

                @endif

                @if ($minutes['hasPackage'] && $canUpgrade)
                    <button type="button" x-data @click="Livewire.dispatch('open-activation')"
                       class="w-full text-left rounded-[20px] p-4 flex items-center gap-3 text-white hover:opacity-95 transition"
                       style="background: radial-gradient(120% 140% at 88% 10%, #2f7468 0%, #18443E 58%); background-color: #18443E;">
                        <span class="size-11 rounded-full bg-white/12 flex items-center justify-center text-secondary shrink-0"><i class="ri-vip-crown-2-line text-[22px]"></i></span>
                        <div class="flex-1 min-w-0">
                            <p class="font-heading font-medium text-white text-[15px]">Upgrade your plan</p>
                            <p class="font-sans text-white/70 text-[12px] leading-snug">Move to a higher tier and earn more per call.</p>
                        </div>
                        <i class="ri-arrow-right-line text-white/80 text-[18px] shrink-0"></i>
                    </button>
                @endif

                {{-- Earnings portal card hidden to declutter the home screen (earnings still reachable from the sidebar).
                <div class="bg-surface rounded-[20px] p-5 flex flex-col gap-2.5">
                    <div class="flex flex-col">
                        <p class="font-heading font-medium text-text text-[18px]">Your Earnings Portal</p>
                        <p class="font-sans text-muted text-[15px]">Track and claim your earnings</p>
                    </div>
                    <a href="{{ route('earnings') }}" wire:navigate class="self-start inline-flex items-center gap-2 bg-primary text-surface rounded-[30px] px-4 py-2 font-sans text-[15px] hover:opacity-95 transition">
                        Claim Earnings <i class="ri-arrow-right-line"></i>
                    </a>
                </div>
                --}}

                @if ($reward['enabled'])
                    <div class="bg-surface rounded-[20px] p-5 flex flex-col gap-3 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <span class="size-9 rounded-full bg-secondary/40 flex items-center justify-center text-primary"><i class="ri-trophy-line text-[18px]"></i></span>
                                <p class="font-heading font-medium text-text text-[16px]">Target Reward</p>
                            </div>
                            <a href="{{ route('rewards') }}" wire:navigate class="font-sans text-[13px] text-primary">View</a>
                        </div>

                        <div class="flex flex-col gap-0.5">
                            <p class="font-sans text-muted text-[12px]">Earned from targets</p>
                            <x-money :usd="$reward['lifetime']" class="font-heading font-semibold text-text text-[26px] leading-none" />
                        </div>

                        @if ($reward['hasTarget'])
                            <div class="flex flex-col gap-1.5">
                                <div class="flex items-center justify-between font-sans text-[12px]">
                                    <span class="text-muted">{{ $reward['locked'] ? 'Today’s target hit' : 'Today’s target' }}</span>
                                    <span class="text-text"><x-money :usd="$reward['progress']" /> / <x-money :usd="$reward['targetAmount']" /></span>
                                </div>
                                <div class="h-2 rounded-full bg-background overflow-hidden">
                                    <div class="h-full rounded-full {{ $reward['locked'] ? 'bg-success' : 'bg-secondary' }}" style="width: {{ $reward['pct'] }}%"></div>
                                </div>
                            </div>
                        @else
                            <p class="font-sans text-muted text-[12px]">No target set today — set one to start earning.</p>
                        @endif

                        @if ($reward['unclaimed'] > 0)
                            <a href="{{ route('rewards') }}" wire:navigate class="self-start inline-flex items-center gap-1.5 bg-success text-surface rounded-[30px] px-4 py-2 font-sans text-[13px] hover:opacity-95 transition">
                                <i class="ri-gift-line"></i> Claim <x-money :usd="$reward['unclaimed']" />
                            </a>
                        @else
                            <a href="{{ route('rewards') }}" wire:navigate class="self-start inline-flex items-center gap-1.5 bg-secondary/30 hover:bg-secondary/40 transition text-primary rounded-[30px] px-4 py-2 font-sans text-[13px]">
                                {{ $reward['hasTarget'] ? 'View rewards' : 'Set today’s target' }} <i class="ri-arrow-right-line"></i>
                            </a>
                        @endif
                    </div>
                @endif

                <div class="flex flex-col gap-[15px]">
                    <div class="flex items-center justify-between">
                        <p class="font-heading font-medium text-text text-[18px]">Recent Transactions</p>
                        <a href="{{ route('transactions') }}" wire:navigate class="font-sans text-[13px] text-primary">View all</a>
                    </div>
                    @if ($recent->isEmpty())
                        <div class="bg-surface rounded-[20px] p-6 flex flex-col items-center gap-2 text-center">
                            <span class="size-12 rounded-full bg-background flex items-center justify-center text-muted"><i class="ri-pulse-line text-[22px]"></i></span>
                            <p class="font-sans text-muted text-[14px]">No activity yet — start earning from your calls.</p>
                        </div>
                    @else
                        <div class="bg-surface rounded-[20px] p-2.5 flex flex-col shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                            @foreach ($recent as $tx)
                                <x-app.transaction-row :tx="$tx" wire:click="showDetails('{{ $tx->id }}')"
                                    @class(['cursor-pointer rounded-[14px] hover:bg-background/60 transition-colors', 'border-b border-muted/10' => ! $loop->last]) />
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <x-app.bottom-nav />

    <x-app.transaction-details :tx="$selectedTx" />
</div>
