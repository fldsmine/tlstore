<div>
    <x-backoffice.page title="Product orders" subtitle="Guest eSIM sales from the product page — what was paid, and what still has to be sent">
        @php $input = 'h-[42px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40'; @endphp

        <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <x-backoffice.stat label="Awaiting fulfilment" :value="number_format($awaitingFulfilment)" icon="ri-mail-send-line" :tone="$awaitingFulfilment > 0 ? 'warning' : 'primary'" hint="paid, not sent yet" />
            <x-backoffice.stat label="Paid today" :value="number_format($paidToday)" icon="ri-shopping-bag-3-line" />
            <x-backoffice.stat label="Revenue today" :value="'₦'.number_format($revenueToday, 2)" icon="ri-money-dollar-circle-line" />
            <x-backoffice.stat label="Revenue all time" :value="'₦'.number_format($revenueAllTime, 2)" icon="ri-line-chart-line" />
        </div>

        <x-backoffice.card>
            <p class="font-sans text-muted text-[12px] -mt-1 mb-4 leading-relaxed">
                An order is created the moment a buyer starts a checkout. It is marked <span class="text-text">paid</span> only after the gateway
                has been re-verified server-side, the amount reconciled and the payment reference claimed exactly once — and it leaves this queue
                only when someone has actually emailed the buyer their eSIM.
            </p>

            <div class="flex flex-col xl:flex-row gap-3">
                <div class="flex-1 min-w-0">
                    <input type="search" wire:model.live.debounce.350ms="search" placeholder="Search reference, name, email, phone or gateway reference…" class="{{ $input }} w-full">
                </div>
                <div class="flex flex-wrap gap-3">
                    <select wire:model.live="queueFilter" class="{{ $input }}">
                        <option value="">All orders</option>
                        <option value="to_send">To send</option>
                        <option value="sent">Fulfilled</option>
                    </select>
                    <select wire:model.live="statusFilter" class="{{ $input }}">
                        <option value="">All statuses</option>
                        <option value="paid">Paid</option>
                        <option value="pending">Awaiting payment</option>
                        <option value="failed">Failed</option>
                    </select>
                    <select wire:model.live="perPage" class="{{ $input }}">
                        @foreach ([10, 25, 50, 100] as $value)
                            <option value="{{ $value }}">{{ $value }} / page</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-backoffice.card>

        <x-backoffice.card title="Orders">
            @if ($orders->isEmpty())
                <p class="font-sans text-muted text-[13px]">No orders match this view yet.</p>
            @else
                <div class="overflow-x-auto -mx-5 lg:-mx-6 px-5 lg:px-6">
                    <table class="w-full min-w-[980px] text-left">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-2 py-3 font-medium">Order</th>
                                <th class="px-2 py-3 font-medium">Buyer</th>
                                <th class="px-2 py-3 font-medium">Product</th>
                                <th class="px-2 py-3 font-medium text-right">Amount</th>
                                <th class="px-2 py-3 font-medium">Status</th>
                                <th class="px-2 py-3 font-medium">Paid</th>
                                <th class="px-2 py-3 font-medium text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $order)
                                <tr wire:key="order-{{ $order->id }}" class="border-b border-muted/5 last:border-0">
                                    <td class="px-2 py-3">
                                        <p class="font-sans text-text text-[13px] tabular-nums">{{ $order->reference }}</p>
                                        @if ($order->gateway_reference && $order->gateway_reference !== $order->reference)
                                            <p class="font-sans text-muted text-[11px]">gateway {{ $order->gateway_reference }}</p>
                                        @endif
                                        <p class="font-sans text-muted text-[11px]">{{ $order->created_at?->format('j M Y, g:i A') }}</p>
                                    </td>
                                    <td class="px-2 py-3">
                                        <p class="font-sans text-text text-[13px]">{{ $order->full_name }}</p>
                                        <p class="font-sans text-muted text-[11px]">{{ $order->email }}</p>
                                        <p class="font-sans text-muted text-[11px] tabular-nums">{{ $order->phone }}</p>
                                    </td>
                                    <td class="px-2 py-3">
                                        <span class="font-sans text-text text-[12px]">{{ $order->product_name }}</span>
                                    </td>
                                    <td class="px-2 py-3 text-right whitespace-nowrap">
                                        <span class="font-heading text-text text-[13px] tabular-nums">{{ $order->formattedAmount() }}</span>
                                        <span class="block font-sans text-muted text-[11px]">{{ ucfirst((string) $order->provider) }}</span>
                                    </td>
                                    <td class="px-2 py-3">
                                        <x-backoffice.badge :tone="$order->statusTone()">{{ $order->statusLabel() }}</x-backoffice.badge>
                                        @if ($order->awaitsFulfilment())
                                            <span class="block font-sans text-[11px] text-[#d79937] mt-1">not sent yet</span>
                                        @elseif ($order->isFulfilled())
                                            <span class="block font-sans text-[11px] text-muted mt-1">sent {{ $order->fulfilled_at?->diffForHumans() }}</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-3">
                                        @if ($order->paid_at)
                                            <p class="font-sans text-text text-[12px]">{{ $order->paid_at->format('j M Y, g:i A') }}</p>
                                            <p class="font-sans text-muted text-[11px]">{{ $order->paid_at->diffForHumans() }}</p>
                                        @else
                                            <span class="font-sans text-muted text-[12px]">—</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-3 text-right">
                                        <a href="{{ route('backoffice.products.orders.show', ['order' => $order->id]) }}"
                                           class="inline-flex h-[34px] px-3.5 rounded-full border border-muted/20 items-center gap-1.5 font-sans text-[12px] text-text hover:border-primary/40 hover:text-primary transition-colors">
                                            {{ $order->awaitsFulfilment() ? 'Send' : 'Open' }}
                                            <i class="ri-arrow-right-line text-[13px]"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">{{ $orders->links() }}</div>
            @endif
        </x-backoffice.card>
    </x-backoffice.page>
</div>
