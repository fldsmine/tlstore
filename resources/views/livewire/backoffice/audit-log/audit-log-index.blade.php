<div>
    <x-backoffice.page title="Audit log" subtitle="Every privileged action, newest first">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[340px]">
                <i class="ri-search-line text-muted text-[17px]"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Filter by action…"
                       class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
                @if ($search !== '')
                    <button type="button" wire:click="$set('search', '')" class="text-muted hover:text-text"><i class="ri-close-line text-[16px]"></i></button>
                @endif
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <input type="text" wire:model.live="entity" class="input !w-[150px] !h-[40px] text-[12px]" placeholder="Entity type…" aria-label="Entity type">
                <select wire:model.live="admin" class="input !w-[190px] !h-[40px] text-[12px]" aria-label="Actor">
                    <option value="all">All actors</option>
                    @foreach ($admins as $a)
                        <option value="{{ $a->id }}">{{ $a->full_name }}</option>
                    @endforeach
                </select>
                <input type="date" wire:model.live="from" class="input !w-[150px] !h-[40px] text-[12px]" aria-label="From">
                <input type="date" wire:model.live="to" class="input !w-[150px] !h-[40px] text-[12px]" aria-label="To">
            </div>
        </div>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($logs->isEmpty())
                <div class="p-10 flex flex-col items-center gap-2.5 text-center">
                    <span class="size-12 rounded-full bg-background flex items-center justify-center text-muted"><i class="ri-file-list-3-line text-[22px]"></i></span>
                    <p class="font-sans text-muted text-[14px]">No audit entries match these filters.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-muted/10">
                                <th class="th">When</th>
                                <th class="th">Actor</th>
                                <th class="th">Action</th>
                                <th class="th">Entity</th>
                                <th class="th">Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($logs as $log)
                                @php
                                    $detail = $log->after_values ?? $log->before_values;
                                    $detailText = is_array($detail) ? json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $detail;
                                    $detailText = \Illuminate\Support\Str::limit($detailText, 180);
                                @endphp
                                <tr wire:key="log-{{ $log->id }}" class="tr align-top">
                                    <td class="td text-muted whitespace-nowrap">{{ optional($log->created_at)->format('j M, H:i:s') }}</td>
                                    <td class="td whitespace-nowrap">{{ $log->adminActor?->full_name ?? 'User/system' }}</td>
                                    <td class="td"><code class="font-mono text-[11px] bg-background rounded px-1.5 py-0.5">{{ $log->action }}</code></td>
                                    <td class="td text-muted">{{ $log->entity_type }}#{{ $log->entity_id }}</td>
                                    <td class="td"><code class="font-mono text-[11px] text-muted break-all">{{ $detailText }}</code></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-4">
                    {{ $logs->links('vendor.pagination.backoffice') }}
                </div>
            @endif
        </x-backoffice.card>
    </x-backoffice.page>
</div>
