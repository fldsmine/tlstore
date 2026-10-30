<div>
    <x-backoffice.page :title="$batch->reference" :subtitle="$batch->displayName().' batch'">
        @php
            $canWrite = $this->canWrite();
            $total = (int) $batch->coupons_count;
            $used = (int) $batch->used_coupons_count;
            $active = $total - $used;
            $pct = $total > 0 ? (int) round($used / $total * 100) : 0;
        @endphp

        <x-slot:action>
            <div class="relative" x-data="{ open: false }">
                <button type="button" x-on:click="open = !open" aria-label="Batch actions"
                        class="inline-flex items-center justify-center gap-1.5 h-[44px] w-[44px] sm:w-auto sm:px-4 rounded-[14px] bg-surface text-text shadow-sm hover:bg-surface/70 transition">
                    <i class="ri-more-2-fill text-[20px] sm:text-[16px]"></i><span class="hidden sm:inline font-sans text-[13px]">Actions</span>
                </button>
                <div x-show="open" x-on:click.outside="open = false" x-transition.origin.top.right style="display:none"
                     class="absolute right-0 top-[50px] z-30 w-52 rounded-[14px] bg-surface border border-muted/10 shadow-[0_12px_40px_rgba(0,0,0,0.16)] p-1.5 flex flex-col">
                    <button type="button" x-on:click="open = false; $dispatch('open-export-batch', { batchId: '{{ $batch->id }}' })" class="flex items-center gap-2.5 px-3 py-2 rounded-[10px] hover:bg-background text-left font-sans text-text text-[13px]"><i class="ri-download-2-line text-muted text-[15px]"></i> Export tokens</button>
                    @if ($canWrite)
                        <span class="h-px bg-muted/10 my-1"></span>
                        @if ($used > 0)
                            <button type="button" x-on:click="open = false; $dispatch('confirm-action', { id: $wire.id, method: 'deleteUnused', params: [], title: 'Delete unused tokens', message: 'Delete all unused tokens in this batch? Redeemed tokens are kept.', confirmText: 'Delete', tone: 'danger' })" class="flex items-center gap-2.5 px-3 py-2 rounded-[10px] hover:bg-[#d79937]/10 text-left font-sans text-[#d79937] text-[13px]"><i class="ri-eraser-line text-[15px]"></i> Delete unused tokens</button>
                        @else
                            <button type="button" x-on:click="open = false; $dispatch('confirm-action', { id: $wire.id, method: 'deleteBatch', params: [], title: 'Delete batch', message: 'Delete this entire batch and its tokens? This cannot be undone.', confirmText: 'Delete', tone: 'danger' })" class="flex items-center gap-2.5 px-3 py-2 rounded-[10px] hover:bg-danger/10 text-left font-sans text-danger text-[13px]"><i class="ri-delete-bin-6-line text-[15px]"></i> Delete batch</button>
                        @endif
                    @endif
                </div>
            </div>
        </x-slot:action>

        <x-backoffice.card>
            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                <span class="size-14 rounded-[14px] bg-primary/10 flex items-center justify-center text-primary shrink-0"><i class="ri-stack-line text-[24px]"></i></span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <p class="font-mono text-text text-[15px] break-all">{{ $batch->reference }}</p>
                        <x-backoffice.badge :tone="$batch->type->color()">{{ $batch->type->getName() }}</x-backoffice.badge>
                        <x-region-badge :region="$batch->region" />
                    </div>
                    <p class="font-sans text-muted text-[13px] mt-1">{{ $batch->displayName() }} · Created {{ $batch->created_at?->format('j M Y, g:i A') }}</p>
                </div>
                @if ($batch->agent)
                    <a href="{{ route('backoffice.users.show', $batch->agent) }}" wire:navigate class="flex items-center gap-3 rounded-[14px] bg-background px-3.5 py-2.5 hover:bg-background/70 transition shrink-0">
                        <div class="min-w-0"><x-backoffice.user-cell :user="$batch->agent" label="Issued by" /></div>
                        <i class="ri-arrow-right-up-line text-muted text-[15px] shrink-0"></i>
                    </a>
                @endif
            </div>
        </x-backoffice.card>

        <div class="grid grid-cols-3 gap-3">
            <x-backoffice.card class="!p-4">
                <p class="font-sans text-muted text-[12px]">Total tokens</p>
                <p class="font-heading text-text text-[20px] mt-1">{{ number_format($total) }}</p>
            </x-backoffice.card>
            <x-backoffice.card class="!p-4">
                <p class="font-sans text-muted text-[12px]">Active</p>
                <p class="font-heading text-success text-[20px] mt-1">{{ number_format($active) }}</p>
            </x-backoffice.card>
            <x-backoffice.card class="!p-4">
                <div class="flex items-baseline justify-between gap-2">
                    <p class="font-sans text-muted text-[12px]">Redeemed</p>
                    <span class="font-sans text-muted text-[11px]">{{ $pct }}%</span>
                </div>
                <p class="font-heading text-text text-[20px] mt-1">{{ number_format($used) }}</p>
                <div class="mt-2 h-1.5 rounded-full bg-muted/15 overflow-hidden"><div class="h-full rounded-full bg-primary" style="width: {{ $pct }}%"></div></div>
            </x-backoffice.card>
        </div>

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[320px]">
                <i class="ri-search-line text-muted text-[17px]"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search token code…"
                       class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
            </div>
            <div class="flex items-center gap-1.5 flex-wrap">
                @foreach (['all' => 'All', 'active' => 'Active', 'used' => 'Used'] as $key => $label)
                    <button type="button" wire:click="setScope('{{ $key }}')" class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $scope === $key ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        @if ($canWrite && count($selected))
            <div class="flex items-center justify-between gap-3 rounded-[14px] bg-primary/5 border border-primary/15 px-4 py-2.5">
                <span class="font-sans text-text text-[13px]">{{ count($selected) }} token{{ count($selected) === 1 ? '' : 's' }} selected</span>
                <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'deleteSelected', params: [], title: 'Delete selected tokens', message: 'Delete the selected unused tokens? Redeemed tokens are skipped.', confirmText: 'Delete', tone: 'danger' })" class="h-[34px] px-3.5 rounded-full bg-danger/10 text-danger font-sans text-[12px] inline-flex items-center gap-1.5 hover:bg-danger/15 transition"><i class="ri-delete-bin-6-line"></i> Delete selected</button>
            </div>
        @endif

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($coupons->isEmpty())
                <div class="p-10 flex flex-col items-center gap-2.5 text-center">
                    <span class="size-12 rounded-full bg-background flex items-center justify-center text-muted"><i class="ri-coupon-line text-[22px]"></i></span>
                    <p class="font-sans text-muted text-[14px]">No tokens{{ ($scope !== 'all' || trim($search) !== '') ? ' match these filters' : ' yet' }}.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[720px]">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                @if ($canWrite)<th class="px-5 py-3 w-10"><input type="checkbox" wire:model.live="selectPage" class="rounded border-muted/30 text-primary focus:ring-primary/40" aria-label="Select page"></th>@endif
                                <th class="px-5 py-3 font-medium whitespace-nowrap">Code</th>
                                <th class="px-5 py-3 font-medium">Region</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 font-medium">Redeemed by</th>
                                <th class="px-5 py-3 font-medium">Created</th>
                                @if ($canWrite)<th class="px-5 py-3 font-medium text-right">Actions</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($coupons as $c)
                                <tr wire:key="c-{{ $c->id }}" class="border-b border-muted/5 last:border-0">
                                    @if ($canWrite)
                                        <td class="px-5 py-3">
                                            @if ($c->is_valid)
                                                <input type="checkbox" value="{{ $c->id }}" wire:model.live="selected" class="rounded border-muted/30 text-primary focus:ring-primary/40">
                                            @else
                                                <span class="block size-4"></span>
                                            @endif
                                        </td>
                                    @endif
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        <div class="inline-flex items-center gap-2" x-data="{ copied: false }">
                                            <button type="button" x-on:click="nextelCopy('{{ $c->code }}').then(ok => { if (!ok) return; copied = true; setTimeout(() => { copied = false }, 1200) })" class="text-muted hover:text-primary transition shrink-0" aria-label="Copy code">
                                                <i class="ri-file-copy-line text-[14px]" x-show="!copied"></i>
                                                <i class="ri-check-line text-[14px] text-success" x-show="copied" style="display:none"></i>
                                            </button>
                                            <span class="font-mono text-text text-[13px] tracking-wide">{{ $c->code }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3"><x-region-badge :region="$c->region" /></td>
                                    <td class="px-5 py-3">
                                        @if ($c->is_valid)<x-backoffice.badge tone="success">Active</x-backoffice.badge>@else<x-backoffice.badge tone="danger">Used</x-backoffice.badge>@endif
                                    </td>
                                    <td class="px-5 py-3">
                                        @if ($c->redeemer)
                                            <a href="{{ route('backoffice.users.show', $c->redeemer) }}" wire:navigate class="inline-flex hover:opacity-80 transition"><x-backoffice.user-cell :user="$c->redeemer" /></a>
                                        @else
                                            <span class="font-sans text-muted text-[13px]">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $c->created_at?->format('j M Y, g:i A') }}</td>
                                    @if ($canWrite)
                                        <td class="px-5 py-3">
                                            <div class="flex items-center justify-end">
                                                @if ($c->is_valid)
                                                    <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'deleteCoupon', params: ['{{ $c->id }}'], title: 'Delete token', message: 'Delete this unused token?', confirmText: 'Delete', tone: 'danger' })" class="size-8 rounded-full bg-danger/10 text-danger hover:bg-danger/15 inline-flex items-center justify-center transition" aria-label="Delete token"><i class="ri-delete-bin-6-line text-[14px]"></i></button>
                                                @else
                                                    <span class="font-sans text-muted text-[12px]">—</span>
                                                @endif
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-backoffice.card>

        @if ($coupons->hasPages())
            <div>{{ $coupons->links('vendor.pagination.nextel') }}</div>
        @endif

        <livewire:backoffice.coupons.export-batch-modal />
    </x-backoffice.page>
</div>
