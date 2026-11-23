<div class="min-h-screen bg-background">
    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-12 flex flex-col gap-[22px]">

        <x-app.app-bar :title="$init['label'] ?? 'Buy'" subtitle="Pay instantly from your Nextel wallet" :back="true" />

        @unless ($hasWallet)
            <div class="bg-surface rounded-[24px] p-7 flex flex-col items-center text-center gap-3 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)] mt-2 lg:max-w-[460px] lg:mx-auto lg:w-full">
                <span class="size-16 rounded-full bg-warning/15 flex items-center justify-center"><i class="ri-time-line text-warning text-[30px]"></i></span>
                <p class="font-heading font-medium text-text text-[18px]">Not available yet</p>
                <p class="font-sans text-muted text-[13px] leading-relaxed max-w-[300px]">Bill payments aren’t enabled on your account right now. Please check back soon.</p>
                <a href="{{ route('vas') }}" wire:navigate class="mt-1 text-primary font-sans font-semibold text-[14px]">Back to services</a>
            </div>
        @else
            {{-- Client-side Alpine — no server round-trips until Verify or Pay. --}}
            <div x-data="vasPurchase(@js($init))" class="flex flex-col gap-4 lg:grid lg:grid-cols-2 lg:gap-5 lg:items-start">

                <div class="flex flex-col gap-4">

                    <div class="flex flex-col gap-2">
                        <p class="font-sans font-semibold text-text text-[14px] px-1">Provider</p>
                        <div class="grid grid-cols-3 sm:grid-cols-4 gap-2.5">
                            <template x-for="p in d.providers" :key="p.id">
                                <button type="button" @click="selectProvider(p.id)"
                                        class="flex flex-col items-center gap-1.5 rounded-[16px] p-2.5 transition border"
                                        :class="serviceId === p.id ? 'bg-surface border-primary shadow-sm' : 'bg-surface border-transparent hover:border-muted/20'">
                                    <template x-if="p.logo">
                                        <img :src="p.logo" alt="" class="size-9 rounded-full object-cover">
                                    </template>
                                    <template x-if="!p.logo">
                                        <span class="size-9 rounded-full bg-primary/10 text-primary flex items-center justify-center font-heading font-semibold text-[15px]" x-text="p.label.charAt(0)"></span>
                                    </template>
                                    <span class="font-sans text-[11px] text-text text-center leading-tight truncate w-full" x-text="p.label"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div x-data="{ show: true }" class="flex flex-col gap-2.5 bg-surface rounded-[20px] p-4 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                        <div class="flex items-center justify-between gap-3">
                            <div class="relative">
                                <select x-model="wallet"
                                        class="appearance-none max-w-[220px] h-[36px] rounded-full bg-background pl-4 pr-9 font-sans text-[13px] font-medium text-text outline-none focus:ring-1 focus:ring-primary cursor-pointer">
                                    <template x-for="(w, key) in d.wallets" :key="key">
                                        <option :value="key" x-text="w.label"></option>
                                    </template>
                                </select>
                                <i class="ri-arrow-down-s-line text-[18px] text-primary absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                            </div>
                            <button type="button" @click="show = !show" class="text-muted shrink-0" aria-label="Toggle balance">
                                <i class="ri-eye-line text-[19px]" x-show="show"></i>
                                <i class="ri-eye-off-line text-[19px]" x-show="!show" x-cloak></i>
                            </button>
                        </div>
                        <div class="flex items-end justify-between">
                            <p class="font-heading font-semibold text-text text-[28px] leading-none">
                                <span x-show="show" x-text="walletData.display"></span>
                                <span x-show="!show" x-cloak>••••••</span>
                            </p>
                            <p class="font-sans text-muted text-[12px]" x-text="d.isLocal ? ('≈ ' + usd(walletData.balance)) : ('≈ ₦' + fmt(walletData.ngn))"></p>
                        </div>
                    </div>

                    <div x-show="d.category === 'electricity'" class="flex items-center gap-2 bg-surface rounded-full p-1 shadow-sm">
                        <template x-for="mt in d.meterTypes" :key="mt.code">
                            <button type="button" @click="meterType = mt.code"
                                    class="flex-1 h-[38px] rounded-full font-sans text-[13px] font-medium transition"
                                    :class="meterType === mt.code ? 'bg-primary text-surface' : 'text-muted'" x-text="mt.label"></button>
                        </template>
                    </div>

                    {{-- Amount entry: airtime, electricity. --}}
                    <div x-show="entersAmount" class="bg-surface rounded-[22px] p-5 flex flex-col gap-3.5 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                        <div class="flex items-center justify-between">
                            <span class="font-sans text-muted text-[13px]">Amount</span>
                            <span class="font-sans text-[12px] text-primary" x-show="limitLabel" x-text="limitLabel"></span>
                        </div>
                        <label class="flex items-baseline justify-center gap-1.5 w-full cursor-text py-1">
                            <span class="font-heading font-semibold text-text text-[26px] leading-none">₦</span>
                            <input type="text" inputmode="numeric" x-model="amount" placeholder="0"
                                   class="field-sizing-content min-w-[70px] max-w-[200px] bg-transparent outline-none border-0 p-0 text-center font-heading font-semibold text-text text-[40px] leading-none placeholder:text-muted/40">
                        </label>
                        <p class="text-center font-sans text-[12px] text-muted">≈ <span x-text="usd(usdAmount)"></span> debited</p>
                        <div class="grid grid-cols-4 gap-2" x-show="picks().length">
                            <template x-for="v in picks()" :key="v">
                                <button type="button" @click="amount = String(v)"
                                        class="h-[36px] rounded-[12px] bg-background font-sans text-[13px] font-medium text-text hover:bg-primary/10 transition">₦<span x-text="fmt(v)"></span></button>
                            </template>
                        </div>
                        <span class="font-sans text-[12px] text-danger text-center" x-show="amountError" x-text="amountError"></span>
                    </div>

                    {{-- Plan picker: data, TV, education. --}}
                    <div x-show="!entersAmount" class="flex flex-col gap-2">
                        <div class="flex items-center justify-between px-1">
                            <p class="font-sans font-semibold text-text text-[14px]">Choose a plan</p>
                            <span class="font-sans text-[12px] text-primary" x-show="limitLabel" x-text="limitLabel"></span>
                        </div>
                        <div class="bg-surface rounded-[16px] p-5 text-center font-sans text-[13px] text-muted shadow-sm" x-show="plans.length === 0">No plans available for this provider right now.</div>
                        <div class="flex flex-col gap-2 max-h-[320px] overflow-y-auto pr-0.5" x-show="plans.length">
                            <template x-for="plan in plans" :key="plan.code">
                                <button type="button" @click="!planOver(plan) && (variationCode = plan.code)" :disabled="planOver(plan)"
                                        class="flex items-center justify-between gap-3 rounded-[16px] px-4 py-3 text-left transition border"
                                        :class="[variationCode === plan.code ? 'bg-surface border-primary shadow-sm' : 'bg-surface border-transparent hover:border-muted/20', planOver(plan) ? 'opacity-45 cursor-not-allowed' : '']">
                                    <span class="font-sans text-[13px] text-text min-w-0 flex-1 truncate" x-text="plan.label"></span>
                                    <span class="font-heading font-semibold text-primary text-[14px] shrink-0">₦<span x-text="fmt(plan.amount)"></span></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-4">

                    <div x-show="d.category === 'tv'" class="flex items-center gap-2 bg-surface rounded-full p-1 shadow-sm">
                        <template x-for="opt in [{c:'renew',l:'Renew'},{c:'change',l:'Change plan'}]" :key="opt.c">
                            <button type="button" @click="subscriptionType = opt.c"
                                    class="flex-1 h-[38px] rounded-full font-sans text-[13px] font-medium transition"
                                    :class="subscriptionType === opt.c ? 'bg-primary text-surface' : 'text-muted'" x-text="opt.l"></button>
                        </template>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <p class="font-sans font-semibold text-text text-[14px] px-1" x-text="recipientLabel"></p>
                        <div class="flex items-stretch gap-2">
                            <input type="text" x-model="recipient" :inputmode="recipientIsPhone ? 'numeric' : 'text'"
                                   :placeholder="recipientIsPhone ? '080XXXXXXXX' : ('Enter ' + recipientLabel.toLowerCase())"
                                   class="flex-1 min-w-0 h-[50px] rounded-[16px] bg-surface px-4 font-sans text-[14px] text-text outline-none border border-transparent focus:border-primary shadow-sm">
                            <button type="button" x-show="needsVerify" @click="verify" :disabled="verifying"
                                    class="shrink-0 px-4 rounded-[16px] bg-primary/10 text-primary font-sans font-semibold text-[13px] hover:bg-primary/15 transition disabled:opacity-50">
                                <span x-show="!verifying">Verify</span>
                                <span x-show="verifying" x-cloak><i class="ri-loader-4-line animate-spin"></i></span>
                            </button>
                        </div>
                        <span class="font-sans text-[12px] text-danger px-1" x-show="recipientError" x-text="recipientError"></span>
                        <div class="flex items-center gap-2 bg-success/10 rounded-[12px] px-3 py-2 mt-0.5" x-show="verified && customerName" x-cloak>
                            <i class="ri-checkbox-circle-fill text-success text-[16px]"></i>
                            <span class="font-sans text-[13px] text-text truncate" x-text="customerName"></span>
                        </div>
                    </div>

                    {{-- Contact phone for verify categories. --}}
                    <div x-show="!recipientIsPhone" x-cloak class="flex flex-col gap-1.5">
                        <p class="font-sans font-semibold text-text text-[14px] px-1">Contact phone</p>
                        <input type="text" inputmode="numeric" x-model="phone" placeholder="080XXXXXXXX"
                               class="h-[50px] rounded-[16px] bg-surface px-4 font-sans text-[14px] text-text outline-none border border-transparent focus:border-primary shadow-sm">
                    </div>

                    <div class="bg-surface rounded-[20px] p-5 flex flex-col gap-2.5 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                        <div class="flex items-center justify-between font-sans text-[13px]">
                            <span class="text-muted">You pay</span>
                            <span class="font-heading font-semibold text-text text-[16px]">₦<span x-text="fmt(ngnAmount)"></span></span>
                        </div>
                        <div class="flex items-center justify-between font-sans text-[13px] border-t border-background pt-2.5">
                            <span class="text-muted">Debited from <span x-text="walletData.label"></span></span>
                            <span class="font-semibold text-danger" x-text="usd(usdAmount)"></span>
                        </div>
                    </div>

                    <button type="button" @click="openConfirm" :disabled="!canContinue"
                            class="mt-1 h-[52px] rounded-[30px] bg-primary text-surface flex items-center justify-center gap-2 font-sans font-semibold text-[15px] hover:opacity-95 transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="ri-shopping-bag-line text-[17px]"></i>
                        <span x-text="ngnAmount > 0 ? ('Continue · ₦' + fmt(ngnAmount)) : 'Continue'"></span>
                    </button>
                </div>

                <div x-show="confirming" x-cloak :data-lock-scroll="confirming ? '' : null"
                     class="fixed inset-0 z-[90] flex items-end justify-center sm:items-center sm:p-4">
                    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" x-show="confirming" x-transition.opacity @click="closeConfirm"></div>

                    <div class="relative w-full sm:max-w-[400px] bg-background rounded-t-[26px] sm:rounded-[26px] max-h-[92vh] overflow-y-auto px-6 pt-6 pb-8 flex flex-col gap-4"
                         x-show="confirming"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">

                        <button type="button" @click="closeConfirm" class="absolute right-5 top-5 size-9 rounded-full bg-surface shadow-sm flex items-center justify-center text-text" aria-label="Close">
                            <i class="ri-close-line text-[20px]"></i>
                        </button>

                        <div class="flex flex-col items-center text-center gap-2 mt-1">
                            <span class="size-14 rounded-full bg-primary/10 flex items-center justify-center"><i class="ri-shield-keyhole-line text-primary text-[28px]"></i></span>
                            <p class="font-heading font-medium text-text text-[19px]">Confirm purchase</p>
                            <p class="font-sans text-muted text-[13px] leading-relaxed max-w-[290px]">Enter your 4-digit transaction PIN to pay <span class="font-semibold text-text">₦<span x-text="fmt(ngnAmount)"></span></span>.</p>
                        </div>

                        <div class="bg-surface rounded-[16px] p-4 flex flex-col gap-2 font-sans text-[13px]">
                            <div class="flex items-center justify-between"><span class="text-muted" x-text="d.label"></span><span class="text-text font-medium" x-text="walletData.label"></span></div>
                            <div class="flex items-center justify-between"><span class="text-muted">Recipient</span><span class="text-text font-medium truncate max-w-[180px]" x-text="recipient"></span></div>
                            <div class="flex items-center justify-between" x-show="customerName"><span class="text-muted">Name</span><span class="text-text font-medium truncate max-w-[180px]" x-text="customerName"></span></div>
                            <div class="flex items-center justify-between"><span class="text-muted">Amount</span><span class="text-text">₦<span x-text="fmt(ngnAmount)"></span></span></div>
                            <div class="flex items-center justify-between border-t border-background pt-2"><span class="font-semibold text-text">Debited</span><span class="font-semibold text-danger" x-text="usd(usdAmount)"></span></div>
                        </div>

                        <div class="flex flex-col gap-2.5">
                            <label class="font-sans text-[13px] text-text font-medium text-center">Transaction PIN</label>
                            <div class="flex items-center justify-center gap-3">
                                @for ($i = 0; $i < 4; $i++)
                                    <input type="password" inputmode="numeric" maxlength="1" autocomplete="off"
                                           x-ref="pb{{ $i }}" @input="pinInput({{ $i }}, $event)" @keydown="pinKey({{ $i }}, $event)"
                                           class="size-[58px] rounded-[16px] bg-surface border text-center font-heading font-semibold text-text text-[22px] outline-none transition focus:border-primary focus:ring-1 focus:ring-primary"
                                           :class="pinArr[{{ $i }}] ? 'border-primary' : 'border-muted/25'">
                                @endfor
                            </div>
                            <span class="font-sans text-[12px] text-danger text-center" x-show="submitError" x-text="submitError"></span>
                            <div class="flex items-center justify-between px-1 mt-0.5">
                                <a href="{{ route('reset-pin') }}" wire:navigate class="font-sans text-[12px] text-muted hover:text-primary">Forgot PIN?</a>
                            </div>
                        </div>

                        <button type="button" @click="submit" :disabled="pin.length < 4 || submitting"
                                class="h-[52px] rounded-[30px] bg-primary text-surface flex items-center justify-center gap-2 font-sans font-semibold text-[15px] hover:opacity-95 transition disabled:opacity-60 disabled:cursor-not-allowed">
                            <span x-show="!submitting" class="flex items-center gap-2"><i class="ri-checkbox-circle-line text-[18px]"></i> Pay ₦<span x-text="fmt(ngnAmount)"></span></span>
                            <span x-show="submitting" x-cloak class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Processing…</span>
                        </button>
                    </div>
                </div>
            </div>
        @endunless
    </div>

    <x-app.bottom-nav />
</div>
