<div>
    <x-backoffice.page title="Payment Sheets" subtitle="Generated bank payout exports — re-download in any processor format">
        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($sheets->isEmpty())
                <div class="p-10 flex flex-col items-center gap-2.5 text-center">
                    <span class="size-12 rounded-full bg-background flex items-center justify-center text-muted"><i class="ri-file-list-3-line text-[22px]"></i></span>
                    <p class="font-sans text-muted text-[14px]">No payment sheets yet. Generate one from the Withdrawals screen.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[640px]">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium min-w-[180px]">Sheet</th>
                                <th class="px-5 py-3 font-medium">Processor</th>
                                <th class="px-5 py-3 font-medium text-right">Payouts</th>
                                <th class="px-5 py-3 font-medium text-right">Total</th>
                                <th class="px-5 py-3 font-medium">Generated</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sheets as $sheet)
                                <tr wire:key="ps-{{ $sheet->id }}" wire:click="openDetail('{{ $sheet->id }}')" class="border-b border-muted/5 last:border-0 hover:bg-background/50 cursor-pointer transition-colors">
                                    <td class="px-5 py-3 min-w-[180px]">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <span class="size-9 rounded-[10px] bg-primary/10 text-primary flex items-center justify-center shrink-0"><i class="ri-file-list-3-line text-[17px]"></i></span>
                                            <div class="min-w-0">
                                                <p class="font-heading text-text text-[13px] leading-tight">#{{ sprintf('%03d', $sheet->sequence_number) }}</p>
                                                <p class="font-sans text-muted text-[12px] leading-tight">{{ $sheet->currency ?: 'NGN' }} payout sheet</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap"><x-backoffice.badge tone="info">{{ $processors[$sheet->processor] ?? $sheet->processor }}</x-backoffice.badge></td>
                                    <td class="px-5 py-3 text-right font-sans text-muted text-[13px]">{{ count($sheet->withdrawal_ids ?? []) }}</td>
                                    <td class="px-5 py-3 text-right whitespace-nowrap">
                                        <div class="inline-flex flex-col items-end gap-0.5">
                                            @foreach (($sheet->currency === 'MIXED' ? $sheet->currencyTotals() : [($sheet->currency ?: 'NGN') => (float) $sheet->total_amount]) as $cur => $amt)
                                                <span class="inline-flex items-center justify-end gap-1 font-heading text-text text-[13px]"><span class="font-sans text-muted text-[11px]">{{ $cur }}</span><x-figure :value="(float) $amt" /></span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $sheet->created_at?->format('j M Y, g:i A') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-backoffice.card>

        @if ($sheets->hasPages())<div>{{ $sheets->links('vendor.pagination.nextel') }}</div>@endif

        <x-backoffice.modal :show="$showDetail" title="Payment sheet" closeAction="$set('showDetail', false)">
            @if ($detailSheet)
                @php $selectCls = 'h-[46px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40 w-full'; @endphp
                <div class="flex flex-col gap-5">
                    <div class="flex items-center gap-3.5">
                        <span class="size-12 rounded-[12px] bg-primary/10 text-primary flex items-center justify-center shrink-0"><i class="ri-file-list-3-line text-[20px]"></i></span>
                        <div class="min-w-0">
                            <p class="font-heading font-medium text-text text-[16px]">Sheet #{{ sprintf('%03d', $detailSheet->sequence_number) }}</p>
                            <p class="font-sans text-muted text-[13px]">{{ $processors[$detailSheet->processor] ?? $detailSheet->processor }} · {{ count($detailSheet->withdrawal_ids ?? []) }} payout(s)</p>
                        </div>
                    </div>

                    <div class="bg-background rounded-[16px] divide-y divide-muted/10">
                        @foreach ([
                            ['Original processor', $processors[$detailSheet->processor] ?? $detailSheet->processor],
                            ['Payouts', count($detailSheet->withdrawal_ids ?? [])],
                            ['Total amount', $detailSheet->formattedTotals()],
                            ['Currency', $detailSheet->currency === 'MIXED' ? implode(', ', array_keys($detailSheet->currencyTotals())) : ($detailSheet->currency ?: 'NGN')],
                            ['Generated', $detailSheet->created_at?->format('j M Y, g:i A')],
                        ] as [$label, $value])
                            <div class="flex items-start justify-between gap-3 px-4 py-3">
                                <span class="font-sans text-muted text-[13px] shrink-0">{{ $label }}</span>
                                <span class="font-sans text-text text-[13px] text-right break-all">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>

                    @if ($this->canWrite())
                        <div class="rounded-[16px] border border-muted/15 p-4 flex flex-col gap-3">
                            <p class="font-sans text-muted text-[12px]">Re-download — pick a processor, then a format.</p>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="flex flex-col gap-1.5">
                                    <label class="font-sans text-muted text-[12px]">Processor</label>
                                    <select wire:model="reProcessor" class="{{ $selectCls }}">@foreach ($processors as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <label class="font-sans text-muted text-[12px]">Format</label>
                                    <select wire:model="reFormat" class="{{ $selectCls }}">@foreach ($formats as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
                                </div>
                            </div>
                            <button type="button" wire:click="download" wire:loading.attr="disabled" wire:target="download"
                                    class="h-[44px] rounded-full bg-primary text-white font-sans text-[13px] flex items-center justify-center gap-2 disabled:opacity-60">
                                <span wire:loading.remove wire:target="download" class="flex items-center gap-2"><i class="ri-download-2-line"></i> Download sheet</span>
                                <span wire:loading wire:target="download" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Preparing…</span>
                            </button>
                        </div>
                    @endif
                </div>
            @endif
        </x-backoffice.modal>
    </x-backoffice.page>
</div>
