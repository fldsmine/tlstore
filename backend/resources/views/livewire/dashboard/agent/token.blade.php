<div class="min-h-screen bg-background">
    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-12 flex flex-col gap-[22px]">

        <x-app.app-bar title="My Tokens" />

        <div class="flex flex-col gap-5 w-full lg:max-w-[820px] lg:mx-auto">

            @if (session('token-success'))
                <div class="flex items-center gap-2.5 bg-success/12 rounded-[16px] px-4 py-3" x-data x-init="setTimeout(() => $el.remove(), 5000)">
                    <i class="ri-checkbox-circle-fill text-success text-[18px] shrink-0"></i>
                    <p class="font-sans text-[13px] text-text">{{ session('token-success') }}</p>
                </div>
            @endif

            <div class="bg-surface rounded-[20px] shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)] px-5 py-6 flex items-center justify-between">
                @foreach ([['total', 'Total Token', 'text-[#d79937]'], ['active', 'Active Token', 'text-success'], ['used', 'Used Token', 'text-danger']] as [$key, $label, $color])
                    <div class="flex flex-col items-center gap-2">
                        <p class="font-heading font-medium {{ $color }} text-[26px] leading-none">{{ number_format($stats[$key]) }}</p>
                        <p class="font-sans text-muted text-[13px]">{{ $label }}</p>
                    </div>
                    @unless ($loop->last)<span class="w-px h-10 bg-black/10"></span>@endunless
                @endforeach
            </div>

            <div class="flex items-center justify-between gap-3">
                <button type="button" wire:click="openGenerate"
                        class="h-[46px] inline-flex items-center justify-center gap-1.5 px-5 rounded-[30px] bg-primary text-surface font-sans font-semibold text-[14px] hover:opacity-95 transition">
                    <i class="ri-add-line text-[17px]"></i> Generate Token
                </button>
                @if ($exportUrl)
                    {{-- Signed GET link so the file works in every surface (browser, Telegram Mini
                         App, Android WebView) — see resources/js/downloads.js. --}}
                    <x-app.download-link
                        :href="$exportUrl"
                        :filename="$exportFilename"
                        mime="text/csv"
                        class="h-[46px] inline-flex items-center justify-center gap-1.5 px-5 rounded-[30px] border border-primary text-primary font-sans font-semibold text-[14px] hover:bg-primary/5 transition">
                        <i class="ri-download-2-line text-[16px]"></i> Export
                    </x-app.download-link>
                @else
                    <span aria-disabled="true"
                          class="h-[46px] inline-flex items-center justify-center gap-1.5 px-5 rounded-[30px] border border-primary/40 text-primary/40 font-sans font-semibold text-[14px] cursor-not-allowed">
                        <i class="ri-download-2-line text-[16px]"></i> Export
                    </span>
                @endif
            </div>

            @if ($selectionTooLarge)
                <p class="font-sans text-muted text-[12px] px-1">You have ticked more than {{ number_format(\App\Services\Agent\CouponExportService::MAX_SELECTED_IDS) }} codes, which is too many to export as a selection — Export downloads all of your tokens.</p>
            @endif

            @if ($coupons->isEmpty())
                <div class="bg-surface rounded-[20px] shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)] py-14 flex flex-col items-center text-center gap-3">
                    <span class="size-[96px] rounded-full bg-background flex items-center justify-center text-muted/50"><i class="ri-coupon-3-line text-[44px]"></i></span>
                    <p class="font-heading font-medium text-text text-[17px]">No tokens yet</p>
                    <p class="font-sans text-muted text-[13px] max-w-[260px]">Generate your first batch of tokens to start reselling Nextel plans.</p>
                    <button type="button" wire:click="openGenerate" class="mt-1 h-[44px] px-5 rounded-[30px] bg-primary text-surface font-sans font-semibold text-[14px] inline-flex items-center gap-1.5 hover:opacity-95 transition">
                        <i class="ri-add-line"></i> Generate Token
                    </button>
                </div>
            @else
                <div class="bg-surface rounded-[20px] shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)] overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-background/60 font-sans text-muted text-[12px]">
                                    <th class="py-3 pl-4 pr-2 w-9">
                                        <input type="checkbox"
                                               @change="$wire.set('selected', $event.target.checked ? @js($coupons->pluck('id')) : [])"
                                               class="size-4 rounded border-muted/40 accent-[#18443E]">
                                    </th>
                                    <th class="py-3 px-2 w-10">S/N</th>
                                    <th class="py-3 px-2">Token</th>
                                    <th class="py-3 px-2">Plan</th>
                                    <th class="py-3 px-2">Region</th>
                                    <th class="py-3 pr-4 pl-2 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="font-sans text-[13px]">
                                @foreach ($coupons as $i => $coupon)
                                    @php $plan = $coupon->displayName(); @endphp
                                    <tr class="border-t border-background">
                                        <td class="py-3 pl-4 pr-2">
                                            <input type="checkbox" wire:model="selected" value="{{ $coupon->id }}"
                                                   class="size-4 rounded border-muted/40 accent-[#18443E]">
                                        </td>
                                        <td class="py-3 px-2 text-muted">{{ $i + 1 }}</td>
                                        <td class="py-3 px-2">
                                            <button type="button" class="inline-flex items-center gap-1.5 text-text font-medium hover:text-primary transition group"
                                                    x-data="{ copied: false }"
                                                    @click="nextelCopy('{{ $coupon->code }}').then(ok => { if (!ok) return; copied = true; setTimeout(() => { copied = false }, 1200) })"
                                                    title="Copy code">
                                                <span class="tracking-wide">{{ $coupon->code }}</span>
                                                <i class="text-[14px] text-muted group-hover:text-primary" :class="copied ? 'ri-check-line text-success' : 'ri-file-copy-line'"></i>
                                            </button>
                                        </td>
                                        <td class="py-3 px-2 text-muted truncate max-w-[120px]">
                                            {{ $plan }}
                                            @if ($coupon->type === \App\Enums\CouponType::STORAGE_ACTIVATION)
                                                <span class="ml-1 inline-flex items-center gap-1 rounded-full bg-success/12 text-success px-2 py-0.5 font-sans text-[10.5px] font-semibold align-middle">Special</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-2"><x-region-badge :region="$coupon->region" /></td>
                                        <td class="py-3 pr-4 pl-2 text-right">
                                            @if ($coupon->is_valid)
                                                <span class="inline-flex items-center gap-1 text-success font-medium"><span class="size-1.5 rounded-full bg-success"></span>Active</span>
                                            @else
                                                <span class="inline-flex items-center gap-1 text-muted"><span class="size-1.5 rounded-full bg-muted"></span>Used</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <p class="font-sans text-muted text-[12px] px-1">Tick rows then Export, or Export with none ticked to download all. Tap a code to copy it.</p>
            @endif
        </div>
    </div>

    @if ($generating)
        <div data-lock-scroll class="fixed inset-0 z-[90] flex items-end justify-center sm:items-center sm:p-4"
             x-data="{ in: false }" x-init="$nextTick(() => in = true)">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" x-show="in" x-transition.opacity @click="$wire.closeGenerate()"></div>

            <div class="relative w-full sm:max-w-[420px] bg-background rounded-t-[26px] sm:rounded-[26px] max-h-[92vh] overflow-y-auto px-6 pt-6 pb-8 flex flex-col gap-4"
                 x-show="in"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">

                <button type="button" wire:click="closeGenerate" class="absolute right-5 top-5 size-9 rounded-full bg-surface shadow-sm flex items-center justify-center text-text" aria-label="Close">
                    <i class="ri-close-line text-[20px]"></i>
                </button>

                <div class="flex flex-col gap-1 mt-1">
                    <p class="font-heading font-semibold text-primary text-[22px]">Generate Nextel Token</p>
                    <p class="font-sans text-muted text-[13px] leading-relaxed max-w-[330px]">Choose a plan and quantity — your vendor wallet is debited based on the plan purchased.</p>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-[14px] text-text">Plan</label>
                    <div class="relative">
                        <select wire:model.live="planKey"
                                class="w-full h-[48px] rounded-[14px] border-[0.5px] border-primary bg-transparent pl-4 pr-10 outline-none appearance-none font-sans text-[14px] text-text focus:ring-1 focus:ring-primary">
                            <option value="">Select a Plan</option>
                            @foreach ($planOptions->groupBy('group') as $group => $opts)
                                <optgroup label="{{ $group }}">
                                    @foreach ($opts as $opt)
                                        <option value="{{ $opt['key'] }}">{{ $opt['label'] }} — {{ money($opt['agent_price']) }}/code</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <i class="ri-arrow-down-s-line text-[20px] text-muted absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                    </div>
                    @error('planKey')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                </div>

                @if (($selectedPlan['special'] ?? false))
                    <div class="flex items-start gap-2 rounded-[12px] bg-success/10 px-3.5 py-3">
                        <i class="ri-information-line text-success text-[16px] shrink-0 mt-0.5"></i>
                        <p class="font-sans text-[12px] text-text leading-relaxed">Special coupon — the buyer activates tier storage with this code, and the same code authorizes their N-Cloud withdrawals. Expires {{ (int) config('settings.token.special_expiry_days', 90) }} days after generation.</p>
                    </div>
                @endif
                @if (($selectedPlan['loan_fee'] ?? false))
                    <div class="flex items-start gap-2 rounded-[12px] bg-warning/10 px-3.5 py-3">
                        <i class="ri-information-line text-warning text-[16px] shrink-0 mt-0.5"></i>
                        <p class="font-sans text-[12px] text-text leading-relaxed">One-time loan application fee coupon. The fee is non-refundable and the code can be redeemed once; your vendor balance is charged at the price shown in Loan settings.</p>
                    </div>
                @endif

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-[14px] text-text">Number of Codes</label>
                    <input type="number" min="1" max="500" wire:model.live.debounce.400ms="quantity" placeholder="1"
                           class="w-full h-[48px] rounded-[14px] border-[0.5px] border-primary bg-transparent px-4 outline-none font-sans text-[14px] text-text focus:ring-1 focus:ring-primary">
                    @error('quantity')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                </div>

                <div class="rounded-[18px] border border-secondary bg-secondary/20 px-5 py-4 flex flex-col gap-1">
                    <span class="font-sans text-muted text-[12px]">Estimated Debit</span>
                    <x-money :usd="$estimate" class="font-heading font-semibold text-text text-[28px] leading-none" />
                    <span class="font-sans text-muted text-[12px]">Vendor balance: <x-money :usd="$agentBalance" class="font-semibold text-text" /></span>
                </div>

                <div class="flex items-center gap-3 mt-1">
                    <button type="button" wire:click="generate" wire:loading.attr="disabled" wire:target="generate" @disabled(! $selectedPlan || $estimate > $agentBalance)
                            class="flex-1 h-[50px] rounded-[30px] bg-primary text-surface font-sans font-semibold text-[14px] flex items-center justify-center gap-2 hover:opacity-95 transition disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="generate" class="flex items-center gap-2"><i class="ri-coupon-3-line"></i> Generate Token</span>
                        <span wire:loading wire:target="generate" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Generating…</span>
                    </button>
                    <button type="button" wire:click="closeGenerate" class="h-[50px] px-6 rounded-[30px] bg-surface text-text font-sans font-semibold text-[14px] shadow-sm hover:bg-surface/70 transition">Cancel</button>
                </div>
            </div>
        </div>
    @endif

    <x-app.bottom-nav />
</div>
