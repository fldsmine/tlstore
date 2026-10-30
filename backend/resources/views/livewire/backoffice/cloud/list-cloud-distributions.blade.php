<div>
    <x-backoffice.page title="Bonus Distributions" subtitle="Promoter & shareholder bonuses you've sent over Cloud contacts">
        <div class="flex items-center gap-2 flex-wrap mb-4">
            <div class="relative flex-1 min-w-[200px]">
                <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-muted text-[16px]"></i>
                <input type="text" wire:model.live.debounce.400ms="search" placeholder="Search by contact…"
                       class="h-[44px] w-full rounded-[12px] bg-surface border border-muted/15 pl-9 pr-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
            </div>
            <select wire:model.live="type" class="h-[44px] rounded-[12px] bg-surface border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                <option value="">All types</option>
                <option value="promoter">Promoter</option>
                <option value="shareholder">Shareholder</option>
            </select>
        </div>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($distributions->isEmpty())
                <div class="p-10 text-center font-sans text-muted text-[14px]">No distributions yet.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[820px]">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">Contact</th>
                                <th class="px-5 py-3 font-medium">Type</th>
                                <th class="px-5 py-3 font-medium text-right">Amount</th>
                                <th class="px-5 py-3 font-medium text-right">Savers</th>
                                <th class="px-5 py-3 font-medium text-right">Claimed</th>
                                <th class="px-5 py-3 font-medium">Note</th>
                                <th class="px-5 py-3 font-medium">When</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($distributions as $d)
                                <tr wire:key="dist-{{ $d->id }}" class="border-b border-muted/5 last:border-0">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2.5">
                                            @if ($d->avatar_url)
                                                <img src="{{ $d->avatar_url }}" alt="" class="size-8 rounded-[9px] object-cover bg-background shrink-0">
                                            @else
                                                <span class="size-8 rounded-[9px] bg-primary/10 flex items-center justify-center text-primary shrink-0 font-heading text-[12px]">{{ strtoupper(substr($d->sender_name, 0, 1)) }}</span>
                                            @endif
                                            <span class="font-sans text-text text-[13px] truncate max-w-[180px]">{{ $d->sender_name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3">
                                        @if ($d->is_shareholder)<x-backoffice.badge tone="warning">Shareholder</x-backoffice.badge>@else<x-backoffice.badge tone="success">Promoter</x-backoffice.badge>@endif
                                    </td>
                                    <td class="px-5 py-3 text-right font-heading text-text text-[13px] tabular-nums whitespace-nowrap">{{ money($d->amount) }}</td>
                                    <td class="px-5 py-3 text-right font-sans text-muted text-[13px] tabular-nums whitespace-nowrap">{{ number_format($d->recipients_count) }}</td>
                                    <td class="px-5 py-3 text-right font-sans text-[13px] tabular-nums whitespace-nowrap">
                                        <span class="text-text">{{ number_format($d->claims_count) }}</span><span class="text-muted"> · {{ money($d->claims_count * (float) $d->amount) }}</span>
                                    </td>
                                    <td class="px-5 py-3 font-sans text-muted text-[12px] truncate max-w-[220px]">{{ $d->note ?: '—' }}</td>
                                    <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $d->created_at?->format('j M Y, g:i A') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-backoffice.card>

        @if ($distributions->hasPages())<div class="mt-4">{{ $distributions->links('vendor.pagination.nextel') }}</div>@endif
    </x-backoffice.page>
</div>
