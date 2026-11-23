<div>
    <x-backoffice.page title="Token Batches" subtitle="Coupon batches issued by agents">
        @php $canWrite = $this->canWrite(); @endphp

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[360px]">
                <i class="ri-search-line text-muted text-[17px]"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Reference or agent…"
                       class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
            </div>
            <div class="flex items-center gap-1.5 flex-wrap">
                <button type="button" wire:click="setType('all')" class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $type === 'all' ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">All</button>
                @foreach ($types as $t)
                    <button type="button" wire:click="setType('{{ $t->value }}')" class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $type === $t->value ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">{{ $t->getName() }}</button>
                @endforeach
            </div>
        </div>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($batches->isEmpty())
                <div class="p-10 flex flex-col items-center gap-2.5 text-center">
                    <span class="size-12 rounded-full bg-background flex items-center justify-center text-muted"><i class="ri-stack-line text-[22px]"></i></span>
                    <p class="font-sans text-muted text-[14px]">No batches found.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[860px]">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">Batch</th>
                                <th class="px-5 py-3 font-medium">Agent</th>
                                <th class="px-5 py-3 font-medium">Type</th>
                                <th class="px-5 py-3 font-medium">Region</th>
                                <th class="px-5 py-3 font-medium text-right">Used / Total</th>
                                <th class="px-5 py-3 font-medium">Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($batches as $b)
                                <tr wire:key="b-{{ $b->id }}" wire:click="openDetail('{{ $b->id }}')" class="border-b border-muted/5 last:border-0 hover:bg-background/50 cursor-pointer transition-colors">
                                    <td class="px-5 py-3">
                                        <a href="{{ route('backoffice.batches.show', $b) }}" wire:navigate @click.stop class="font-sans text-primary text-[13px]">{{ $b->reference }}</a>
                                        <p class="font-sans text-muted text-[12px]">{{ $b->package?->name ?? $b->callType?->name ?? $b->additionalMinutePlan?->name ?? $b->cloudStoragePlan?->name ?? '—' }}</p>
                                    </td>
                                    <td class="px-5 py-3"><x-backoffice.user-cell :user="$b->agent" /></td>
                                    <td class="px-5 py-3"><x-backoffice.badge :tone="$b->type->color()">{{ $b->type->getName() }}</x-backoffice.badge></td>
                                    <td class="px-5 py-3"><x-region-badge :region="$b->region" /></td>
                                    <td class="px-5 py-3 font-sans text-muted text-[13px] text-right">{{ $b->used_coupons_count }} / {{ $b->coupons_count }}</td>
                                    <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $b->created_at?->format('j M Y, g:i A') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-backoffice.card>

        @if ($batches->hasPages())
            <div>{{ $batches->links('vendor.pagination.nextel') }}</div>
        @endif

        <x-backoffice.modal :show="$showDetail" title="Manage batch" closeAction="$set('showDetail', false)">
            @if ($detailBatch)
                <div class="flex flex-col gap-5">
                    <div class="flex items-center gap-3.5">
                        <span class="size-14 rounded-[14px] bg-primary/10 flex items-center justify-center text-primary shrink-0"><i class="ri-stack-line text-[22px]"></i></span>
                        <div class="min-w-0">
                            <p class="font-mono text-text text-[15px] truncate">{{ $detailBatch->reference }}</p>
                            <p class="font-sans text-muted text-[13px] truncate">{{ $detailBatch->package?->name ?? $detailBatch->callType?->name ?? $detailBatch->additionalMinutePlan?->name ?? $detailBatch->cloudStoragePlan?->name ?? '—' }} · {{ $detailBatch->used_coupons_count }}/{{ $detailBatch->coupons_count }} used</p>
                            <div class="mt-1.5">
                                <x-backoffice.badge :tone="$detailBatch->type->color()">{{ $detailBatch->type->getName() }}</x-backoffice.badge>
                            </div>
                        </div>
                    </div>

                    @if ($detailBatch->agent)
                        <div class="rounded-[16px] bg-background p-1.5">
                            <p class="px-3 pt-2 pb-1 font-sans text-muted text-[11px] uppercase tracking-wide">Issued by</p>
                            <a href="{{ route('backoffice.users.show', $detailBatch->agent) }}" wire:navigate class="flex items-center gap-3 rounded-[12px] px-3 py-2.5 hover:bg-surface transition">
                                <div class="flex-1 min-w-0"><x-backoffice.user-cell :user="$detailBatch->agent" /></div>
                                <i class="ri-arrow-right-up-line text-muted text-[15px] shrink-0"></i>
                            </a>
                        </div>
                    @endif

                    <div class="flex flex-col gap-2">
                        <a href="{{ route('backoffice.batches.show', $detailBatch) }}" wire:navigate
                           class="flex items-center gap-3 rounded-[14px] bg-background px-4 py-3 hover:bg-background/70 transition text-left">
                            <i class="ri-eye-line text-primary text-[18px]"></i>
                            <span class="flex-1 font-sans text-text text-[13px]">View tokens</span>
                            <i class="ri-arrow-right-s-line text-muted"></i>
                        </a>
                        @if ($detailBatch->used_coupons_count > 0)
                            <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'deleteUnusedInBatch', params: ['{{ $detailBatch->id }}'], title: 'Delete unused tokens', message: 'Delete all unused tokens in this batch? Redeemed tokens are kept.', confirmText: 'Delete', tone: 'danger' })"
                                    class="flex items-center gap-3 rounded-[14px] bg-[#d79937]/10 px-4 py-3 hover:bg-[#d79937]/15 transition text-left">
                                <i class="ri-eraser-line text-[#d79937] text-[18px]"></i>
                                <span class="flex-1 font-sans text-[#d79937] text-[13px]">Delete unused tokens</span>
                                <i class="ri-arrow-right-s-line text-[#d79937]/60"></i>
                            </button>
                        @else
                            <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'deleteBatch', params: ['{{ $detailBatch->id }}'], title: 'Delete batch', message: 'Delete this batch and all its tokens? This cannot be undone.', confirmText: 'Delete', tone: 'danger' })"
                                    class="flex items-center gap-3 rounded-[14px] bg-danger/10 px-4 py-3 hover:bg-danger/15 transition text-left">
                                <i class="ri-delete-bin-6-line text-danger text-[18px]"></i>
                                <span class="flex-1 font-sans text-danger text-[13px]">Delete batch</span>
                                <i class="ri-arrow-right-s-line text-danger/60"></i>
                            </button>
                        @endif
                    </div>
                </div>
            @endif
        </x-backoffice.modal>
    </x-backoffice.page>
</div>
