<div>
    <x-backoffice.page title="Users" subtitle="Everyone who can use the platform">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[380px]">
                <i class="ri-search-line text-muted text-[17px]"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search username, name, email or phone…"
                       class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
                @if ($search !== '')
                    <button type="button" wire:click="$set('search', '')" class="text-muted hover:text-text" aria-label="Clear search"><i class="ri-close-line text-[16px]"></i></button>
                @endif
            </div>

            <div class="flex items-center gap-1.5 flex-wrap">
                @foreach (['all' => 'All', 'active' => 'Active', 'restricted' => 'Restricted', 'suspended' => 'Suspended', 'pending' => 'Pending', 'banned' => 'Banned'] as $key => $label)
                    <button type="button" wire:click="setStatus('{{ $key }}')"
                            class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $status === $key ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">{{ $label }}</button>
                @endforeach
                <span class="w-px h-5 bg-muted/15 mx-1 hidden sm:block"></span>
                <button type="button" wire:click="setSort('latest')" class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $sort === 'latest' ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">Newest</button>
                <button type="button" wire:click="setSort('oldest')" class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $sort === 'oldest' ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">Oldest</button>
                <button type="button" wire:click="setSort('name')" class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $sort === 'name' ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">A–Z</button>
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
                            <tr class="border-b border-muted/10">
                                <th class="th">User</th>
                                <th class="th">Email</th>
                                <th class="th">Status</th>
                                <th class="th">Currency</th>
                                <th class="th">Joined</th>
                                <th class="th-right"><span class="sr-only">Open</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $u)
                                <tr wire:key="u-{{ $u->id }}"
                                    class="tr cursor-pointer"
                                    onclick="window.location.assign('{{ route('backoffice.users.show', $u) }}')">
                                    <td class="td">
                                        <div class="flex items-center gap-3">
                                            <span class="size-9 rounded-full bg-primary/10 overflow-hidden flex items-center justify-center font-heading text-primary text-[12px] shrink-0">
                                                {{ strtoupper(mb_substr($u->full_name ?: $u->username, 0, 2)) }}
                                            </span>
                                            <div class="min-w-0">
                                                <p class="font-sans text-text text-[13px] truncate">{{ $u->full_name }}</p>
                                                <p class="font-sans text-muted text-[12px] truncate">{{ '@'.$u->username }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="td text-muted max-w-[220px] truncate">{{ $u->email }}</td>
                                    <td class="td">
                                        <x-backoffice.badge :tone="match ($u->status->value) { 'active' => 'success', 'restricted', 'suspended' => 'warning', 'pending' => 'info', 'banned' => 'danger', default => 'muted' }">{{ \Illuminate\Support\Str::headline($u->status->value) }}</x-backoffice.badge>
                                    </td>
                                    <td class="td text-muted">{{ strtoupper($u->wallet_currency ?: $u->preferred_currency ?: 'USD') }}</td>
                                    <td class="td text-muted">{{ $u->created_at?->format('j M Y') }}</td>
                                    <td class="td text-right">
                                        <a href="{{ route('backoffice.users.show', $u) }}" wire:navigate class="inline-flex size-8 rounded-full bg-background items-center justify-center text-muted hover:text-text" aria-label="Open user"><i class="ri-arrow-right-s-line"></i></a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-4">
                    {{ $users->links('vendor.pagination.backoffice') }}
                </div>
            @endif
        </x-backoffice.card>
    </x-backoffice.page>
</div>
