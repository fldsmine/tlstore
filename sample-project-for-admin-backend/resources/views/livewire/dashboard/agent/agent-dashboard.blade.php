<div class="min-h-screen lg:h-screen lg:overflow-hidden bg-background">
    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-6 lg:h-full flex flex-col gap-[22px] lg:gap-5">

        <x-app.app-bar title="Vendor Dashboard" subtitle="Generate and resell Nextel tokens" />

        @if (session('token-success'))
            <div class="flex items-center gap-2.5 bg-success/12 rounded-[16px] px-4 py-3" x-data x-init="setTimeout(() => $el.remove(), 5000)">
                <i class="ri-checkbox-circle-fill text-success text-[18px] shrink-0"></i>
                <p class="font-sans text-[13px] text-text">{{ session('token-success') }}</p>
            </div>
        @endif

        @if (config('settings.chat.enabled'))
        @php $liveChatCount = (int) \App\Models\Chat::query()->where('agent_id', auth()->id())
            ->whereHas('messages', fn ($q) => $q->where('sender', 'customer')
                ->where(fn ($w) => $w->whereNull('chats.agent_read_at')
                    ->orWhereColumn('chat_messages.created_at', '>', 'chats.agent_read_at')))
            ->count(); @endphp
        <a href="{{ route('agent.chats') }}" wire:navigate
           class="bg-surface rounded-[20px] p-5 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)] flex items-center gap-4 hover:shadow-md transition">
            <span class="size-11 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0"><i class="ri-chat-smile-2-line text-[20px]"></i></span>
            <div class="flex-1 min-w-0">
                <p class="font-heading font-medium text-text text-[15px]">Live Chats</p>
                <p class="font-sans text-muted text-[12px]">Sell tokens to customers in real time</p>
            </div>
            <span x-data="{ n: {{ $liveChatCount }} }" @live-chats-badge.window="n = $event.detail.count" x-show="n > 0" x-cloak
                  class="shrink-0 min-w-[22px] h-[22px] px-1.5 rounded-full bg-primary text-surface text-[12px] font-heading font-semibold flex items-center justify-center"
                  x-text="n > 99 ? '99+' : n">{{ $liveChatCount > 99 ? '99+' : $liveChatCount }}</span>
            <i class="ri-arrow-right-s-line text-muted text-[22px]"></i>
        </a>
        @endif

        <div class="flex flex-col gap-6 lg:flex-row lg:gap-6 lg:flex-1 lg:min-h-0">

            <div class="flex flex-col gap-5 lg:w-[38%] lg:max-w-[400px] lg:shrink-0 lg:self-start lg:bg-surface lg:rounded-[24px] lg:p-6 lg:shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                <div x-data="{ show: true }" class="flex items-start justify-between">
                    <div class="flex flex-col gap-2">
                        <p class="font-sans text-muted text-[14px]">Available Balance</p>
                        <p class="font-heading font-semibold text-text text-[30px] leading-none">
                            <x-money :usd="$agentBalance" x-show="show" />
                            <span x-show="!show" x-cloak>{{ \App\Support\DisplayCurrency::symbol() }}••••••</span>
                        </p>
                    </div>
                    <button @click="show = !show" class="text-text mt-1 shrink-0" aria-label="Toggle balance">
                        <i class="ri-eye-line text-[20px]" x-show="show"></i>
                        <i class="ri-eye-off-line text-[20px]" x-show="!show" x-cloak></i>
                    </button>
                </div>

                <div class="flex items-center gap-2.5">
                    <button type="button" wire:click="openGenerate"
                            class="flex-1 h-[48px] inline-flex items-center justify-center gap-1.5 px-5 rounded-[30px] bg-primary text-surface font-sans font-semibold text-[14px] hover:opacity-95 transition">
                        <i class="ri-add-line text-[17px]"></i> Generate
                    </button>
                    @if (auth()->user()->isSpecialVendor())
                        <a href="{{ route('agent.transfer') }}" wire:navigate
                           class="flex-1 h-[48px] inline-flex items-center justify-center gap-1.5 px-5 rounded-[30px] bg-background text-primary font-sans font-semibold text-[14px] hover:bg-secondary/20 transition">
                            <i class="ri-exchange-funds-line text-[17px]"></i> Transfer
                        </a>
                    @endif
                </div>

                <div class="grid grid-cols-3 gap-2 lg:bg-background lg:rounded-[18px] lg:p-1.5">
                    @foreach ([['total', 'Total', 'text-[#d79937]'], ['active', 'Active', 'text-success'], ['used', 'Used', 'text-danger']] as [$key, $label, $color])
                        <div class="flex flex-col items-center gap-1.5 rounded-[16px] lg:rounded-[14px] py-3 bg-surface lg:bg-transparent shadow-[0_4px_9px_rgba(194,194,194,0.07)] lg:shadow-none">
                            <p class="font-heading font-semibold {{ $color }} text-[22px] leading-none">{{ number_format($stats[$key]) }}</p>
                            <p class="font-sans text-muted text-[12px]">{{ $label }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex flex-col gap-[15px] lg:flex-1 lg:min-w-0 lg:min-h-0 lg:h-full">
                <div class="flex items-center justify-between lg:shrink-0">
                    <p class="font-heading font-medium text-text text-[18px]">My Token Batches</p>
                    <span class="font-sans text-muted text-[12px]">{{ $batches->total() }} batch{{ $batches->total() === 1 ? '' : 'es' }}</span>
                </div>

                <div class="flex items-center gap-2.5 lg:shrink-0">
                    <div class="flex items-center gap-2 h-[42px] flex-1 px-3.5 rounded-[14px] bg-surface shadow-sm">
                        <i class="ri-search-line text-muted text-[17px]"></i>
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search reference…"
                               class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
                        @if ($search !== '')
                            <button type="button" wire:click="$set('search', '')" class="text-muted hover:text-text"><i class="ri-close-line text-[16px]"></i></button>
                        @endif
                    </div>
                    <div class="relative shrink-0">
                        <select wire:model.live="typeFilter"
                                class="appearance-none h-[42px] rounded-[14px] bg-surface shadow-sm pl-3.5 pr-9 font-sans text-[13px] text-text outline-none cursor-pointer">
                            @foreach ($types as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <i class="ri-arrow-down-s-line text-[18px] text-muted absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                    </div>
                </div>

                <div class="flex flex-col gap-3 lg:flex-1 lg:min-h-0 lg:overflow-y-auto lg:pr-1 [scrollbar-width:thin]">
                    @forelse ($batches as $batch)
                        @php $name = $batch->displayName(); @endphp
                        <div class="bg-surface rounded-[18px] px-4 py-4 flex flex-col gap-3 shadow-[0_4px_9px_rgba(194,194,194,0.05)] lg:shrink-0">
                            <div class="flex items-center gap-3">
                                <span class="size-10 rounded-full bg-primary/10 flex items-center justify-center shrink-0"><i class="ri-coupon-3-line text-primary text-[18px]"></i></span>
                                <div class="flex flex-col min-w-0 flex-1">
                                    <span class="font-sans font-semibold text-text text-[14px] truncate">{{ $name }}</span>
                                    <span class="font-sans text-muted text-[12px] truncate">{{ $batch->reference }} · {{ $batch->created_at->format('j M Y, g:i A') }}</span>
                                </div>
                                <div class="flex flex-col items-end shrink-0">
                                    <span class="font-heading font-semibold text-text text-[15px] leading-none">{{ $batch->total_count }}</span>
                                    <span class="font-sans text-muted text-[11px]">codes</span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between border-t border-background pt-3">
                                <div class="flex items-center gap-3 font-sans text-[12px]">
                                    <span class="inline-flex items-center gap-1 text-success"><span class="size-1.5 rounded-full bg-success"></span>{{ $batch->total_count - $batch->used_count }} active</span>
                                    <span class="inline-flex items-center gap-1 text-muted"><span class="size-1.5 rounded-full bg-muted"></span>{{ $batch->used_count }} used</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="$dispatch('open-export-batch', { batchId: '{{ $batch->id }}' })"
                                            class="size-9 rounded-full bg-background flex items-center justify-center text-primary hover:bg-primary/10 transition" title="Export">
                                        <i class="ri-download-2-line text-[16px]"></i>
                                    </button>
                                    <a href="{{ route('agent.batches.show', $batch) }}" wire:navigate
                                       class="h-9 px-4 inline-flex items-center gap-1 rounded-full bg-primary text-surface font-sans font-semibold text-[12px] hover:opacity-95 transition">
                                        View <i class="ri-arrow-right-s-line text-[15px]"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="bg-surface rounded-[20px] py-14 flex flex-col items-center text-center gap-3 shadow-[0_4px_9px_rgba(194,194,194,0.05)]">
                            <span class="size-[88px] rounded-full bg-background flex items-center justify-center text-muted/50"><i class="ri-coupon-3-line text-[40px]"></i></span>
                            <p class="font-heading font-medium text-text text-[16px]">{{ $search !== '' || $typeFilter !== '' ? 'No matching batches' : 'No tokens yet' }}</p>
                            <p class="font-sans text-muted text-[13px] max-w-[260px]">{{ $search !== '' || $typeFilter !== '' ? 'Try a different search or filter.' : 'Generate your first batch of tokens to start reselling Nextel plans.' }}</p>
                            @if ($search === '' && $typeFilter === '')
                                <button type="button" wire:click="openGenerate" class="mt-1 h-[44px] px-5 rounded-[30px] bg-primary text-surface font-sans font-semibold text-[14px] inline-flex items-center gap-1.5 hover:opacity-95 transition">
                                    <i class="ri-add-line"></i> Generate
                                </button>
                            @endif
                        </div>
                    @endforelse
                </div>

                @if ($batches->hasPages())
                    <div class="lg:shrink-0">{{ $batches->links('vendor.pagination.nextel') }}</div>
                @endif
            </div>
        </div>
    </div>

    @if ($generating)
        <div data-lock-scroll class="fixed inset-0 z-[90] flex items-end justify-center sm:items-center sm:p-4"
             x-data="{ shown: false }" x-init="$nextTick(() => shown = true)">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" x-show="shown" x-transition.opacity @click="$wire.closeGenerate()"></div>

            <div class="relative w-full sm:max-w-[420px] bg-background rounded-t-[26px] sm:rounded-[26px] max-h-[92vh] overflow-y-auto px-6 pt-6 pb-8 flex flex-col gap-4"
                 x-show="shown"
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
                        <span wire:loading.remove wire:target="generate" class="flex items-center gap-2"><i class="ri-coupon-3-line"></i> Generate</span>
                        <span wire:loading wire:target="generate" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Generating…</span>
                    </button>
                    <button type="button" wire:click="closeGenerate" class="h-[50px] px-6 rounded-[30px] bg-surface text-text font-sans font-semibold text-[14px] shadow-sm hover:bg-surface/70 transition">Cancel</button>
                </div>
            </div>
        </div>
    @endif

    <livewire:dashboard.agent.export-batch-modal />

    <x-app.bottom-nav />
</div>
