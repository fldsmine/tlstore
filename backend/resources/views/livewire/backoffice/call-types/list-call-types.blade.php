<div>
    <x-backoffice.page title="Call Types" subtitle="Base pricing & claim limits per call type">
        @php $canWrite = $this->canWrite(); @endphp

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($callTypes->isEmpty())
                <div class="p-10 text-center font-sans text-muted text-[14px]">No call types yet.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[880px]">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">Call type</th>
                                <th class="px-5 py-3 font-medium text-right">Rate / min</th>
                                <th class="px-5 py-3 font-medium text-right">Claimable %</th>
                                <th class="px-5 py-3 font-medium text-right">Share price (USD)</th>
                                <th class="px-5 py-3 font-medium text-right">Minutes</th>
                                <th class="px-5 py-3 font-medium text-right">Per batch</th>
                                <th class="px-5 py-3 font-medium text-right">Packages</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($callTypes as $ct)
                                <tr wire:key="ct-{{ $ct->id }}" wire:click="openDetail('{{ $ct->id }}')" class="border-b border-muted/5 last:border-0 hover:bg-background/50 cursor-pointer transition-colors">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <span class="size-9 rounded-full flex items-center justify-center text-white shrink-0" style="background: {{ $ct->color ?: '#18443E' }}"><i class="ri-phone-line text-[15px]"></i></span>
                                            <div class="min-w-0">
                                                <p class="font-sans text-text text-[13px] truncate">{{ $ct->name }}</p>
                                                <p class="font-sans text-muted text-[12px] truncate">{{ $ct->key?->value }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 text-right font-heading text-text text-[13px] whitespace-nowrap">{{ money($ct->rate_per_minute) }}</td>
                                    <td class="px-5 py-3 text-right font-sans text-muted text-[13px] whitespace-nowrap">{{ rtrim(rtrim(number_format((float) $ct->claimable_percentage, 2), '0'), '.') }}%</td>
                                    <td class="px-5 py-3 text-right font-sans text-muted text-[13px] whitespace-nowrap">${{ number_format((float) $ct->price, 2) }}</td>
                                    <td class="px-5 py-3 text-right font-sans text-muted text-[13px] whitespace-nowrap">{{ (int) $ct->min_minutes }}–{{ (int) $ct->max_minutes }}</td>
                                    <td class="px-5 py-3 text-right font-sans text-muted text-[13px] whitespace-nowrap">{{ $ct->min_per_batch }}–{{ $ct->max_per_batch }}</td>
                                    <td class="px-5 py-3 text-right font-sans text-muted text-[13px] whitespace-nowrap">{{ number_format($ct->packages_count) }}</td>
                                    <td class="px-5 py-3">
                                        @if ($ct->is_active)<x-backoffice.badge tone="success">Active</x-backoffice.badge>@else<x-backoffice.badge tone="muted">Inactive</x-backoffice.badge>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-backoffice.card>

        <x-backoffice.modal :show="$showDetail" title="Manage call type" closeAction="$set('showDetail', false)">
            @if ($detailCallType)
                <div class="flex flex-col gap-5">
                    <div class="flex items-center gap-3.5">
                        <span class="size-14 rounded-full flex items-center justify-center text-white shrink-0" style="background: {{ $detailCallType->color ?: '#18443E' }}"><i class="ri-phone-line text-[22px]"></i></span>
                        <div class="min-w-0">
                            <p class="font-heading font-medium text-text text-[16px] truncate">{{ $detailCallType->name }}</p>
                            <p class="font-sans text-muted text-[13px] truncate">{{ $detailCallType->key?->value }} · {{ number_format($detailCallType->packages_count) }} package{{ $detailCallType->packages_count === 1 ? '' : 's' }}</p>
                            <div class="mt-1.5">@if ($detailCallType->is_active)<x-backoffice.badge tone="success">Active</x-backoffice.badge>@else<x-backoffice.badge tone="muted">Inactive</x-backoffice.badge>@endif</div>
                        </div>
                    </div>

                    <div class="bg-background rounded-[16px] divide-y divide-muted/10">
                        @foreach ([
                            ['Base rate / minute', money($detailCallType->rate_per_minute)],
                            ['Claimable percentage', rtrim(rtrim(number_format((float) $detailCallType->claimable_percentage, 2), '0'), '.').'%'],
                            ['Share price region', ucfirst((string) ($detailCallType->region ?: 'nigeria'))],
                            ['Share product price (USD)', '$'.number_format((float) $detailCallType->price, 2)],
                            ['Share agent price (USD)', '$'.number_format((float) $detailCallType->agent_price, 2)],
                            ['Alternate-region price (USD)', $detailCallType->foreign_price === null ? 'Same as primary' : '$'.number_format((float) $detailCallType->foreign_price, 2)],
                            ['Alternate-region agent price (USD)', $detailCallType->foreign_agent_price === null ? 'Same as primary' : '$'.number_format((float) $detailCallType->foreign_agent_price, 2)],
                            ['Monthly share quota', rtrim(rtrim(number_format((float) $detailCallType->monthly_share_minutes, 2), '0'), '.').' minutes'],
                            ['Share term', (int) $detailCallType->share_term_days.' days'],
                            ['Daily share earning cap (USD)', '$'.number_format((float) $detailCallType->max_daily_shareholder_earning, 2)],
                            ['Share purchase prompt threshold', rtrim(rtrim(number_format((float) $detailCallType->share_purchase_prompt_minutes, 2), '0'), '.').' minutes'],
                            ['Minutes per record', (int) $detailCallType->min_minutes.'–'.(int) $detailCallType->max_minutes],
                            ['Records per batch', $detailCallType->min_per_batch.'–'.$detailCallType->max_per_batch],
                            ['Packages using it', number_format($detailCallType->packages_count)],
                        ] as [$label, $value])
                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                <span class="font-sans text-muted text-[13px]">{{ $label }}</span>
                                <span class="font-sans text-text text-[13px] text-right truncate">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>

                    @if ($canWrite)
                        <button type="button" wire:click="edit('{{ $detailCallType->id }}')"
                                class="flex items-center gap-3 rounded-[14px] bg-background px-4 py-3 hover:bg-background/70 transition text-left">
                            <i class="ri-equalizer-line text-primary text-[18px]"></i>
                            <span class="flex-1 font-sans text-text text-[13px]">Edit config</span>
                            <i class="ri-arrow-right-s-line text-muted"></i>
                        </button>
                    @endif
                </div>
            @endif
        </x-backoffice.modal>

        <x-backoffice.modal :show="$showModal" :title="'Edit '.$editingName">
            <form wire:submit="save" class="flex flex-col gap-3.5">
                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Name</label>
                    <input type="text" wire:model="editingName" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                    @error('editingName') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Description</label>
                    <input type="text" wire:model="description" placeholder="Short label for this call type" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                    @error('description') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Badge colour</label>
                    <div class="flex items-center gap-2.5">
                        <input type="color" wire:model="color" class="h-[44px] w-[52px] shrink-0 rounded-[12px] bg-background border border-muted/15 cursor-pointer p-1">
                        <input type="text" wire:model="color" placeholder="#18443E" class="h-[44px] flex-1 rounded-[12px] bg-background border border-muted/15 px-3 font-mono text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                    </div>
                    @error('color') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    @foreach ([['ratePerMinute','Rate / minute ($)'],['claimablePercentage','Claimable %'],['sharePromptMinutes','Share prompt threshold (mins)'],['minMinutes','Min minutes'],['maxMinutes','Max minutes'],['minPerBatch','Min per batch'],['maxPerBatch','Max per batch']] as [$field,$label])
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">{{ $label }}</label>
                            <input type="number" step="any" wire:model="{{ $field }}" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                            @error($field) <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                    @endforeach
                </div>

                <div class="rounded-[16px] bg-background p-4 flex flex-col gap-3">
                    <div class="flex flex-col gap-1">
                        <p class="font-heading font-medium text-text text-[14px]">Standalone share product</p>
                        <p class="font-sans text-muted text-[11px]">Prices are stored in USD, selected by the customer&rsquo;s region, then converted at checkout. The primary region defaults to Nigeria.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label class="font-sans text-muted text-[12px]">Primary region</label>
                            <select wire:model="region" class="h-[44px] rounded-[12px] bg-surface border border-muted/15 px-3 font-sans text-[13px] text-text">
                                <option value="nigeria">Nigeria</option>
                                <option value="foreign">Foreign</option>
                            </select>
                            @error('region') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>
                        @foreach ([['price','Primary price (USD)'],['agentPrice','Primary agent price (USD)'],['foreignPrice','Alternate-region price (USD)'],['foreignAgentPrice','Alternate-region agent price (USD)'],['monthlyShareMinutes','Monthly share minutes'],['shareTermDays','Term days'],['maxDailyShareholderEarning','Daily earning cap (USD)']] as [$field,$label])
                            <div class="flex flex-col gap-1.5">
                                <label class="font-sans text-muted text-[12px]">{{ $label }}</label>
                                <input type="number" step="any" min="0" wire:model="{{ $field }}" class="h-[44px] rounded-[12px] bg-surface border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                                @error($field) <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                            </div>
                        @endforeach
                    </div>
                    <p class="font-sans text-muted text-[11px] mt-2">The monthly quota is prorated over the share term (monthly minutes × term days ÷ 30). When the daily cap is positive, saving raises the quota if needed to make that cap reachable at this call type's rate and claimable percentage.</p>
                </div>
                <x-backoffice.toggle wire:model="isActive" label="Active" />
                <div class="flex justify-end gap-2 mt-1">
                    <button type="button" wire:click="$set('showModal', false)" class="h-[42px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save" class="h-[42px] px-5 rounded-full bg-primary text-white font-sans text-[13px] disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">Save</span>
                        <span wire:loading wire:target="save" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Saving…</span>
                    </button>
                </div>
            </form>
        </x-backoffice.modal>
    </x-backoffice.page>
</div>
