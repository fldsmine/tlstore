<div>
    <x-backoffice.page title="Transactions" subtitle="Ledger projection — read-only. Money movement happens only through the ledger and wallet actions.">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[380px]">
                <i class="ri-search-line text-muted text-[17px]"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search reference, username or email…"
                       class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
                @if ($search !== '')
                    <button type="button" wire:click="$set('search', '')" class="text-muted hover:text-text"><i class="ri-close-line text-[16px]"></i></button>
                @endif
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <input type="date" wire:model.live="from" class="input !w-[150px] !h-[40px] text-[12px]" aria-label="From date">
                <span class="text-muted text-[12px]">to</span>
                <input type="date" wire:model.live="to" class="input !w-[150px] !h-[40px] text-[12px]" aria-label="To date">
                <select wire:model.live="direction" class="input !w-[120px] !h-[40px] text-[12px]" aria-label="Direction">
                    <option value="all">All directions</option>
                    <option value="credit">Credit</option>
                    <option value="debit">Debit</option>
                </select>
                <select wire:model.live="status" class="input !w-[130px] !h-[40px] text-[12px]" aria-label="Status">
                    <option value="all">All statuses</option>
                    <option value="completed">Completed</option>
                    <option value="pending">Pending</option>
                    <option value="failed">Failed</option>
                </select>
            </div>
        </div>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($transactions->isEmpty())
                <div class="p-10 flex flex-col items-center gap-2.5 text-center">
                    <span class="size-12 rounded-full bg-background flex items-center justify-center text-muted"><i class="ri-exchange-dollar-line text-[22px]"></i></span>
                    <p class="font-sans text-muted text-[14px]">No transactions match these filters.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-muted/10">
                                <th class="th">ID</th>
                                <th class="th">User</th>
                                <th class="th">Type</th>
                                <th class="th">Dir.</th>
                                <th class="th-right">Amount</th>
                                <th class="th-right">Fee</th>
                                <th class="th">Wallet</th>
                                <th class="th">Status</th>
                                <th class="th">Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transactions as $t)
                                <tr wire:key="txn-{{ $t->id }}" class="tr">
                                    <td class="td text-muted tabular-nums">{{ $t->id }}</td>
                                    <td class="td">
                                        @if ($t->user)
                                            <a href="{{ route('backoffice.users.show', $t->user) }}" wire:navigate class="hover:underline">{{ '@'.$t->user->username }}</a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="td">{{ $t->type->getName() }}</td>
                                    <td class="td"><x-backoffice.badge :tone="$t->direction === 'credit' ? 'success' : 'muted'">{{ $t->direction }}</x-backoffice.badge></td>
                                    <td class="td text-right tabular-nums {{ $t->direction === 'credit' ? 'text-success' : 'text-text' }}">{{ number_format((float) $t->amount, 2) }}</td>
                                    <td class="td text-right tabular-nums text-muted">{{ number_format((float) $t->fee, 2) }}</td>
                                    <td class="td text-muted">{{ $t->wallet }}</td>
                                    <td class="td"><x-backoffice.badge :tone="match ($t->status) { 'completed' => 'success', 'pending' => 'warning', 'failed' => 'danger', default => 'muted' }">{{ \Illuminate\Support\Str::headline($t->status) }}</x-backoffice.badge></td>
                                    <td class="td text-muted whitespace-nowrap">{{ $t->created_at?->format('j M, H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-4">
                    {{ $transactions->links('vendor.pagination.backoffice') }}
                </div>
            @endif
        </x-backoffice.card>
        <p class="font-sans text-[12px] text-muted">Amounts on this projection are decimal units as stored by the app. Canonical money lives in the ledger (integer minor units).</p>
    </x-backoffice.page>
</div>
