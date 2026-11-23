<div>
    <x-backoffice.page title="Call IDs" subtitle="Every allocated number across all users">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[320px]">
                <i class="ri-search-line text-muted text-[17px]"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search user…"
                       class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
            </div>
            <div class="flex items-center gap-1.5 flex-wrap">
                @foreach (['all' => 'All sources', 'package' => 'Package', 'additional' => 'Additional'] as $value => $label)
                    <button type="button" wire:click="setSource('{{ $value }}')" class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $source === $value ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">{{ $label }}</button>
                @endforeach
                <span class="mx-1 h-5 w-px bg-muted/15"></span>
                @foreach (['all' => 'All', 'active' => 'Active', 'expired' => 'Expired'] as $value => $label)
                    <button type="button" wire:click="setStatus('{{ $value }}')" class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $status === $value ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($numbers->isEmpty())
                <div class="p-10 text-center font-sans text-muted text-[14px]">No call IDs here.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[860px]">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">User</th>
                                <th class="px-5 py-3 font-medium">Call ID</th>
                                <th class="px-5 py-3 font-medium">Source</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 font-medium">Expires</th>
                                <th class="px-5 py-3 font-medium">Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($numbers as $n)
                                @php
                                    [$stLabel, $stTone] = $n->expires_at === null
                                        ? ['Permanent', 'muted']
                                        : ($n->is_expired ? ['Expired', 'danger'] : ['Active', 'success']);
                                @endphp
                                <tr wire:key="num-{{ $n->id }}" wire:click="openDetail('{{ $n->id }}')" class="border-b border-muted/5 last:border-0 hover:bg-background/50 cursor-pointer transition-colors">
                                    <td class="px-5 py-3">
                                        @if ($n->user)
                                            <a href="{{ route('backoffice.users.show', $n->user) }}" wire:navigate @click.stop class="block hover:opacity-80 transition"><x-backoffice.user-cell :user="$n->user" /></a>
                                        @else
                                            <span class="font-sans text-muted text-[13px]">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <span class="size-9 rounded-full bg-background overflow-hidden flex items-center justify-center shrink-0 ring-1 ring-black/[0.04]">
                                                <img src="{{ $n->logo }}" alt="{{ $n->network }}" class="size-full object-cover">
                                            </span>
                                            <div class="min-w-0">
                                                <p class="font-mono text-text text-[13px] leading-tight whitespace-nowrap">{{ $n->masked }}</p>
                                                <p class="font-sans text-muted text-[12px] leading-tight">{{ $n->network }} · {{ $n->callType()->getName() }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3"><x-backoffice.badge :tone="$n->source === 'package' ? 'info' : 'warning'">{{ ucfirst($n->source) }}</x-backoffice.badge></td>
                                    <td class="px-5 py-3"><x-backoffice.badge :tone="$stTone">{{ $stLabel }}</x-backoffice.badge></td>
                                    <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $n->expires_at ? $n->expires_at->format('j M Y, g:i A') : '—' }}</td>
                                    <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $n->created_at?->format('j M Y, g:i A') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-backoffice.card>

        @if ($numbers->hasPages())<div>{{ $numbers->links('vendor.pagination.nextel') }}</div>@endif

        <x-backoffice.modal :show="$showDetail" title="Call ID details" closeAction="$set('showDetail', false)">
            @if ($detailNumber)
                @php
                    [$stLabel, $stTone] = $detailNumber->expires_at === null
                        ? ['Permanent', 'muted']
                        : ($detailNumber->is_expired ? ['Expired', 'danger'] : ['Active', 'success']);
                @endphp
                <div class="flex flex-col gap-5">
                    <div class="flex items-center justify-between gap-3">
                        @if ($detailNumber->user)
                            <a href="{{ route('backoffice.users.show', $detailNumber->user) }}" wire:navigate class="block min-w-0 hover:opacity-80 transition"><x-backoffice.user-cell :user="$detailNumber->user" /></a>
                        @else
                            <span class="font-sans text-muted text-[13px]">—</span>
                        @endif
                        <x-backoffice.badge :tone="$stTone">{{ $stLabel }}</x-backoffice.badge>
                    </div>

                    <div class="flex flex-col items-center gap-2.5 py-1">
                        <span class="size-14 rounded-full bg-background overflow-hidden flex items-center justify-center ring-1 ring-black/[0.04]">
                            <img src="{{ $detailNumber->logo }}" alt="{{ $detailNumber->network }}" class="size-full object-cover">
                        </span>
                        <p class="font-mono text-text text-[18px] leading-none">{{ $detailNumber->masked }}</p>
                    </div>

                    <div class="bg-background rounded-[16px] divide-y divide-muted/10">
                        @foreach ([
                            ['Network', $detailNumber->network],
                            ['Type', $detailNumber->callType()->getName()],
                            ['Source', ucfirst($detailNumber->source)],
                            ['Status', $stLabel],
                            ['Expires', $detailNumber->expires_at ? $detailNumber->expires_at->format('j M Y, g:i A') : 'Permanent'],
                            ['Created', $detailNumber->created_at?->format('j M Y, g:i A') ?? '—'],
                        ] as [$label, $value])
                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                <span class="font-sans text-muted text-[13px]">{{ $label }}</span>
                                <span class="font-sans text-text text-[13px] text-right truncate">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-backoffice.modal>
    </x-backoffice.page>
</div>
