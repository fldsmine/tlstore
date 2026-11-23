{{-- Shared backoffice UI for a payment manager's wallet history. Included with $manager ("Korapay")
     and, for providers that don't support a page size, $showLimit = false. --}}
<div>
    <x-backoffice.page :title="$manager.' balance history'" subtitle="Every credit and debit that moved the merchant wallet">
        @php $input = 'h-[42px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40'; @endphp

        <x-backoffice.card>
            <div class="grid sm:grid-cols-2 {{ $showLimit ? 'xl:grid-cols-5' : 'xl:grid-cols-4' }} gap-3">
                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Currency</label>
                    <select wire:model.live="currency" class="{{ $input }} w-full">
                        @foreach ($currencies as $code)
                            <option value="{{ $code }}">{{ $code }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Direction</label>
                    <select wire:model.live="direction" class="{{ $input }} w-full">
                        <option value="">All movements</option>
                        <option value="credit">Credit (money in)</option>
                        <option value="debit">Debit (money out)</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">From</label>
                    <input type="date" wire:model="dateFrom" class="{{ $input }} w-full">
                    @error('dateFrom') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">To</label>
                    <input type="date" wire:model="dateTo" class="{{ $input }} w-full">
                    @error('dateTo') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                </div>
                @if ($showLimit)
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Per page</label>
                        <select wire:model.live="limit" class="{{ $input }} w-full">
                            @foreach ($limits as $value)
                                <option value="{{ $value }}">{{ $value }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
            <div class="flex items-center gap-2 mt-4">
                <button type="button" wire:click="applyFilters" wire:loading.attr="disabled" wire:target="applyFilters,currency,direction,limit"
                        class="h-[38px] px-4 rounded-full bg-primary text-white font-sans text-[13px] inline-flex items-center gap-2 disabled:opacity-60">
                    <i wire:loading.remove wire:target="applyFilters,currency,direction,limit" class="ri-filter-3-line text-[16px]"></i>
                    <i wire:loading wire:target="applyFilters,currency,direction,limit" class="ri-loader-4-line animate-spin text-[16px]"></i>
                    Apply filters
                </button>
                <button type="button" wire:click="clearFilters" class="h-[38px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Reset</button>
            </div>
        </x-backoffice.card>

        <x-backoffice.card title="Movements">
            @if ($error)
                <div class="flex items-start gap-3">
                    <span class="size-10 rounded-[12px] bg-danger/12 text-danger flex items-center justify-center shrink-0"><i class="ri-error-warning-line text-[20px]"></i></span>
                    <div class="flex-1 min-w-0">
                        <p class="font-heading font-medium text-text text-[15px]">History unavailable</p>
                        <p class="font-sans text-muted text-[13px]">{{ $error }}</p>
                    </div>
                </div>
            @elseif ($entries === [])
                <p class="font-sans text-muted text-[13px]">No movements match these filters.</p>
            @else
                <div class="overflow-x-auto -mx-5 lg:-mx-6 px-5 lg:px-6">
                    <table class="w-full min-w-[880px] text-left">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-2 py-3 font-medium">Date</th>
                                <th class="px-2 py-3 font-medium">Description</th>
                                <th class="px-2 py-3 font-medium">Source</th>
                                <th class="px-2 py-3 font-medium text-right">Amount</th>
                                <th class="px-2 py-3 font-medium text-right">Balance after</th>
                                <th class="px-2 py-3 font-medium">Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entries as $entry)
                                <tr wire:key="entry-{{ $entry['pointer'] }}-{{ $loop->index }}" class="border-b border-muted/5 last:border-0">
                                    <td class="px-2 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $entry['date_created'] ?: '—' }}</td>
                                    <td class="px-2 py-3">
                                        <p class="font-sans text-text text-[13px]">{{ $entry['description'] ?: '—' }}</p>
                                    </td>
                                    <td class="px-2 py-3">
                                        <span class="font-sans text-muted text-[12px]">{{ $entry['source'] ?: '—' }}</span>
                                    </td>
                                    <td class="px-2 py-3 text-right whitespace-nowrap">
                                        <span class="font-heading text-[13px] {{ $entry['direction'] === 'credit' ? 'text-success' : 'text-danger' }}">
                                            {{ $entry['direction'] === 'credit' ? '+' : '−' }}{{ number_format($entry['amount'], 2) }}
                                        </span>
                                        <span class="block font-sans text-muted text-[11px]">{{ $entry['currency'] }}</span>
                                    </td>
                                    <td class="px-2 py-3 text-right font-sans text-text text-[13px] tabular-nums whitespace-nowrap">
                                        {{ $entry['balance_after'] !== null ? number_format($entry['balance_after'], 2) : '—' }}
                                        <span class="block font-sans text-muted text-[11px]">{{ $entry['balance_after'] !== null ? 'from '.number_format($entry['balance_before'], 2) : 'balance not reported' }}</span>
                                    </td>
                                    <td class="px-2 py-3">
                                        <p class="font-sans text-text text-[12px]">{{ $entry['source_reference'] ?: '—' }}</p>
                                        <p class="font-sans text-muted text-[11px]">{{ $entry['pointer'] ?: '—' }}</p>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between gap-3 mt-4">
                    <p class="font-sans text-muted text-[12px]">Showing {{ count($entries) }} movement{{ count($entries) === 1 ? '' : 's' }} · page {{ $cursorIndex + 1 }}</p>
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="previousPage" @disabled($cursorIndex === 0)
                                class="h-[36px] px-3.5 rounded-full bg-background text-muted font-sans text-[13px] disabled:opacity-40">Previous</button>
                        <button type="button" wire:click="nextPage" @disabled(! $hasMore)
                                class="h-[36px] px-3.5 rounded-full bg-background text-text font-sans text-[13px] disabled:opacity-40">Next</button>
                    </div>
                </div>
            @endif
        </x-backoffice.card>
    </x-backoffice.page>
</div>
