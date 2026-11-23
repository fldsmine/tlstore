<div>
    <x-backoffice.page title="Coupons" subtitle="All tokens across the platform">
        @php $canWrite = $this->canWrite(); @endphp

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[320px]">
                <i class="ri-search-line text-muted text-[17px]"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Code or agent…"
                       class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
            </div>
            <div class="flex items-center gap-1.5 flex-wrap">
                @foreach (['all' => 'All', 'activation' => 'Activation', 'extra_minutes' => 'Extra Minutes', 'cloud' => 'Cloud'] as $key => $label)
                    <button type="button" wire:click="setType('{{ $key }}')" class="px-3 h-[32px] rounded-full font-sans text-[12px] transition {{ $type === $key ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">{{ $label }}</button>
                @endforeach
                <span class="w-px h-5 bg-muted/15 mx-1"></span>
                @foreach (['all' => 'All', 'unused' => 'Unused', 'used' => 'Used'] as $key => $label)
                    <button type="button" wire:click="setStatus('{{ $key }}')" class="px-3 h-[32px] rounded-full font-sans text-[12px] transition {{ $status === $key ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        @if ($canWrite)
            <div class="flex items-center justify-between gap-3 rounded-[14px] bg-surface px-4 py-2.5">
                <span class="font-sans text-[13px] text-muted">{{ count($selected) }} selected</span>
                <div class="flex items-center gap-2">
                    @if (count($selected))
                        <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'deleteSelected', params: [], title: 'Delete coupons', message: 'Delete the selected unused coupons? Redeemed ones are skipped.', confirmText: 'Delete', tone: 'danger' })"
                                class="h-[34px] px-3.5 rounded-full bg-danger/10 text-danger font-sans text-[12px]">Delete selected</button>
                    @endif
                    <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'deleteAllUnused', params: [], title: 'Delete unused coupons', message: 'Delete ALL unused coupons matching the current filters? This cannot be undone.', confirmText: 'Delete', tone: 'danger' })"
                            class="h-[34px] px-3.5 rounded-full bg-[#d79937]/12 text-[#d79937] font-sans text-[12px]">Delete all unused (filtered)</button>
                </div>
            </div>
        @endif

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($coupons->isEmpty())
                <div class="p-10 flex flex-col items-center gap-2.5 text-center">
                    <span class="size-12 rounded-full bg-background flex items-center justify-center text-muted"><i class="ri-coupon-line text-[22px]"></i></span>
                    <p class="font-sans text-muted text-[14px]">No coupons match.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[860px]">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                @if ($canWrite)<th class="px-4 py-3 w-10"><input type="checkbox" wire:model.live="selectPage" class="rounded border-muted/30 text-primary focus:ring-primary/40"></th>@endif
                                <th class="px-4 py-3 font-medium whitespace-nowrap">Code</th>
                                <th class="px-4 py-3 font-medium whitespace-nowrap">Type</th>
                                <th class="px-4 py-3 font-medium">Region</th>
                                <th class="px-4 py-3 font-medium">Agent</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium">Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($coupons as $c)
                                <tr wire:key="cp-{{ $c->id }}" wire:click="openDetail('{{ $c->id }}')" class="border-b border-muted/5 last:border-0 hover:bg-background/50 cursor-pointer transition-colors">
                                    @if ($canWrite)
                                        <td class="px-4 py-3" @click.stop>
                                            @if ($c->is_valid)
                                                <input type="checkbox" value="{{ $c->id }}" wire:model.live="selected" class="rounded border-muted/30 text-primary focus:ring-primary/40">
                                            @else
                                                <span class="block size-4"></span>
                                            @endif
                                        </td>
                                    @endif
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="inline-flex items-center gap-2" x-data="{ copied: false }">
                                            <button type="button" x-on:click.stop="nextelCopy('{{ $c->code }}').then(ok => { if (!ok) return; copied = true; setTimeout(() => { copied = false }, 1200) })" class="text-muted hover:text-primary transition shrink-0" aria-label="Copy code">
                                                <i class="ri-file-copy-line text-[14px]" x-show="!copied"></i>
                                                <i class="ri-check-line text-[14px] text-success" x-show="copied" style="display:none"></i>
                                            </button>
                                            <span class="font-mono text-text text-[13px] tracking-wide">{{ $c->code }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap"><x-backoffice.badge :tone="$c->type->color()">{{ $c->type->getName() }}</x-backoffice.badge></td>
                                    <td class="px-4 py-3"><x-region-badge :region="$c->region" /></td>
                                    <td class="px-4 py-3"><x-backoffice.user-cell :user="$c->agent" /></td>
                                    <td class="px-4 py-3">
                                        @if ($c->is_valid)<x-backoffice.badge tone="success">Active</x-backoffice.badge>@else<x-backoffice.badge tone="danger">Used</x-backoffice.badge>@endif
                                    </td>
                                    <td class="px-4 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $c->created_at?->format('j M Y, g:i A') }}</td>
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

        <x-backoffice.modal :show="$showDetail" title="Coupon details" closeAction="$set('showDetail', false)">
            @if ($detailCoupon)
                @php $cp = $detailCoupon; @endphp
                <div class="flex flex-col gap-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 min-w-0" x-data="{ copied: false }">
                                <button type="button" x-on:click="nextelCopy('{{ $cp->code }}').then(ok => { if (!ok) return; copied = true; setTimeout(() => { copied = false }, 1200) })" class="text-muted hover:text-primary transition shrink-0" aria-label="Copy code">
                                    <i class="ri-file-copy-line text-[16px]" x-show="!copied"></i>
                                    <i class="ri-check-line text-[16px] text-success" x-show="copied" style="display:none"></i>
                                </button>
                                <p class="font-mono text-text text-[18px] tracking-wide break-all">{{ $cp->code }}</p>
                            </div>
                            <div class="mt-1.5 flex items-center gap-1.5 flex-wrap">
                                <x-backoffice.badge :tone="$cp->type->color()">{{ $cp->type->getName() }}</x-backoffice.badge>
                                @if ($cp->is_valid)<x-backoffice.badge tone="success">Active</x-backoffice.badge>@else<x-backoffice.badge tone="danger">Used</x-backoffice.badge>@endif
                            </div>
                        </div>
                        <span class="size-12 rounded-[14px] bg-primary/10 flex items-center justify-center text-primary shrink-0"><i class="ri-coupon-3-line text-[20px]"></i></span>
                    </div>

                    @unless ($cp->is_valid)
                        <div class="rounded-[16px] bg-background p-1.5">
                            <p class="px-3 pt-2 pb-1 font-sans text-muted text-[11px] uppercase tracking-wide">Redeemed by</p>
                            @if ($cp->redeemer)
                                <a href="{{ route('backoffice.users.show', $cp->redeemer) }}" wire:navigate class="flex items-center gap-3 rounded-[12px] px-3 py-2.5 hover:bg-surface transition">
                                    <div class="flex-1 min-w-0"><x-backoffice.user-cell :user="$cp->redeemer" /></div>
                                    <i class="ri-arrow-right-up-line text-muted text-[15px] shrink-0"></i>
                                </a>
                            @else
                                <p class="px-3 py-2.5 font-sans text-muted text-[13px]">User no longer exists</p>
                            @endif
                        </div>
                    @endunless

                    <div class="bg-background rounded-[16px] divide-y divide-muted/10">
                        <div class="flex items-center justify-between gap-3 px-4 py-3">
                            <span class="font-sans text-muted text-[13px]">Region</span>
                            <span class="text-right"><x-region-badge :region="$cp->region" /></span>
                        </div>
                        @foreach (array_values(array_filter([
                            ['Issued by', $cp->agent?->username ? '@'.$cp->agent->username : '—', $cp->agent ? route('backoffice.users.show', $cp->agent) : null],
                            ['Batch', $cp->batch?->reference ?? '—', $cp->batch ? route('backoffice.batches.show', $cp->batch) : null],
                            ['Grants', $cp->displayName(), null],
                            $cp->price !== null ? ['Price', money($cp->price), null] : null,
                            ['Created', $cp->created_at?->format('j M Y, g:i A'), null],
                            ! $cp->is_valid ? ['Redeemed at', $cp->used_at?->format('j M Y, g:i A'), null] : null,
                        ])) as [$label, $value, $href])
                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                <span class="font-sans text-muted text-[13px]">{{ $label }}</span>
                                @if ($href)
                                    <a href="{{ $href }}" wire:navigate class="font-sans text-primary text-[13px] text-right truncate hover:underline">{{ $value }}</a>
                                @else
                                    <span class="font-sans text-text text-[13px] text-right truncate">{{ $value }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if ($canWrite && $cp->is_valid)
                        <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'deleteCoupon', params: ['{{ $cp->id }}'], title: 'Delete coupon', message: 'Delete this unused coupon?', confirmText: 'Delete', tone: 'danger' })"
                                class="h-[44px] rounded-[14px] bg-danger/10 text-danger font-sans text-[13px] flex items-center justify-center gap-2 hover:bg-danger/15 transition">
                            <i class="ri-delete-bin-6-line"></i> Delete coupon
                        </button>
                    @endif
                </div>
            @endif
        </x-backoffice.modal>
    </x-backoffice.page>
</div>
