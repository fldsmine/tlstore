<div>
    <x-backoffice.page title="Payment references" subtitle="Single-use ledger — a settled payment can credit exactly one entitlement">
        @php $input = 'h-[42px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40'; @endphp

        <div class="grid sm:grid-cols-3 gap-4">
            <x-backoffice.stat label="Settled today" :value="number_format($settledToday)" icon="ri-shield-check-line" />
            <x-backoffice.stat label="Settled all time" :value="number_format($settledAllTime)" icon="ri-database-2-line" />
            <x-backoffice.stat label="Charges flagged for review" :value="number_format($flaggedCharges)" icon="ri-flag-2-line" :tone="$flaggedCharges > 0 ? 'warning' : 'primary'" hint="never credited" />
        </div>

        <x-backoffice.card>
            <p class="font-sans text-muted text-[12px] -mt-1 mb-4 leading-relaxed">A row appears here the moment a gateway charge is verified and reconciled, <span class="text-text">before</span> any entitlement is granted. A reference — or a provider transaction — that already has a row is refused at settlement and flagged for review instead of being credited again.</p>
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1 min-w-0">
                    <input type="search" wire:model.live.debounce.350ms="search" placeholder="Search reference, gateway reference or transaction id…" class="{{ $input }} w-full">
                </div>
                <div class="flex gap-3">
                    <select wire:model.live="typeFilter" class="{{ $input }}">
                        <option value="">All types</option>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="perPage" class="{{ $input }}">
                        @foreach ([10, 25, 50, 100] as $value)
                            <option value="{{ $value }}">{{ $value }} / page</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-backoffice.card>

        <x-backoffice.card title="Settled references">
            @if ($references->isEmpty())
                <p class="font-sans text-muted text-[13px]">No settled references match this view yet.</p>
            @else
                <div class="overflow-x-auto -mx-5 lg:-mx-6 px-5 lg:px-6">
                    <table class="w-full min-w-[900px] text-left">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-2 py-3 font-medium">Reference</th>
                                <th class="px-2 py-3 font-medium">Type</th>
                                <th class="px-2 py-3 font-medium">User</th>
                                <th class="px-2 py-3 font-medium text-right">Amount</th>
                                <th class="px-2 py-3 font-medium">Provider</th>
                                <th class="px-2 py-3 font-medium">Settled</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($references as $entry)
                                <tr wire:key="ref-{{ $entry->id }}" class="border-b border-muted/5 last:border-0">
                                    <td class="px-2 py-3">
                                        <p class="font-sans text-text text-[13px] tabular-nums">{{ $entry->reference }}</p>
                                        @if ($entry->gateway_reference)
                                            <p class="font-sans text-muted text-[11px]">gateway {{ $entry->gateway_reference }}</p>
                                        @endif
                                    </td>
                                    <td class="px-2 py-3">
                                        @php $label = $types[$entry->type] ?? \Illuminate\Support\Str::headline((string) $entry->type); @endphp
                                        <span class="font-sans text-text text-[12px]">{{ $label }}</span>
                                    </td>
                                    <td class="px-2 py-3">
                                        @if ($entry->user)
                                            <p class="font-sans text-text text-[13px]">{{ $entry->user->username ? '@'.$entry->user->username : $entry->user->name }}</p>
                                            <p class="font-sans text-muted text-[11px]">{{ $entry->user->email }}</p>
                                        @else
                                            <span class="font-sans text-muted text-[12px]">—</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-3 text-right whitespace-nowrap">
                                        <span class="font-heading text-text text-[13px]">{{ number_format((float) $entry->amount_ngn, 2) }}</span>
                                        <span class="block font-sans text-muted text-[11px]">{{ $entry->currency ?: 'NGN' }}</span>
                                    </td>
                                    <td class="px-2 py-3">
                                        <span class="font-sans text-text text-[12px]">{{ ucfirst((string) $entry->provider) }}</span>
                                    </td>
                                    <td class="px-2 py-3">
                                        <p class="font-sans text-text text-[12px]">{{ $entry->settled_at?->format('j M Y, g:i A') }}</p>
                                        <p class="font-sans text-muted text-[11px]">{{ $entry->settled_at?->diffForHumans() }}</p>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">{{ $references->links() }}</div>
            @endif
        </x-backoffice.card>
    </x-backoffice.page>
</div>
