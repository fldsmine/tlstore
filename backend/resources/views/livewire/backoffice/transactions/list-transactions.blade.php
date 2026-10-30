<div>
    <x-backoffice.page title="Transactions" subtitle="Full ledger">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[320px]">
                <i class="ri-search-line text-muted text-[17px]"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Reference, description, user…"
                       class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
            </div>
            <div class="flex items-center gap-1.5 flex-wrap">
                @foreach (['all' => 'All', 'credit' => 'Credits', 'debit' => 'Debits'] as $key => $label)
                    <button type="button" wire:click="setDirection('{{ $key }}')" class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $direction === $key ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">{{ $label }}</button>
                @endforeach
                <select wire:model.live="type" class="h-[34px] rounded-full bg-surface border-0 px-3 font-sans text-[12px] text-muted focus:ring-2 focus:ring-primary/40">
                    <option value="all">All types</option>
                    @foreach ($types as $t)<option value="{{ $t->value }}">{{ $t->getName() }}</option>@endforeach
                </select>
                <select wire:model.live="status" class="h-[34px] rounded-full bg-surface border-0 px-3 font-sans text-[12px] text-muted focus:ring-2 focus:ring-primary/40">
                    <option value="all">All statuses</option>
                    <option value="completed">Completed</option>
                    <option value="pending">Pending</option>
                    <option value="failed">Failed</option>
                </select>
            </div>
        </div>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($transactions->isEmpty())
                <div class="p-10 text-center font-sans text-muted text-[14px]">No transactions match.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[860px]">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">Type / Reference</th>
                                <th class="px-5 py-3 font-medium">User</th>
                                <th class="px-5 py-3 font-medium">Wallet</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 font-medium text-right">Amount</th>
                                <th class="px-5 py-3 font-medium">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transactions as $tx)
                                <tr wire:key="tx-{{ $tx->id }}" wire:click="openDetail('{{ $tx->id }}')" class="border-b border-muted/5 last:border-0 hover:bg-background/50 cursor-pointer transition-colors">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            @if ($tx->network_logo)
                                                <img src="{{ $tx->network_logo }}" alt="" class="size-9 rounded-full object-cover shrink-0">
                                            @else
                                                <span class="size-9 rounded-full flex items-center justify-center shrink-0 {{ $tx->is_credit ? 'bg-success/15 text-success' : 'bg-danger/15 text-danger' }}"><i class="{{ $tx->type->icon() }} text-[17px]"></i></span>
                                            @endif
                                            <div class="min-w-0">
                                                <p class="font-sans text-text text-[13px] truncate">{{ $tx->type->getName() }}</p>
                                                <p class="font-sans text-muted text-[12px] truncate">{{ $tx->reference }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3">
                                        @if ($tx->user)
                                            <a href="{{ route('backoffice.users.show', $tx->user) }}" wire:navigate @click.stop class="block hover:opacity-80 transition"><x-backoffice.user-cell :user="$tx->user" /></a>
                                        @else
                                            <span class="font-sans text-muted text-[13px]">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 font-sans text-muted text-[12px]">{{ $tx->wallet ? (\App\Models\User::WALLET_LABELS[$tx->wallet] ?? $tx->wallet) : '—' }}</td>
                                    <td class="px-5 py-3">
                                        <x-backoffice.badge :tone="$tx->status === 'completed' ? 'success' : ($tx->status === 'pending' ? 'warning' : 'danger')">{{ ucfirst($tx->status) }}</x-backoffice.badge>
                                    </td>
                                    <td class="px-5 py-3 text-right font-heading text-[13px] {{ $tx->is_credit ? 'text-success' : 'text-danger' }} whitespace-nowrap">{{ $tx->signed_amount }}</td>
                                    <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $tx->created_at?->format('j M Y, g:i A') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-backoffice.card>

        @if ($transactions->hasPages())<div>{{ $transactions->links('vendor.pagination.nextel') }}</div>@endif

        <x-backoffice.modal :show="$showDetail" title="Transaction details" closeAction="$set('showDetail', false)">
            @if ($detailTx)
                @php $tx = $detailTx; @endphp
                <div class="flex flex-col gap-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-heading font-semibold text-[24px] leading-none {{ $tx->is_credit ? 'text-success' : 'text-danger' }}">{{ $tx->signed_amount }}</p>
                            <p class="mt-1.5 font-sans text-text text-[14px] truncate">{{ $tx->type->getName() }}</p>
                            <div class="mt-1.5 flex items-center gap-1.5 flex-wrap">
                                <x-backoffice.badge :tone="$tx->is_credit ? 'success' : 'muted'">{{ ucfirst($tx->direction) }}</x-backoffice.badge>
                                <x-backoffice.badge :tone="$tx->status === 'completed' ? 'success' : ($tx->status === 'pending' ? 'warning' : 'danger')">{{ ucfirst($tx->status) }}</x-backoffice.badge>
                            </div>
                        </div>
                        @if ($tx->network_logo)
                            <img src="{{ $tx->network_logo }}" alt="" class="size-12 rounded-[14px] object-cover shrink-0">
                        @else
                            <span class="size-12 rounded-[14px] flex items-center justify-center shrink-0 {{ $tx->is_credit ? 'bg-success/15 text-success' : 'bg-danger/15 text-danger' }}"><i class="{{ $tx->type->icon() }} text-[20px]"></i></span>
                        @endif
                    </div>

                    @if ($tx->user)
                        <div class="rounded-[16px] bg-background p-1.5">
                            <p class="px-3 pt-2 pb-1 font-sans text-muted text-[11px] uppercase tracking-wide">Account</p>
                            <a href="{{ route('backoffice.users.show', $tx->user) }}" wire:navigate class="flex items-center gap-3 rounded-[12px] px-3 py-2.5 hover:bg-surface transition">
                                <div class="flex-1 min-w-0"><x-backoffice.user-cell :user="$tx->user" /></div>
                                <i class="ri-arrow-right-up-line text-muted text-[15px] shrink-0"></i>
                            </a>
                        </div>
                    @endif

                    <div class="bg-background rounded-[16px] divide-y divide-muted/10">
                        @foreach (array_values(array_filter([
                            ['Reference', $tx->reference, true],
                            ['Amount', money($tx->amount), false],
                            (float) $tx->fee > 0 ? ['Fee', '−'.money($tx->fee), false] : null,
                            ['Net', money($tx->net_amount), false],
                            ['Wallet', $tx->wallet ? (\App\Models\User::WALLET_LABELS[$tx->wallet] ?? $tx->wallet) : '—', false],
                            ['Description', $tx->description ?: '—', false],
                            ['Date', $tx->created_at?->format('j M Y, g:i A') ?? '—', false],
                        ])) as [$label, $value, $mono])
                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                <span class="font-sans text-muted text-[13px]">{{ $label }}</span>
                                <span class="text-text text-[13px] text-right truncate {{ $mono ? 'font-mono' : 'font-sans' }}">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>

                    @if (is_array($tx->meta) && collect($tx->meta)->filter(fn ($v) => ! is_array($v) && ! is_null($v))->isNotEmpty())
                        <div>
                            <p class="font-sans text-muted text-[12px] mb-2">More details</p>
                            <div class="bg-background rounded-[16px] divide-y divide-muted/10">
                                @foreach ($tx->meta as $metaKey => $metaValue)
                                    @if (! is_array($metaValue) && ! is_null($metaValue) && $metaKey !== 'net')
                                        <div class="flex items-center justify-between gap-3 px-4 py-3">
                                            <span class="font-sans text-muted text-[13px]">{{ ucfirst(str_replace('_', ' ', $metaKey)) }}</span>
                                            <span class="font-sans text-text text-[13px] text-right truncate">{{ $metaValue }}</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </x-backoffice.modal>
    </x-backoffice.page>
</div>
