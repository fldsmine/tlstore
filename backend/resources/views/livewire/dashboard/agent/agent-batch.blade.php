<div class="min-h-screen lg:h-screen lg:overflow-hidden bg-background">
    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-6 lg:h-full flex flex-col gap-[22px] lg:gap-5">

        <x-app.app-bar title="Token Batch" />

        @if (! $batch)
            <div class="flex flex-col items-center text-center gap-3 mt-16">
                <span class="size-16 rounded-full bg-muted/15 flex items-center justify-center"><i class="ri-search-line text-muted text-[30px]"></i></span>
                <p class="font-heading font-medium text-text text-[20px]">Batch not found</p>
                <a href="{{ route('agent.dashboard') }}" wire:navigate class="mt-2 h-[46px] px-6 rounded-full bg-primary text-surface inline-flex items-center gap-2 font-sans font-semibold text-[14px]"><i class="ri-arrow-left-line"></i> Back to Vendor</a>
            </div>
        @else
            <div class="flex flex-col gap-6 lg:flex-row lg:gap-6 lg:flex-1 lg:min-h-0">

                <div class="flex flex-col gap-4 lg:w-[38%] lg:max-w-[400px] lg:shrink-0 lg:self-start lg:bg-surface lg:rounded-[24px] lg:p-6 lg:shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                    <div class="bg-surface rounded-[20px] p-5 flex flex-col gap-4 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)] lg:bg-transparent lg:rounded-none lg:p-0 lg:shadow-none">
                        <div class="flex items-center gap-3">
                            <span class="size-11 rounded-full bg-primary/10 flex items-center justify-center shrink-0"><i class="ri-coupon-3-line text-primary text-[20px]"></i></span>
                            <div class="flex flex-col min-w-0 flex-1">
                                <span class="font-heading font-medium text-text text-[17px] truncate">{{ $planName }}</span>
                                <span class="font-sans text-muted text-[12px] truncate">{{ $batch->reference }}</span>
                            </div>
                            <x-region-badge :region="$batch->region" class="shrink-0" />
                        </div>
                        <p class="font-sans text-muted text-[12px] -mt-1">Created {{ $batch->created_at->format('d M, Y · H:i') }}</p>

                        <div class="grid grid-cols-3 gap-3">
                            @foreach ([['total', 'Total', 'text-text'], ['active', 'Active', 'text-success'], ['used', 'Used', 'text-danger']] as [$key, $label, $color])
                                <div class="bg-background rounded-[14px] py-3 flex flex-col items-center gap-1">
                                    <span class="font-heading font-semibold {{ $color }} text-[22px] leading-none">{{ number_format($stats[$key]) }}</span>
                                    <span class="font-sans text-muted text-[12px]">{{ $label }}</span>
                                </div>
                            @endforeach
                        </div>

                        <button type="button" @click="$dispatch('open-export-batch', { batchId: '{{ $batch->id }}' })"
                                class="h-[46px] inline-flex items-center justify-center gap-1.5 rounded-[30px] bg-primary text-surface font-sans font-semibold text-[14px] hover:opacity-95 transition">
                            <i class="ri-download-2-line text-[16px]"></i> Export
                        </button>
                    </div>
                </div>

                <div class="flex flex-col gap-[15px] lg:flex-1 lg:min-w-0 lg:min-h-0 lg:h-full">
                    <div class="flex items-center justify-between lg:shrink-0">
                        <p class="font-heading font-medium text-text text-[18px]">Tokens</p>
                        <span class="font-sans text-muted text-[12px]">{{ $coupons->total() }} total</span>
                    </div>

                    <div class="flex items-center gap-2.5 lg:shrink-0">
                        <div class="flex items-center gap-2 h-[42px] flex-1 px-3.5 rounded-[14px] bg-surface shadow-sm">
                            <i class="ri-search-line text-muted text-[17px]"></i>
                            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search code…"
                                   class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
                            @if ($search !== '')
                                <button type="button" wire:click="$set('search', '')" class="text-muted hover:text-text"><i class="ri-close-line text-[16px]"></i></button>
                            @endif
                        </div>
                        <div class="relative shrink-0">
                            <select wire:model.live="statusFilter"
                                    class="appearance-none h-[42px] rounded-[14px] bg-surface shadow-sm pl-3.5 pr-9 font-sans text-[13px] text-text outline-none cursor-pointer">
                                <option value="">All</option>
                                <option value="active">Active</option>
                                <option value="used">Used</option>
                            </select>
                            <i class="ri-arrow-down-s-line text-[18px] text-muted absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="bg-surface rounded-[20px] shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)] overflow-hidden lg:flex-1 lg:min-h-0 lg:flex lg:flex-col">
                        <div class="overflow-auto lg:flex-1 [scrollbar-width:thin]">
                            <table class="w-full text-left">
                                <thead class="lg:sticky lg:top-0 lg:z-10">
                                    <tr class="bg-background/95 font-sans text-muted text-[12px]">
                                        <th class="py-3 pl-4 pr-2 w-10">S/N</th>
                                        <th class="py-3 px-2">Token</th>
                                        <th class="py-3 px-2">Region</th>
                                        <th class="py-3 px-2">Status</th>
                                        <th class="py-3 pr-4 pl-2 text-right">Details</th>
                                    </tr>
                                </thead>
                                <tbody class="font-sans text-[13px]">
                                    @forelse ($coupons as $i => $coupon)
                                        <tr class="border-t border-background" wire:key="coupon-{{ $coupon->id }}">
                                            <td class="py-3 pl-4 pr-2 text-muted">{{ $coupons->firstItem() + $i }}</td>
                                            <td class="py-3 px-2">
                                                <button type="button" class="inline-flex items-center gap-1.5 text-text font-medium hover:text-primary transition group"
                                                        x-data="{ copied: false }"
                                                        @click="nextelCopy('{{ $coupon->code }}').then(ok => { if (!ok) return; copied = true; setTimeout(() => { copied = false }, 1200) })"
                                                        title="Copy code">
                                                    <span class="tracking-wide">{{ $coupon->code }}</span>
                                                    <i class="text-[14px] text-muted group-hover:text-primary" :class="copied ? 'ri-check-line text-success' : 'ri-file-copy-line'"></i>
                                                </button>
                                            </td>
                                            <td class="py-3 px-2"><x-region-badge :region="$coupon->region" /></td>
                                            <td class="py-3 px-2">
                                                @if ($coupon->used_at)
                                                    <span class="inline-flex items-center gap-1 text-muted"><span class="size-1.5 rounded-full bg-muted"></span>Used</span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 text-success font-medium"><span class="size-1.5 rounded-full bg-success"></span>Active</span>
                                                @endif
                                            </td>
                                            <td class="py-3 pr-4 pl-2 text-right">
                                                <button type="button" wire:click="viewToken('{{ $coupon->id }}')" class="text-primary hover:opacity-80" title="View details">
                                                    <i class="ri-eye-line text-[18px]"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="py-10 text-center font-sans text-muted text-[13px]">No tokens match your search.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if ($coupons->hasPages())
                        <div class="lg:shrink-0">{{ $coupons->links('vendor.pagination.nextel') }}</div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    @if ($viewingCoupon)
        <div data-lock-scroll class="fixed inset-0 z-[90] flex items-end justify-center sm:items-center sm:p-4"
             x-data="{ shown: false }" x-init="$nextTick(() => shown = true)">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" x-show="shown" x-transition.opacity @click="$wire.closeToken()"></div>

            <div class="relative w-full sm:max-w-[400px] bg-background rounded-t-[26px] sm:rounded-[26px] max-h-[92vh] overflow-y-auto px-6 pt-6 pb-8 flex flex-col gap-4"
                 x-show="shown"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">

                <button type="button" wire:click="closeToken" class="absolute right-5 top-5 size-9 rounded-full bg-surface shadow-sm flex items-center justify-center text-text" aria-label="Close">
                    <i class="ri-close-line text-[20px]"></i>
                </button>

                <div class="flex flex-col items-center text-center gap-2 mt-1">
                    <span class="size-14 rounded-full bg-primary/10 flex items-center justify-center"><i class="ri-coupon-3-line text-primary text-[28px]"></i></span>
                    <p class="font-sans text-muted text-[12px]">Token Code</p>
                    <button type="button" class="inline-flex items-center gap-2 font-heading font-semibold text-text text-[20px] tracking-wide hover:text-primary transition"
                            x-data="{ copied: false }"
                            @click="nextelCopy('{{ $viewingCoupon->code }}').then(ok => { if (!ok) return; copied = true; setTimeout(() => { copied = false }, 1200) })">
                        <span>{{ $viewingCoupon->code }}</span>
                        <i class="text-[16px]" :class="copied ? 'ri-check-line text-success' : 'ri-file-copy-line text-muted'"></i>
                    </button>
                    @if ($viewingCoupon->used_at)
                        <span class="inline-flex items-center gap-1.5 bg-muted/15 rounded-full px-3 py-1 font-sans text-[12px] font-medium text-muted"><span class="size-1.5 rounded-full bg-muted"></span>Used</span>
                    @else
                        <span class="inline-flex items-center gap-1.5 bg-success/15 rounded-full px-3 py-1 font-sans text-[12px] font-medium text-success"><span class="size-1.5 rounded-full bg-success"></span>Active</span>
                    @endif
                </div>

                <div class="bg-surface rounded-[16px] p-4 flex flex-col gap-3 font-sans text-[13px]">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-muted">Region</span>
                        <span class="text-right"><x-region-badge :region="$viewingCoupon->region" /></span>
                    </div>
                    @foreach (array_filter([
                        ['Type', $viewingCoupon->type->getName()],
                        ['Plan', $viewingCoupon->package?->name ?? $viewingCoupon->callType?->name ?? $viewingCoupon->additionalMinutePlan?->name ?? $viewingCoupon->cloudStoragePlan?->name ?? '—'],
                        ['Value', money((float) $viewingCoupon->price)],
                        ['Generated', $viewingCoupon->created_at->format('d M, Y · H:i')],
                        $viewingCoupon->used_at ? ['Redeemed', $viewingCoupon->used_at->format('d M, Y · H:i')] : null,
                        $viewingCoupon->redeemer ? ['Redeemed by', $viewingCoupon->redeemer->full_name ?: '—'] : null,
                        $viewingCoupon->redeemer ? ['Username', '@'.$viewingCoupon->redeemer->username] : null,
                        $viewingCoupon->redeemer ? ['Phone', $viewingCoupon->redeemer->phone ?: '—'] : null,
                    ]) as [$label, $value])
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted">{{ $label }}</span>
                            <span class="text-text font-medium text-right">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <livewire:dashboard.agent.export-batch-modal />

    <x-app.bottom-nav />
</div>
