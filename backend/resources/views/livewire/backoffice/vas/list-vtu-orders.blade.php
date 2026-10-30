<div>
    <x-backoffice.page title="Bill Payments" subtitle="VTU orders (VTpass)">

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[320px]">
                <i class="ri-search-line text-muted text-[17px]"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Reference, recipient, user…"
                       class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
            </div>
            <div class="flex items-center gap-1.5 flex-wrap">
                @foreach (['all' => 'All'] + collect($statuses)->mapWithKeys(fn ($s) => [$s->value => $s->getName()])->all() as $key => $label)
                    <button type="button" wire:click="setStatus('{{ $key }}')" class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $status === $key ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">{{ $label }}</button>
                @endforeach
                <select wire:model.live="service" class="h-[34px] rounded-full bg-surface border-0 px-3 font-sans text-[12px] text-muted focus:ring-2 focus:ring-primary/40">
                    <option value="all">All services</option>
                    @foreach ($services as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                </select>
            </div>
        </div>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($orders->isEmpty())
                <div class="p-10 text-center font-sans text-muted text-[14px]">No bill payments match.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[880px]">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">Service / Reference</th>
                                <th class="px-5 py-3 font-medium">User</th>
                                <th class="px-5 py-3 font-medium">Recipient</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 font-medium text-right">Amount</th>
                                <th class="px-5 py-3 font-medium">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $order)
                                <tr wire:key="vo-{{ $order->id }}" wire:click="openDetail('{{ $order->id }}')" class="border-b border-muted/5 last:border-0 hover:bg-background/50 cursor-pointer transition-colors">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="size-9 rounded-full flex items-center justify-center shrink-0 bg-primary/10 text-primary"><i class="ri-shopping-bag-line text-[16px]"></i></span>
                                            <div class="min-w-0">
                                                <p class="font-sans text-text text-[13px] truncate">{{ $services[$order->service] ?? ucfirst($order->service) }} · {{ $order->meta['provider_label'] ?? $order->service_id }}</p>
                                                <p class="font-sans text-muted text-[12px] truncate">{{ $order->request_id }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3">
                                        @if ($order->user)
                                            <a href="{{ route('backoffice.users.show', $order->user) }}" wire:navigate @click.stop class="block hover:opacity-80 transition"><x-backoffice.user-cell :user="$order->user" /></a>
                                        @else
                                            <span class="font-sans text-muted text-[13px]">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 font-sans text-muted text-[12px]">{{ $order->billers_code ?: $order->phone }}</td>
                                    <td class="px-5 py-3">
                                        <x-backoffice.badge :tone="$order->status->color()">{{ $order->status->getName() }}</x-backoffice.badge>
                                    </td>
                                    <td class="px-5 py-3 text-right whitespace-nowrap">
                                        <p class="font-heading text-[13px] text-text">₦{{ number_format((float) $order->amount_ngn, 0) }}</p>
                                        <p class="font-sans text-muted text-[11px]">{{ money($order->amount_usd) }}</p>
                                    </td>
                                    <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $order->created_at?->format('j M Y, g:i A') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-backoffice.card>

        @if ($orders->hasPages())<div>{{ $orders->links('vendor.pagination.nextel') }}</div>@endif

        <x-backoffice.modal :show="$showDetail" title="Bill payment details" closeAction="$set('showDetail', false)">
            @if ($detailOrder)
                @php $o = $detailOrder; @endphp
                <div class="flex flex-col gap-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-heading font-semibold text-[24px] leading-none text-text">₦{{ number_format((float) $o->amount_ngn, 0) }}</p>
                            <p class="mt-1.5 font-sans text-text text-[14px] truncate">{{ $services[$o->service] ?? ucfirst($o->service) }} · {{ $o->meta['provider_label'] ?? $o->service_id }}</p>
                            <div class="mt-1.5"><x-backoffice.badge :tone="$o->status->color()">{{ $o->status->getName() }}</x-backoffice.badge></div>
                        </div>
                        <span class="size-12 rounded-[14px] flex items-center justify-center shrink-0 bg-primary/10 text-primary"><i class="ri-shopping-bag-line text-[20px]"></i></span>
                    </div>

                    @if ($o->user)
                        <div class="rounded-[16px] bg-background p-1.5">
                            <p class="px-3 pt-2 pb-1 font-sans text-muted text-[11px] uppercase tracking-wide">Account</p>
                            <a href="{{ route('backoffice.users.show', $o->user) }}" wire:navigate class="flex items-center gap-3 rounded-[12px] px-3 py-2.5 hover:bg-surface transition">
                                <div class="flex-1 min-w-0"><x-backoffice.user-cell :user="$o->user" /></div>
                                <i class="ri-arrow-right-up-line text-muted text-[15px] shrink-0"></i>
                            </a>
                        </div>
                    @endif

                    <div class="bg-background rounded-[16px] divide-y divide-muted/10">
                        @foreach (array_values(array_filter([
                            ['Reference', $o->request_id, true],
                            $o->external_id ? ['VTpass ID', $o->external_id, true] : null,
                            ['Recipient', $o->billers_code ?: $o->phone, false],
                            ! empty($o->meta['customer_name']) ? ['Name', $o->meta['customer_name'], false] : null,
                            ! empty($o->meta['plan_label']) ? ['Plan', $o->meta['plan_label'], false] : null,
                            $o->variation_code ? ['Variation', $o->variation_code, false] : null,
                            ['Amount', '₦'.number_format((float) $o->amount_ngn, 2), false],
                            ['Debited', money($o->amount_usd), false],
                            ['Wallet', \App\Models\User::WALLET_LABELS[$o->wallet] ?? $o->wallet, false],
                            $o->refunded ? ['Refunded', 'Yes', false] : null,
                            filled($o->token) ? ['Token', $o->token, true] : null,
                            ['Date', $o->created_at?->format('j M Y, g:i A') ?? '—', false],
                        ])) as [$label, $value, $mono])
                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                <span class="font-sans text-muted text-[13px]">{{ $label }}</span>
                                <span class="text-text text-[13px] text-right break-all {{ $mono ? 'font-mono' : 'font-sans' }}">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>

                    @if ($o->status->isPending() && auth('admin')->user()->canWrite())
                        <button type="button" wire:click="requeryOrder('{{ $o->id }}')" wire:loading.attr="disabled" wire:target="requeryOrder"
                                class="h-[46px] rounded-full bg-primary text-white font-sans font-semibold text-[14px] hover:opacity-95 transition disabled:opacity-60">
                            <span wire:loading.remove wire:target="requeryOrder"><i class="ri-refresh-line"></i> Requery status</span>
                            <span wire:loading wire:target="requeryOrder">Checking…</span>
                        </button>
                    @endif
                </div>
            @endif
        </x-backoffice.modal>
    </x-backoffice.page>
</div>
