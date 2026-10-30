<div>
    @if ($open)
        <div data-lock-scroll class="fixed inset-0 z-[90] flex items-end justify-center sm:items-center sm:p-4"
             x-data="{ shown: false }" x-init="$nextTick(() => shown = true)">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" x-show="shown" x-transition.opacity @click="$wire.close()"></div>

            <div class="relative w-full sm:max-w-[400px] bg-background rounded-t-[26px] sm:rounded-[26px] max-h-[92vh] overflow-y-auto px-6 pt-6 pb-8 sm:pb-7 flex flex-col items-center text-center gap-3"
                 x-show="shown"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">

                <button type="button" wire:click="close" class="absolute right-5 top-5 size-9 rounded-full bg-surface shadow-sm flex items-center justify-center text-text" aria-label="Close">
                    <i class="ri-close-line text-[20px]"></i>
                </button>

                @if ($done)
                    <span class="size-16 rounded-full bg-success/15 flex items-center justify-center text-success mt-4"><i class="ri-rocket-2-fill text-[32px]"></i></span>
                    <p class="font-heading font-medium text-text text-[20px]">{{ $isUpgrade ? 'eSIM Upgraded' : 'eSIM Activated' }}</p>
                    {{-- Admin-authored per package; falls back to the generic line when left blank. Personalised with the user's username. --}}
                    @php
                        $successMsg = filled($activatedPackage?->success_message)
                            ? $activatedPackage->success_message
                            : ($isUpgrade ? 'Your new package is active with extra Call IDs and higher earnings. Enjoy the upgrade.' : 'Your package is now active and your Call IDs are assigned. Start earning from calls.');
                        // Drop a leading "Congratulations!" so the personalised greeting below isn't duplicated.
                        $successBody = preg_replace('/^\s*congratulations!?[\s,]*/i', '', $successMsg);
                    @endphp
                    <p class="font-sans text-muted text-[14px] max-w-[300px]">Congratulations <strong class="text-text">{{ auth()->user()->username }}</strong>! {{ $successBody }}</p>
                    <a href="{{ route('dashboard') }}" class="mt-3 w-full h-[50px] rounded-[30px] bg-secondary text-primary font-sans font-semibold text-[15px] flex items-center justify-center gap-2 hover:opacity-95 transition">
                        Continue <i class="ri-arrow-right-line"></i>
                    </a>
                @else
                    <p class="font-heading font-medium text-text text-[20px] mt-1 px-8">{{ $isUpgrade ? 'Upgrade your package' : 'Activate an eSIM' }}</p>

                    @if ($isUpgrade)
                        <span class="inline-flex items-center gap-1.5 rounded-[35px] bg-primary/10 text-primary px-3 py-1.5 font-sans text-[12px] mt-1">
                            <span class="size-1.5 rounded-full bg-primary"></span> Current plan: {{ $currentPackage->name }}
                        </span>
                        <p class="font-sans text-muted text-[13px] leading-relaxed max-w-[300px]">Move to a higher package to unlock more Call IDs, more daily minutes and more claimable call types.</p>
                    @else
                        <img src="{{ asset('images/activate-lock.png') }}" alt="" class="w-[180px] h-auto my-0.5 select-none pointer-events-none">
                        <p class="font-heading font-semibold text-danger text-[17px]">Your eSIM is not activated</p>
                        <p class="font-sans text-muted text-[13px] leading-relaxed max-w-[300px]">Your dashboard remains available. Activate an eSIM to enable calling, SMS, and eSIM-specific features.</p>
                    @endif

                    <div class="w-full flex flex-col gap-1.5 mt-1 text-left">
                        <label class="font-sans text-[13px] text-text font-medium">{{ $isUpgrade ? 'Upgrade to' : 'Select Package' }}</label>
                        {{-- Deferred @entangle for instant highlight; activate() re-validates packageId server-side. --}}
                        @php $priceFor = fn ($p) => \App\Support\RegionPricing::customer($p, $region); @endphp
                        <div class="grid grid-cols-2 gap-2.5" x-data="{ pid: @entangle('packageId') }">
                            @foreach ($packages as $p)
                                @php
                                    $isCurrent = $isUpgrade && $currentPackage->id === $p->id;
                                    $tooLow = $isUpgrade && (int) $p->tier_level < (int) $currentPackage->tier_level;
                                    $disabled = $isCurrent || $tooLow;
                                @endphp
                                <button type="button" @disabled($disabled)
                                        @if (! $disabled) @click="pid = '{{ $p->id }}'" @endif
                                        class="relative rounded-[14px] border p-3 text-left transition {{ $disabled ? 'border-muted/15 bg-muted/5 opacity-60 cursor-not-allowed' : '' }}"
                                        @unless ($disabled) :class="pid === '{{ $p->id }}' ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-muted/20 bg-surface hover:border-muted/40'" @endunless>
                                    @if ($isCurrent)
                                        <span class="absolute right-2 top-2 rounded-full bg-primary/15 text-primary px-1.5 py-0.5 font-sans text-[9px]">Current</span>
                                    @elseif ($p->is_featured)
                                        <span class="absolute right-2 top-2 rounded-full bg-secondary/40 text-primary px-1.5 py-0.5 font-sans text-[9px]">Popular</span>
                                    @endif
                                    <p class="font-heading font-medium text-text text-[13px] leading-tight">{{ $p->name }}</p>
                                    <x-money :usd="$priceFor($p)" class="font-heading font-semibold text-primary text-[15px] leading-tight mt-0.5 block" />
                                    <p class="font-sans text-muted text-[10px] mt-0.5">{{ rtrim(rtrim(number_format((float) $p->daily_call_minutes, 1), '0'), '.') }} mins · {{ $p->call_id_limit }} IDs</p>
                                </button>
                            @endforeach

                            {{-- Pay for the selected package directly (activate or upgrade) — the
                                 hidden field tracks the Alpine selection; the route re-validates. --}}
                            @if ($directPay ?? false)
                                <form method="POST" action="{{ route('plans.buy.start') }}" class="col-span-2 mt-0.5">
                                    @csrf
                                    <input type="hidden" name="package_id" :value="pid">
                                    <input type="hidden" name="mode" value="{{ $isUpgrade ? 'upgrade' : 'activate' }}">
                                    <button type="submit" :disabled="! pid"
                                            class="w-full h-[48px] rounded-[30px] bg-primary text-surface font-sans font-semibold text-[14px] inline-flex items-center justify-center gap-2 hover:opacity-95 transition disabled:opacity-40 disabled:cursor-not-allowed">
                                        <i class="ri-secure-payment-line text-[17px]"></i> Pay directly
                                    </button>
                                </form>
                            @endif
                        </div>
                        @error('packageId')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div class="w-full flex flex-col gap-1.5 mt-1 text-left">
                        <label class="font-sans text-[13px] text-text font-medium">Or redeem a {{ $isUpgrade ? 'Upgrade' : 'eSIM Activation' }} Code</label>
                        <input type="text" wire:model="token" placeholder="Enter your eSIM code"
                               class="w-full h-[48px] rounded-[14px] bg-surface border border-muted/20 px-4 outline-none font-sans text-[14px] text-text uppercase placeholder:text-muted placeholder:normal-case focus:border-primary focus:ring-1 focus:ring-primary">
                        @error('token')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>

                    <button type="button" wire:click="activate" wire:loading.attr="disabled"
                            class="w-full h-[52px] rounded-[30px] bg-secondary text-primary font-sans font-semibold text-[15px] flex items-center justify-center gap-2 hover:opacity-95 transition disabled:opacity-60 mt-2">
                        <span wire:loading.remove wire:target="activate">{{ $isUpgrade ? 'Upgrade' : 'Activate' }}</span>
                        <span wire:loading wire:target="activate" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> {{ $isUpgrade ? 'Upgrading' : 'Activating' }}…</span>
                    </button>

                    <a href="{{ route('agents') }}" wire:navigate
                       class="w-full h-[46px] rounded-[30px] border border-primary/25 text-primary font-sans font-semibold text-[14px] flex items-center justify-center gap-2 hover:bg-primary/5 transition mt-1">
                        <i class="ri-store-2-line text-[16px]"></i> Purchase eSIM Code
                    </a>
                @endif
            </div>
        </div>
    @endif
</div>
