<div>
    <x-backoffice.page title="Call Claims" subtitle="Every call earning claimed across all users">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[320px]">
                <i class="ri-search-line text-muted text-[17px]"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search user…"
                       class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
            </div>
            <div class="flex items-center gap-2">
                <select wire:model.live="callType" class="h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm font-sans text-[13px] text-text outline-none border-0">
                    <option value="all">All call types</option>
                    @foreach ($callTypes as $ct)
                        <option value="{{ $ct->id }}">{{ $ct->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($claims->isEmpty())
                <div class="p-10 text-center font-sans text-muted text-[14px]">No call claims here.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[720px]">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">User</th>
                                <th class="px-5 py-3 font-medium">Call type</th>
                                <th class="px-5 py-3 font-medium text-right">Amount</th>
                                <th class="px-5 py-3 font-medium">Claimed</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($claims as $c)
                                <tr wire:key="cl-{{ $c->id }}" wire:click="openDetail('{{ $c->id }}')" class="border-b border-muted/5 last:border-0 hover:bg-background/50 cursor-pointer transition-colors">
                                    <td class="px-5 py-3">
                                        @if ($c->user)
                                            <a href="{{ route('backoffice.users.show', $c->user) }}" wire:navigate @click.stop class="block hover:opacity-80 transition"><x-backoffice.user-cell :user="$c->user" /></a>
                                        @else
                                            <span class="font-sans text-muted text-[13px]">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <span class="size-2.5 rounded-full shrink-0" style="background-color: {{ $c->callType?->color ?? '#18443E' }}"></span>
                                            <span class="font-sans text-text text-[13px]">{{ $c->callType?->name ?? ($c->network === 'Nextel' ? 'On-net' : 'Off-net') }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 text-right font-heading text-success text-[13px] whitespace-nowrap">+{{ money($c->amount) }}</td>
                                    <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ ($c->claimed_at ?? $c->created_at)?->format('j M Y, g:i A') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-backoffice.card>

        @if ($claims->hasPages())<div>{{ $claims->links('vendor.pagination.nextel') }}</div>@endif

        <x-backoffice.modal :show="$showDetail" title="Claim details" closeAction="$set('showDetail', false)">
            @if ($detailClaim)
                <div class="flex flex-col gap-5">
                    <div class="flex items-center justify-between gap-3">
                        @if ($detailClaim->user)
                            <a href="{{ route('backoffice.users.show', $detailClaim->user) }}" wire:navigate class="block min-w-0 hover:opacity-80 transition"><x-backoffice.user-cell :user="$detailClaim->user" /></a>
                        @else
                            <span class="font-sans text-muted text-[13px]">—</span>
                        @endif
                        <p class="font-heading font-semibold text-success text-[18px] whitespace-nowrap">+{{ money($detailClaim->amount) }}</p>
                    </div>

                    <div class="bg-background rounded-[16px] divide-y divide-muted/10">
                        @foreach (array_filter([
                            ['Call type', $detailClaim->callType?->name ?? ($detailClaim->network === 'Nextel' ? 'On-net' : 'Off-net')],
                            ['Amount', '+'.money($detailClaim->amount)],
                            ['Claimed at', $detailClaim->claimed_at?->format('j M Y, g:i A') ?? '—'],
                            ['Created at', $detailClaim->created_at?->format('j M Y, g:i A') ?? '—'],
                            $detailClaim->call_record_id ? ['Call record', $detailClaim->call_record_id] : null,
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
