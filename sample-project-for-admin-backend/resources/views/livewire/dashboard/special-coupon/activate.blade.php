<div class="min-h-screen bg-background">
    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-12 flex flex-col gap-[22px]">

        <x-app.app-bar title="Revenue Authentication" subtitle="Bonus cloud storage for your package tier" />

        @if (! $hasPackage)
            <x-app.feature-locked icon="ri-sim-card-2-line"
                title="Activate your account first"
                message="You need an active eSIM plan before you can use a special revenue code. Activate your account to unlock storage bonuses and N-Cloud withdrawals.">
                <button type="button" x-data @click="Livewire.dispatch('open-activation')"
                   class="h-[48px] px-6 rounded-[30px] bg-primary text-surface inline-flex items-center justify-center gap-2 font-sans font-semibold text-[14px] hover:opacity-95 transition">
                    <i class="ri-flashlight-line text-[18px]"></i> Activate a Plan
                </button>
            </x-app.feature-locked>
        @elseif (! $showForm)
            {{-- Success state --}}
            <div class="w-full lg:max-w-[560px] lg:mx-auto bg-surface rounded-[20px] px-7 py-12 flex flex-col items-center text-center gap-4 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                <span class="size-16 rounded-full bg-success/12 text-success flex items-center justify-center"><i class="ri-checkbox-circle-fill text-[36px]"></i></span>
                <p class="font-heading font-semibold text-text text-[19px]">Storage activated</p>
                <p class="font-sans text-muted text-[13px] leading-relaxed max-w-[300px]">{{ $successMessage }}</p>
                <div class="flex flex-wrap items-center justify-center gap-2 mt-2">
                    <button type="button" wire:click="resetForm"
                            class="h-[46px] px-5 rounded-[30px] bg-surface text-text font-sans font-semibold text-[14px] shadow-sm hover:bg-surface/70 transition">
                        Activate another code
                    </button>
                    <a href="{{ route('cloud') }}" wire:navigate
                       class="h-[46px] px-5 rounded-[30px] bg-primary text-surface font-sans font-semibold text-[14px] inline-flex items-center gap-1.5 hover:opacity-95 transition">
                        <i class="ri-cloud-line text-[16px]"></i> Go to Nextel Cloud
                    </a>
                </div>
            </div>
        @else
            <div class="flex flex-col gap-4 w-full lg:max-w-[560px] lg:mx-auto">

                @if ($revenueCode)
                    {{-- A code the platform issued to this user — bought with a card, or issued by support
                         against payment proof. They never typed it anywhere, so this page is where they
                         read it back. Vendor codes are never displayed here. --}}
                    <div class="bg-surface rounded-[20px] p-5 flex flex-col gap-3 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)] @if ($justIssued) ring-1 ring-success/40 @endif">
                        <div class="flex items-center gap-3">
                            <span class="size-11 rounded-[14px] bg-success/10 text-success flex items-center justify-center shrink-0"><i class="ri-shield-keyhole-line text-[21px]"></i></span>
                            <div class="min-w-0 flex-1">
                                <p class="font-heading font-medium text-text text-[15px]">{{ $justIssued ? 'Your code is ready' : 'Your revenue code' }}</p>
                                <p class="font-sans text-muted text-[12px] truncate">{{ $revenueCode->cloudStoragePlan?->name ?? 'Revenue Authentication' }}</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-success/10 text-success font-sans text-[11px] font-semibold px-2.5 py-1">Active</span>
                        </div>

                        @if ($justIssued)
                            <p class="font-sans text-muted text-[12.5px] leading-relaxed">Payment verified — this code was issued to your account automatically and is already active. Nothing else to activate.</p>
                        @endif

                        <div x-data="{ copied: false }" class="flex gap-2">
                            <div class="flex-1 min-w-0 h-12 rounded-[12px] bg-background border border-muted/15 px-4 flex items-center font-mono text-[13.5px] tracking-wider uppercase text-text overflow-x-auto whitespace-nowrap">{{ $revenueCode->code }}</div>
                            <button type="button" aria-label="Copy revenue code"
                                    @click="navigator.clipboard.writeText(@js($revenueCode->code)); copied = true; setTimeout(() => copied = false, 1400)"
                                    class="shrink-0 inline-flex items-center justify-center gap-1.5 h-12 px-4 rounded-[12px] border border-primary/25 bg-primary/[0.06] text-primary font-sans text-[13px] hover:bg-primary/10 transition">
                                <i class="ri-file-copy-line text-[15px]" x-show="! copied"></i>
                                <i class="ri-check-line text-[15px]" x-show="copied" x-cloak></i>
                                <span x-text="copied ? 'Copied' : 'Copy'"></span>
                            </button>
                        </div>

                        <div class="rounded-[12px] bg-background px-4 py-3 flex flex-col gap-2">
                            @if ($revenueGrants)
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-sans text-muted text-[12px]">Storage added</span>
                                    <span class="font-sans text-success text-[12.5px] font-semibold">{{ $revenueGrants }}</span>
                                </div>
                            @endif
                            <div class="flex items-center justify-between gap-3">
                                <span class="font-sans text-muted text-[12px]">Valid until</span>
                                <span class="font-sans text-text text-[12.5px] font-medium">{{ $revenueCode->expires_at?->format('j M Y') ?? 'No expiry' }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span class="font-sans text-muted text-[12px]">Withdrawal</span>
                                <span class="font-sans text-text text-[12.5px] font-medium">Not used yet</span>
                            </div>
                        </div>

                        <p class="font-sans text-muted text-[11.5px] leading-relaxed">Keep this code — entering it is what authorises and approves your N-Cloud withdrawal request.</p>

                        <a href="{{ route('withdraw') }}" wire:navigate
                           class="h-11 rounded-[12px] bg-primary text-surface font-sans font-semibold text-[13.5px] inline-flex items-center justify-center gap-1.5 hover:opacity-95 transition">
                            <i class="ri-bank-card-2-line text-[15px]"></i> Make a withdrawal
                        </a>
                    </div>
                @endif

                {{-- What the coupon does + the user's tier --}}
                <div class="bg-surface rounded-[20px] p-5 flex flex-col gap-3 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                    <div class="flex items-center gap-3">
                        <span class="size-11 rounded-[14px] bg-success/10 text-success flex items-center justify-center shrink-0"><i class="ri-gift-2-line text-[21px]"></i></span>
                        <div class="min-w-0">
                            <p class="font-heading font-medium text-text text-[15px]">Revenue Authentication</p>
                            <p class="font-sans text-muted text-[12px] truncate">Bonus cloud storage for your package tier</p>
                        </div>
                    </div>
                    <p class="font-sans text-muted text-[13px] leading-relaxed">This code unlocks extra sponsored features to boost your daily earnings and also authorizes and approves your withdrawal request.</p>
                    <div class="flex items-center justify-between rounded-[12px] bg-background px-4 py-3">
                        <span class="font-sans text-muted text-[12px]">Your package</span>
                        <span class="font-sans text-text text-[13px] font-medium">{{ $packageName }}@if ($tierLevel) · Tier {{ $tierLevel }}@endif</span>
                    </div>
                    @if ($tierStorageContacts)
                        <div class="flex items-center justify-between rounded-[12px] bg-background px-4 py-3">
                            <span class="font-sans text-muted text-[12px]">Storage for your tier</span>
                            <span class="font-sans text-success text-[13px] font-semibold">+{{ $tierStorageContacts }} contacts</span>
                        </div>
                    @endif
                    @if ($tierStorageSMS)
                        <div class="flex items-center justify-between rounded-[12px] bg-background px-4 py-3">
                            <span class="font-sans text-muted text-[12px]">SMS for your tier</span>
                            <span class="font-sans text-success text-[13px] font-semibold">+{{ $tierStorageSMS }} SMS/day</span>
                        </div>
                    @endif
                </div>

                {{-- Buy the code straight from the platform instead of sourcing a vendor coupon: the
                     payment is verified server-side and the code is issued to this account, storage
                     included, so it authorises a withdrawal right away. --}}
                <div class="bg-surface rounded-[20px] p-5 flex flex-col gap-3 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                    <div class="flex items-center gap-3">
                        <span class="size-11 rounded-[14px] bg-primary/10 text-primary flex items-center justify-center shrink-0"><i class="ri-bank-card-line text-[21px]"></i></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-heading font-medium text-text text-[15px]">Pay directly with card</p>
                            <p class="font-sans text-muted text-[12px] truncate">No vendor needed — issued to you automatically</p>
                        </div>
                        @if ($cardCheckout['available'])
                            <span class="shrink-0 font-heading font-semibold text-text text-[15px] tabular-nums"><x-money :usd="$cardCheckout['price']" /></span>
                        @endif
                    </div>

                    @if ($cardCheckout['available'])
                        <div class="flex flex-col gap-2 rounded-[12px] bg-background px-4 py-3">
                            <div class="flex items-start gap-2">
                                <span class="font-sans text-[11px] text-primary font-semibold mt-[1px]">1</span>
                                <p class="font-sans text-muted text-[12px] leading-relaxed">Pay with your card on the secure checkout page.</p>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="font-sans text-[11px] text-primary font-semibold mt-[1px]">2</span>
                                <p class="font-sans text-muted text-[12px] leading-relaxed">The payment is verified on our servers — we never rely on the browser coming back.</p>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="font-sans text-[11px] text-primary font-semibold mt-[1px]">3</span>
                                <p class="font-sans text-muted text-[12px] leading-relaxed">Your code appears on this page, already active, with @if ($cardGrants) {{ $cardGrants }} @else your storage bonus @endif added.</p>
                            </div>
                        </div>

                        @if ($openCheckout)
                            <div class="flex items-start gap-2 rounded-[12px] bg-warning/10 px-4 py-3">
                                <i class="ri-time-line text-warning text-[16px] shrink-0 mt-0.5"></i>
                                <p class="font-sans text-[12px] text-muted leading-relaxed">You already have a payment waiting to be completed — the button below opens the same checkout, so you won't be charged twice.</p>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('special-coupon.buy.start') }}">
                            @csrf
                            <input type="hidden" name="plan_id" value="{{ $cardCheckout['plan']->id }}">
                            <button type="submit"
                                    class="w-full h-12 rounded-[12px] bg-primary text-surface font-sans font-semibold text-[14px] inline-flex items-center justify-center gap-2 hover:opacity-95 transition">
                                <i class="ri-secure-payment-line text-[16px]"></i> Pay directly with card
                            </button>
                        </form>

                        <p class="font-sans text-muted text-[11.5px] leading-relaxed">Card payments are handled by our payment provider. If the payment goes through but your code is delayed, support can issue it manually — keep your payment reference.</p>
                    @else
                        <div class="flex items-start gap-2 rounded-[12px] bg-background px-4 py-3">
                            <i class="ri-information-line text-muted text-[16px] shrink-0 mt-0.5"></i>
                            <p class="font-sans text-muted text-[12px] leading-relaxed">The Revenue Authentication Code is currently no longer available for purchase. Kindly check back later for further updates on its availability.</p>
                        </div>
                    @endif

                    <a href="{{ $supportUrl }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-1.5 font-sans text-primary text-[12px] hover:underline">
                        <i class="ri-telegram-line text-[14px]"></i> Paid but no code? Message support on Telegram
                    </a>
                </div>

                {{-- Code entry --}}
                <div class="bg-surface rounded-[20px] p-5 flex flex-col gap-3 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                    <label for="special-coupon-code" class="font-sans text-[13px] text-text font-medium">ENTER REVENUE CODE</label>
                    <div class="flex gap-2">
                        <input type="text" id="special-coupon-code" wire:model="code" wire:keydown.enter="activate" placeholder="AUTHENTICATION CODE" autocomplete="off" aria-label="Revenue Authentication code"
                               class="flex-1 min-w-0 h-12 rounded-[12px] bg-background border border-muted/15 px-4 font-mono text-[14px] tracking-wider uppercase text-text placeholder:text-muted/50 placeholder:font-sans placeholder:tracking-normal focus:outline-none focus:border-primary/40 transition @error('code') border-danger @enderror">
                        <button type="button" wire:click="activate" wire:loading.attr="disabled" wire:target="activate"
                                class="shrink-0 inline-flex items-center justify-center gap-1.5 h-12 px-5 rounded-[12px] bg-primary text-surface font-sans font-semibold text-[14px] hover:opacity-95 transition disabled:opacity-60 disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="activate" class="inline-flex items-center gap-1.5"><i class="ri-check-line text-[16px]"></i> Activate</span>
                            <span wire:loading wire:target="activate"><i class="ri-loader-4-line animate-spin"></i></span>
                        </button>
                    </div>
                    @error('code')<p class="font-sans text-danger text-[12px]">{{ $message }}</p>@enderror
                    <div class="flex items-center gap-1 font-sans text-[12px] text-muted">
                        <i class="ri-shield-check-line text-[13px]"></i>
                        <span>Not sure it's valid?</span>
                        <x-app.coupon-verify-sheet label="Verify a coupon code" />
                    </div>
                    <p class="font-sans text-muted text-[11.5px] leading-relaxed">Bought a code from a vendor? Enter it above. The code is verified securely and cleared right after activation — it is never stored on this page.</p>
                </div>

                {{-- How the code works --}}
                <div class="flex items-start gap-2.5 bg-secondary/15 rounded-[16px] px-4 py-3">
                    <i class="ri-shield-check-line text-primary text-[18px] shrink-0 mt-0.5"></i>
                    <p class="font-sans text-[12px] text-muted leading-relaxed">One code activates storage once and authorizes a single N-Cloud withdrawal. Codes expire {{ $expiryDays }} days after they are generated — use yours before then.</p>
                </div>

            </div>
        @endif
    </div>

    <x-app.bottom-nav />
</div>
