<div>
    <x-backoffice.page title="Call Logs" subtitle="The shared daily network call pool — shareholders claim a share of each call">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[320px]">
                <i class="ri-search-line text-muted text-[17px]"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search counterparty number…"
                       class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
            </div>
            <div class="flex items-center gap-2">
                <select wire:model.live="direction" class="h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm font-sans text-[13px] text-text outline-none border-0">
                    <option value="all">All directions</option>
                    <option value="incoming">Incoming</option>
                    <option value="outgoing">Outgoing</option>
                </select>
                <select wire:model.live="callType" class="h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm font-sans text-[13px] text-text outline-none border-0">
                    <option value="all">All call types</option>
                    @foreach ($callTypes as $ct)
                        <option value="{{ $ct->id }}">{{ $ct->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($records->isEmpty())
                <div class="p-10 text-center font-sans text-muted text-[14px]">No call records in the pool right now — it is regenerated daily.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[820px]">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">Call</th>
                                <th class="px-5 py-3 font-medium">Call type</th>
                                <th class="px-5 py-3 font-medium text-right">Duration</th>
                                <th class="px-5 py-3 font-medium text-right">Rate</th>
                                <th class="px-5 py-3 font-medium text-right">Gross</th>
                                <th class="px-5 py-3 font-medium text-right">Claims</th>
                                <th class="px-5 py-3 font-medium">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($records as $r)
                                @php $net = \App\Models\AllocatedNumber::networkFor($r->counterparty) ?? 'Nextel'; @endphp
                                <tr wire:key="cr-{{ $r->id }}" wire:click="openDetail('{{ $r->id }}')" class="border-b border-muted/5 last:border-0 hover:bg-background/50 cursor-pointer transition-colors">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <span class="size-9 rounded-full bg-background overflow-hidden flex items-center justify-center shrink-0 ring-1 ring-black/[0.04]">
                                                <img src="{{ \App\Models\AllocatedNumber::logoFor($net) }}" alt="{{ $net }}" class="size-full object-cover">
                                            </span>
                                            <div class="min-w-0">
                                                <p class="font-mono text-text text-[13px] leading-tight whitespace-nowrap">{{ $r->counterparty }}</p>
                                                <p class="font-sans text-muted text-[12px] leading-tight">{{ $net }} · {{ ucfirst($r->direction) }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <span class="size-2.5 rounded-full shrink-0" style="background-color: {{ $r->callType?->color ?? '#18443E' }}"></span>
                                            <span class="font-sans text-text text-[13px]">{{ $r->callType?->name ?? '—' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 text-right font-sans text-text text-[13px] whitespace-nowrap">{{ rtrim(rtrim(number_format((float) $r->duration_minutes, 2), '0'), '.') }} min</td>
                                    <td class="px-5 py-3 text-right font-sans text-muted text-[13px] whitespace-nowrap">{{ money($r->rate_per_minute) }}</td>
                                    <td class="px-5 py-3 text-right font-heading text-text text-[13px] whitespace-nowrap">{{ money($r->earnings) }}</td>
                                    <td class="px-5 py-3 text-right font-sans text-muted text-[13px] whitespace-nowrap">{{ $r->claims_count }}</td>
                                    <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $r->created_at?->format('j M Y, g:i A') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-backoffice.card>

        @if ($records->hasPages())<div>{{ $records->links('vendor.pagination.nextel') }}</div>@endif

        <x-backoffice.modal :show="$showDetail" title="Call details" closeAction="$set('showDetail', false)">
            @if ($detailRecord)
                @php $net = \App\Models\AllocatedNumber::networkFor($detailRecord->counterparty) ?? 'Nextel'; @endphp
                <div class="flex flex-col gap-5">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="size-11 rounded-full bg-background overflow-hidden flex items-center justify-center shrink-0 ring-1 ring-black/[0.04]">
                                <img src="{{ \App\Models\AllocatedNumber::logoFor($net) }}" alt="{{ $net }}" class="size-full object-cover">
                            </span>
                            <div class="min-w-0">
                                <p class="font-mono text-text text-[14px] leading-tight">{{ $detailRecord->counterparty }}</p>
                                <p class="font-sans text-muted text-[12px] leading-tight">{{ $net }} · {{ ucfirst($detailRecord->direction) }}</p>
                            </div>
                        </div>
                        <p class="font-heading font-semibold text-text text-[18px] whitespace-nowrap">{{ money($detailRecord->earnings) }}</p>
                    </div>

                    <div class="bg-background rounded-[16px] divide-y divide-muted/10">
                        @foreach (array_filter([
                            ['Call type', $detailRecord->callType?->name ?? '—'],
                            ['Direction', ucfirst($detailRecord->direction)],
                            ['Duration', rtrim(rtrim(number_format((float) $detailRecord->duration_minutes, 2), '0'), '.').' min'],
                            ['Rate / min', money($detailRecord->rate_per_minute)],
                            ['Gross earnings', money($detailRecord->earnings)],
                            ['Times claimed', (string) $detailRecord->claims_count],
                            ['Occurred', $detailRecord->created_at?->format('j M Y, g:i A') ?? '—'],
                        ]) as [$label, $value])
                            <div class="flex items-start justify-between gap-3 px-4 py-3">
                                <span class="font-sans text-muted text-[13px] shrink-0">{{ $label }}</span>
                                <span class="font-sans text-text text-[13px] text-right break-all">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-backoffice.modal>
    </x-backoffice.page>
</div>
