<div>
    <x-backoffice.page title="Cloud Inbox opens" subtitle="Users who opened their SMS in the dashboard — this is what counts as an open">
        @php
            $input = 'h-[46px] rounded-[12px] bg-background border border-muted/15 px-3.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40';
        @endphp

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
            <x-backoffice.stat label="Users who opened" :value="$openers" icon="ri-mail-open-line"
                              hint="{{ $rangeLabel }}" hintFull="Distinct users who opened at least one Cloud Inbox message in {{ $rangeLabel }}" />
            <x-backoffice.stat label="Opened today" :value="$openersToday" icon="ri-calendar-check-line" tone="success" />
            <x-backoffice.stat label="Messages opened" :value="$openedMessages" icon="ri-chat-check-line"
                              :hint="$openRate !== null ? $openRate.'% of '.number_format($deliveredMessages).' delivered' : null"
                              hintFull="Messages opened in the dashboard out of the {{ number_format($deliveredMessages) }} delivered in {{ $rangeLabel }}" />
            <x-backoffice.stat label="Earned on opens" :value="$earned" money icon="ri-hand-coin-line" tone="warning"
                              :hint="$claimRate !== null ? $claimRate.'% of opens claimed' : number_format($claimedMessages).' claimed'"
                              hintFull="{{ number_format($claimedMessages) }} opened messages were claimed for earnings" />
        </div>

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[380px]">
                <i class="ri-search-line text-muted text-[17px]"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search name, username, email or phone…" class="{{ $input }} !h-auto !bg-transparent !border-0 !px-0 flex-1 min-w-0">
                @if ($search !== '')
                    <button type="button" wire:click="$set('search', '')" class="text-muted hover:text-text"><i class="ri-close-line text-[16px]"></i></button>
                @endif
            </div>

            <div class="flex items-center gap-1.5 flex-wrap">
                @foreach (['all' => 'All time', 'today' => 'Today', '7' => '7 days', '30' => '30 days'] as $key => $label)
                    <button type="button" wire:click="setRange('{{ $key }}')"
                            class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $range === $key ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">{{ $label }}</button>
                @endforeach

                <span class="w-px h-6 bg-muted/20 mx-1"></span>

                @foreach (['all' => 'Any', 'claimed' => 'Claimed', 'unclaimed' => 'Unclaimed'] as $key => $label)
                    <button type="button" wire:click="setClaim('{{ $key }}')"
                            class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $claim === $key ? 'bg-primary text-white' : 'bg-background text-muted hover:text-text' }}">{{ $label }}</button>
                @endforeach

                {{-- The table below is always live; only the four headline figures are memoised. --}}
                <button type="button" wire:click="refreshTiles" wire:loading.attr="disabled" wire:target="refreshTiles"
                        title="Headline figures are reused for up to a minute — click to recalculate them now"
                        class="ml-1 inline-flex items-center gap-1.5 px-3 h-[34px] rounded-full bg-surface font-sans text-muted text-[12px] shadow-sm hover:text-text transition disabled:opacity-60">
                    <i class="ri-refresh-line" wire:loading.remove wire:target="refreshTiles"></i>
                    <i class="ri-loader-4-line animate-spin" wire:loading wire:target="refreshTiles"></i>
                    Refresh figures
                </button>
            </div>
        </div>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($users->isEmpty())
                <div class="p-10 flex flex-col items-center gap-2.5 text-center">
                    <span class="size-12 rounded-full bg-background flex items-center justify-center text-muted"><i class="ri-mail-open-line text-[22px]"></i></span>
                    <p class="font-sans text-muted text-[14px]">No one has opened a Cloud Inbox message in {{ $rangeLabel }}{{ trim($search) !== '' ? ' matching this search' : '' }}.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">User</th>
                                <th class="px-5 py-3 font-medium" title="Messages delivered to their Cloud Inbox">Received</th>
                                <th class="px-5 py-3 font-medium" title="Messages opened (read) in the dashboard">Opened</th>
                                <th class="px-5 py-3 font-medium" title="Still unread">Unread</th>
                                <th class="px-5 py-3 font-medium" title="Opened messages claimed for earnings">Claimed</th>
                                <th class="px-5 py-3 font-medium" title="USD earned by claiming sponsored messages">Earned</th>
                                <th class="px-5 py-3 font-medium">Last opened</th>
                                <th class="px-5 py-3 font-medium text-right"> </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $user)
                                <tr wire:key="opener-{{ $user->id }}" class="border-b border-muted/5 last:border-0 hover:bg-background/60 transition-colors">
                                    <td class="px-5 py-3"><x-backoffice.user-cell :user="$user" :label="$user->package?->name ?? 'No package'" /></td>
                                    <td class="px-5 py-3 font-sans text-text text-[13px]">{{ number_format($user->received_count) }}</td>
                                    <td class="px-5 py-3">
                                        <span class="font-heading font-semibold text-text text-[14px]">{{ number_format($user->opened_count) }}</span>
                                        @if ($user->unread_count > 0)
                                            <span class="font-sans text-muted text-[12px]">of {{ number_format($user->received_count) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        @if ($user->unread_count > 0)
                                            <x-backoffice.badge tone="warning">{{ number_format($user->unread_count) }}</x-backoffice.badge>
                                        @else
                                            <x-backoffice.badge tone="success">None</x-backoffice.badge>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 font-sans text-text text-[13px]">{{ number_format($user->claimed_count) }}</td>
                                    <td class="px-5 py-3"><x-figure :value="(float) $user->claimed_earnings" money class="font-sans text-text text-[13px]" /></td>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        <p class="font-sans text-text text-[13px]">{{ $user->last_opened_at ? \Illuminate\Support\Carbon::parse($user->last_opened_at)->format('j M Y') : '—' }}</p>
                                        <p class="font-sans text-muted text-[12px]">{{ $user->last_opened_at ? \Illuminate\Support\Carbon::parse($user->last_opened_at)->diffForHumans() : '' }}</p>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <a href="{{ route('backoffice.reports.inbox-opens.show', $user) }}" wire:navigate
                                           class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-3 h-[34px] font-sans font-medium text-primary text-[12px] hover:bg-primary/15">
                                            Messages <i class="ri-arrow-right-line"></i>
                                        </a>
                                    </td>
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
