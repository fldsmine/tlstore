<div>
    <x-backoffice.page title="Users" subtitle="Everyone on the platform">
        @php
            $tone = ['active' => 'success', 'suspended' => 'warning'];
        @endphp

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[380px]">
                <i class="ri-search-line text-muted text-[17px]"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search username, name, email or phone…"
                       class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
                @if ($search !== '')
                    <button type="button" wire:click="$set('search', '')" class="text-muted hover:text-text"><i class="ri-close-line text-[16px]"></i></button>
                @endif
            </div>

            <div class="flex items-center gap-1.5 flex-wrap">
                @foreach (['all' => 'All', 'active' => 'Active', 'suspended' => 'Suspended'] as $key => $label)
                    <button type="button" wire:click="setStatus('{{ $key }}')"
                            class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $status === $key ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">{{ $label }}</button>
                @endforeach
                <span class="w-px h-5 bg-muted/15 mx-1"></span>
                @foreach (['all' => 'Everyone', 'agents' => 'Agents', 'special' => 'Special', 'members' => 'Members'] as $key => $label)
                    <button type="button" wire:click="setType('{{ $key }}')"
                            class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $type === $key ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($users->isEmpty())
                <div class="p-10 flex flex-col items-center gap-2.5 text-center">
                    <span class="size-12 rounded-full bg-background flex items-center justify-center text-muted"><i class="ri-group-line text-[22px]"></i></span>
                    <p class="font-sans text-muted text-[14px]">No users match these filters.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">User</th>
                                <th class="px-5 py-3 font-medium">Email</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                                @if ($type === 'agents')
                                    <th class="px-5 py-3 font-medium text-right">Vendor Float</th>
                                    <th class="px-5 py-3 font-medium text-right">Tokens Sold</th>
                                @else
                                    <th class="px-5 py-3 font-medium">Package</th>
                                @endif
                                <th class="px-5 py-3 font-medium text-right">Balance</th>
                                <th class="px-5 py-3 font-medium text-right">Lifetime</th>
                                <th class="px-5 py-3 font-medium">Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $u)
                                <tr wire:key="u-{{ $u->id }}"
                                    onclick="window.location='{{ route('backoffice.users.show', $u) }}'"
                                    class="border-b border-muted/5 last:border-0 hover:bg-background/60 cursor-pointer transition-colors">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="size-9 rounded-full bg-primary/10 overflow-hidden flex items-center justify-center font-heading text-primary text-[12px] shrink-0">
                                                @if ($u->avatar)<img src="{{ $u->avatar }}" alt="" class="size-full object-cover">@else{{ strtoupper(substr($u->first_name ?: $u->username, 0, 2)) }}@endif
                                            </span>
                                            <div class="min-w-0">
                                                <p class="font-sans text-text text-[13px] truncate flex items-center gap-1.5">
                                                    {{ $u->full_name }}
                                                    @if ($u->is_agent)<i class="ri-store-2-line text-primary text-[13px]" title="Agent"></i>@endif
                                                </p>
                                                <p class="font-sans text-muted text-[12px] truncate">{{ '@'.$u->username }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 font-sans text-muted text-[13px] truncate max-w-[220px]">{{ $u->email }}</td>
                                    <td class="px-5 py-3">
                                        <x-backoffice.badge :tone="$tone[$u->status->value] ?? 'muted'">{{ $u->status->getName() }}</x-backoffice.badge>
                                    </td>
                                    @if ($type === 'agents')
                                        <td class="px-5 py-3 text-right whitespace-nowrap"><x-figure :value="$u->agent_balance" money class="justify-end font-sans text-text text-[13px]" /></td>
                                        <td class="px-5 py-3 font-sans text-text text-[13px] text-right whitespace-nowrap">{{ number_format($u->sold_coupons_count) }}</td>
                                    @else
                                        <td class="px-5 py-3 font-sans text-text text-[13px]">{{ $u->package?->name ?? '—' }}</td>
                                    @endif
                                    <td class="px-5 py-3 text-right whitespace-nowrap"><x-figure :value="$u->total_balance" money class="justify-end font-sans text-text text-[13px]" /></td>
                                    <td class="px-5 py-3 text-right whitespace-nowrap"><x-figure :value="$u->lifetime_earnings" money class="justify-end font-sans text-muted text-[13px]" /></td>
                                    <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $u->created_at?->format('j M Y, g:i A') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-backoffice.card>

        @if ($users->hasPages())
            <div>{{ $users->links('vendor.pagination.nextel') }}</div>
        @endif
    </x-backoffice.page>
</div>
