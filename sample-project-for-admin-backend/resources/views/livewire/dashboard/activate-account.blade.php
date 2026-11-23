<div class="w-full max-w-[440px] min-h-full flex flex-col items-center justify-center py-4">

    <a href="{{ route('home') }}" wire:navigate class="inline-flex items-center shrink-0 mb-2">
        <img src="{{ asset('images/public/logo-dark.png') }}" alt="Nextel Connect" class="h-8 w-auto">
    </a>

    <div class="w-full bg-surface rounded-3xl border border-muted/10 shadow-[0_10px_40px_rgba(0,0,0,0.06)] px-5 sm:px-6 py-6 flex flex-col">
        <div class="w-full flex flex-col items-center text-center gap-1.5">
        @if ($done)
            @php
                $successMsg = filled($activatedPackage?->success_message)
                    ? $activatedPackage->success_message
                    : 'Your package is now active and your Call IDs are assigned. Start earning from calls.';
                $successBody = preg_replace('/^\s*congratulations!?[\s,]*/i', '', $successMsg);

                // Benefits shown are driven by what the activated package actually grants.
                $successFeatures = [];
                if ($activatedPackage?->cloud_calls) {
                    $successFeatures[] = ['icon' => 'ri-phone-line', 'title' => 'Make & Receive Calls', 'desc' => 'Stay connected with crystal-clear calls.'];
                }
                if ($activatedPackage?->cloud_sms) {
                    $successFeatures[] = ['icon' => 'ri-chat-3-line', 'title' => 'Send & Receive Messages', 'desc' => 'Text, share, and stay in touch.'];
                }
                $successFeatures[] = ['icon' => 'ri-hand-coin-line', 'title' => 'Start Earning', 'desc' => 'Every call counts. Every connection rewards you.'];
            @endphp

            <div class="w-full flex flex-col items-center gap-4 py-1">
                {{-- eSIM chip in a soft brand halo, with a success check badge --}}
                <div class="relative">
                    <div class="size-[128px] rounded-full bg-gradient-to-br from-secondary/40 via-primary/5 to-primary/10 flex items-center justify-center">
                        <div class="size-[74px] rounded-[22px] bg-gradient-to-br from-primary to-primary-light shadow-[0_10px_24px_rgba(24,68,62,0.28)] flex items-center justify-center">
                            <i class="ri-sim-card-2-line text-surface text-[34px]"></i>
                        </div>
                    </div>
                    <span class="absolute top-0.5 right-0.5 size-9 rounded-full bg-success text-white flex items-center justify-center ring-4 ring-surface"><i class="ri-check-line text-[18px]"></i></span>
                    <span class="absolute -top-1 left-3 text-secondary text-[13px]"><i class="ri-add-line"></i></span>
                    <span class="absolute bottom-3 -left-1 size-2 rounded-full bg-primary-light"></span>
                    <span class="absolute -bottom-0.5 right-7 size-1.5 rounded-full bg-secondary"></span>
                    <span class="absolute top-7 -right-1.5 size-1.5 rounded-full bg-warning/70"></span>
                </div>

                <div class="flex flex-col items-center gap-2 w-full">
                    <p class="font-heading font-semibold text-text text-[22px] leading-none">eSIM Activated</p>
                    <div class="flex items-center gap-2 w-full max-w-[210px]">
                        <span class="h-px flex-1 bg-muted/20"></span>
                        <i class="ri-checkbox-circle-line text-primary/40 text-[16px]"></i>
                        <span class="h-px flex-1 bg-muted/20"></span>
                    </div>
                    <p class="font-sans text-muted text-[13.5px] leading-relaxed max-w-[320px]">Congratulations <strong class="text-primary font-semibold">{{ auth()->user()->username }}</strong>! {{ $successBody }}</p>
                </div>

                {{-- What the new eSIM unlocks --}}
                <div class="w-full rounded-2xl bg-background border border-muted/10 divide-y divide-muted/10 overflow-hidden">
                    @foreach ($successFeatures as $f)
                        <div class="flex items-center gap-3 px-4 py-3 text-left">
                            <span class="size-10 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0"><i class="{{ $f['icon'] }} text-[20px]"></i></span>
                            <div class="min-w-0">
                                <p class="font-heading text-text text-[14px] leading-tight">{{ $f['title'] }}</p>
                                <p class="font-sans text-muted text-[12px] leading-snug mt-0.5">{{ $f['desc'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <a href="{{ route('auth.setup-pin') }}" wire:navigate class="w-full h-[52px] rounded-full bg-primary text-surface font-sans font-semibold text-[15px] flex items-center justify-center gap-2 hover:opacity-95 transition shadow-[0_10px_24px_rgba(24,68,62,0.22)]">
                    Continue <i class="ri-arrow-right-line text-[18px]"></i>
                </a>

                <div class="flex items-center justify-center gap-1.5 text-muted text-[12px]">
                    <i class="ri-shield-check-line text-primary/60"></i> Secure. Reliable. Rewarding.
                </div>
            </div>
        @else
            <img src="{{ asset('images/activate-lock.png') }}" alt="" class="w-[72px] h-auto select-none pointer-events-none">
            <p class="font-heading font-semibold text-danger text-[16px]">Your eSIM is not activated</p>
            <p class="font-sans text-muted text-[12.5px] leading-snug max-w-[320px]">Your dashboard is available. Activate an eSIM to make calls, receive SMS, and access eSIM-specific features. Pay directly through the configured payment channel, or redeem a code from a vendor.</p>

            @if ($upline)
                <div class="inline-flex items-center gap-2 rounded-full bg-background border border-muted/10 pl-1.5 pr-3.5 py-1">
                    <span class="size-6 rounded-full bg-primary/10 overflow-hidden flex items-center justify-center shrink-0">
                        @if ($upline->avatar)
                            <img src="{{ $upline->avatar }}" alt="" class="size-full object-cover">
                        @else
                            <span class="font-heading font-semibold text-primary text-[10px]">{{ strtoupper(substr($upline->first_name ?: $upline->username, 0, 1)) }}</span>
                        @endif
                    </span>
                    <span class="font-sans text-muted text-[12px]">Promo ID <strong class="text-text font-medium">{{ $upline->username }}</strong></span>
                </div>
            @endif

            <div class="w-full flex flex-col gap-1 mt-1.5 text-left">
                <label class="font-sans text-[12.5px] text-text font-medium">Select Package</label>
                @php $priceFor = fn ($p) => \App\Support\RegionPricing::customer($p, $region); @endphp
                <div class="grid grid-cols-2 gap-2" x-data="{ pid: @entangle('packageId') }">
                    @foreach ($packages as $p)
                        <button type="button" @click="pid = '{{ $p->id }}'"
                                class="relative rounded-2xl border p-2.5 text-left transition"
                                :class="pid === '{{ $p->id }}' ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-muted/15 bg-background hover:border-muted/30'">
                            @if ($p->is_featured)
                                <span class="absolute right-2 top-2 rounded-full bg-secondary/40 text-primary px-1.5 py-0.5 font-sans text-[9px]">Popular</span>
                            @endif
                            <p class="font-heading font-medium text-text text-[13px] leading-tight">{{ $p->name }}</p>
                            <x-money :usd="$priceFor($p)" class="font-heading font-semibold text-primary text-[15px] leading-tight mt-0.5 block" />
                            <p class="font-sans text-muted text-[10px] mt-0.5">{{ rtrim(rtrim(number_format((float) $p->daily_call_minutes, 1), '0'), '.') }} mins · {{ $p->call_id_limit }} IDs</p>
                        </button>
                    @endforeach

                    {{-- Pay for the selected package directly — no vendor token needed. The hidden
                         field tracks the Alpine selection; the route re-validates everything. --}}
                    @if ($directPay ?? false)
                        <form method="POST" action="{{ route('plans.buy.start') }}" class="col-span-2 mt-0.5">
                            @csrf
                            <input type="hidden" name="package_id" :value="pid">
                            <input type="hidden" name="mode" value="activate">
                            <button type="submit" :disabled="! pid"
                                    class="w-full h-[48px] rounded-full bg-primary text-surface font-sans font-semibold text-[14px] inline-flex items-center justify-center gap-2 hover:opacity-95 transition disabled:opacity-40 disabled:cursor-not-allowed">
                                <i class="ri-secure-payment-line text-[17px]"></i> Pay directly
                            </button>
                        </form>
                    @endif
                </div>
                @error('packageId')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
            </div>

            <div class="w-full flex flex-col gap-1 text-left">
                <label class="font-sans text-[12.5px] text-text font-medium">Or redeem an eSIM Activation Code</label>
                <input type="text" wire:model="token" placeholder="Enter your eSIM code" wire:keydown.enter="activate"
                       class="w-full h-[46px] rounded-2xl bg-background border border-muted/20 px-4 outline-none font-sans text-[14px] text-text uppercase placeholder:text-muted placeholder:normal-case focus:border-primary focus:ring-1 focus:ring-primary">
                @error('token')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
            </div>

            <button type="button" wire:click="activate" wire:loading.attr="disabled"
                    class="w-full h-[48px] rounded-full bg-secondary text-primary font-sans font-semibold text-[15px] flex items-center justify-center gap-2 hover:opacity-95 transition disabled:opacity-60 mt-1">
                <span wire:loading.remove wire:target="activate">Activate</span>
                <span wire:loading wire:target="activate" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Activating…</span>
            </button>

            <a href="{{ route('agents') }}" wire:navigate
               class="w-full h-[44px] rounded-full border border-primary/25 text-primary font-sans font-semibold text-[14px] flex items-center justify-center gap-2 hover:bg-primary/5 transition">
                <i class="ri-store-2-line text-[16px]"></i> Purchase eSIM Code
            </a>

            @if (filled($telegram))
                <a href="{{ $telegram }}" target="_blank" rel="noopener noreferrer"
                   class="w-full rounded-2xl bg-[#229ED9]/10 border-2 border-[#229ED9]/40 px-3.5 py-2 flex items-center gap-2.5 hover:bg-[#229ED9]/20 transition mt-0.5 text-left">
                    <span class="size-9 rounded-full bg-[#229ED9] text-white flex items-center justify-center shrink-0"><i class="ri-telegram-2-fill text-[20px]"></i></span>
                    <span class="min-w-0">
                        <span class="block font-heading font-bold text-[#229ED9] text-[14px] leading-tight">Join our Official Telegram channel!</span>
                        <span class="block font-sans text-muted text-[12px] leading-tight mt-0.5">To get real-time updates</span>
                    </span>
                </a>
            @endif
        @endif
        </div>
    </div>

    @unless ($done)
        <form method="POST" action="{{ route('logout') }}" class="shrink-0 mt-3">
            @csrf
            <button type="submit" class="font-sans text-muted text-[13px] hover:text-danger transition"
                    @if (filled($telegram)) x-data @click="window.open(@js($telegram), '_blank', 'noopener')" @endif>Log out</button>
        </form>
    @endunless
</div>
