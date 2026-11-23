<div>
    <x-backoffice.page title="Cloud Inbox detail"
                       :subtitle="$user->full_name.' · @'.$user->username"
                       :back="route('backoffice.reports.inbox-opens.index')">
        <x-slot:action>
            <div class="flex items-center gap-2">
                <a href="{{ route('backoffice.users.show', $user) }}" wire:navigate
                   class="h-[44px] px-4 rounded-[14px] bg-surface text-text font-sans text-[13px] flex items-center gap-1.5 shadow-sm hover:bg-surface/70 transition">
                    <i class="ri-user-line text-[16px]"></i> Full profile
                </a>
                <a href="{{ route('backoffice.reports.signins.show', $user) }}" wire:navigate
                   class="h-[44px] px-4 rounded-[14px] bg-primary text-white font-sans text-[13px] flex items-center gap-1.5 hover:opacity-95 transition">
                    <i class="ri-login-circle-line text-[16px]"></i> Sign-ins
                </a>
            </div>
        </x-slot:action>

        <x-backoffice.card>
            <div class="flex items-start gap-4 flex-wrap">
                <x-backoffice.user-cell :user="$user" :label="$user->package?->name ?? 'No package'" />
                <div class="flex items-center gap-2 flex-wrap ml-auto">
                    @if ($user->email)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-background px-3 h-[30px] font-sans text-muted text-[12px]"><i class="ri-mail-line"></i> {{ $user->email }}</span>
                    @endif
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-background px-3 h-[30px] font-sans text-muted text-[12px]"><i class="ri-smartphone-line"></i> {{ $user->phone ?: 'No phone' }}</span>
                    <x-backoffice.badge :tone="$user->status?->value === 'active' ? 'success' : 'danger'">{{ $user->status?->getName() ?? 'Unknown' }}</x-backoffice.badge>
                </div>
            </div>
        </x-backoffice.card>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
            <x-backoffice.stat label="Messages received" :value="$received" icon="ri-inbox-line"
                              hint="{{ $sponsored }} sponsored · {{ $system }} system" hintFull="Delivered to this user's Cloud Inbox" />
            <x-backoffice.stat label="Opened" :value="$opened" icon="ri-mail-open-line" tone="success"
                              :hint="$received > 0 ? round($opened / $received * 100).'% of inbox' : null" hintFull="Messages this user opened in the dashboard" />
            <x-backoffice.stat label="Unread" :value="$unread" icon="ri-mail-unread-line" tone="warning" />
            <x-backoffice.stat label="Earned on opens" :value="$earned" money icon="ri-hand-coin-line" tone="success"
                              hint="{{ $claimed }} claimed" hintFull="USD earned by claiming sponsored messages" />
        </div>

        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
            <div class="flex items-center gap-3 rounded-[16px] bg-surface px-4 py-3">
                <i class="ri-time-line text-primary text-[18px]"></i>
                <p class="font-sans text-muted text-[13px]">
                    First open <span class="text-text font-medium">{{ $firstOpened ? \Illuminate\Support\Carbon::parse($firstOpened)->format('j M Y, g:i A') : 'never' }}</span>
                    <span class="mx-1.5 text-muted/50">·</span>
                    Last open <span class="text-text font-medium">{{ $lastOpened ? \Illuminate\Support\Carbon::parse($lastOpened)->format('j M Y, g:i A') : 'never' }}</span>
                </p>
            </div>

            <div class="flex items-center gap-1.5 flex-wrap">
                @foreach (['all' => 'All', 'opened' => 'Opened', 'unread' => 'Unread', 'claimed' => 'Claimed'] as $key => $label)
                    <button type="button" wire:click="setState('{{ $key }}')"
                            class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $state === $key ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($messages->isEmpty())
                <div class="p-10 flex flex-col items-center gap-2.5 text-center">
                    <span class="size-12 rounded-full bg-background flex items-center justify-center text-muted"><i class="ri-inbox-line text-[22px]"></i></span>
                    <p class="font-sans text-muted text-[14px]">Nothing in this inbox for these filters.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">From</th>
                                <th class="px-5 py-3 font-medium">Message</th>
                                <th class="px-5 py-3 font-medium">Received</th>
                                <th class="px-5 py-3 font-medium">Opened</th>
                                <th class="px-5 py-3 font-medium">Claimed</th>
                                <th class="px-5 py-3 font-medium text-right">Earned</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($messages as $message)
                                <tr wire:key="inbox-{{ $message->id }}" class="border-b border-muted/5 last:border-0 align-top">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <span class="size-9 rounded-full bg-primary/10 overflow-hidden flex items-center justify-center font-heading text-primary text-[12px] shrink-0">
                                                @if ($message->avatar_url)
                                                    <img src="{{ $message->avatar_url }}" alt="" class="size-full object-cover">
                                                @else
                                                    {{ strtoupper(substr($message->sender_name ?: 'S', 0, 2)) }}
                                                @endif
                                            </span>
                                            <div class="min-w-0">
                                                <p class="font-sans text-text text-[13px] truncate">{{ $message->sender_name }}</p>
                                                <p class="font-sans text-muted text-[12px] truncate">{{ $message->sender_number ?: ($message->is_sponsored ? 'Sponsored' : 'System') }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 max-w-[320px]">
                                        <p class="font-sans text-text text-[13px] truncate">{{ $message->subject }}</p>
                                        <p class="font-sans text-muted text-[12px] truncate">{{ \Illuminate\Support\Str::limit($message->body, 90) }}</p>
                                    </td>
                                    <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $message->created_at?->format('j M Y, g:i A') }}</td>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        @if ($message->read_at)
                                            <p class="font-sans text-text text-[13px]">{{ $message->read_at->format('j M Y, g:i A') }}</p>
                                            <p class="font-sans text-muted text-[12px]">after {{ $message->created_at?->diffForHumans($message->read_at, true) }}</p>
                                        @else
                                            <x-backoffice.badge tone="warning">Not opened</x-backoffice.badge>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        @if ($message->claimed_at)
                                            <p class="font-sans text-text text-[13px]">{{ $message->claimed_at->format('j M Y, g:i A') }}</p>
                                        @elseif ($message->is_claimable)
                                            <x-backoffice.badge tone="info">Unclaimed</x-backoffice.badge>
                                        @else
                                            <span class="font-sans text-muted text-[12px]">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right whitespace-nowrap">
                                        @if ((float) $message->earn_rate > 0)
                                            <x-figure :value="(float) $message->earn_rate" money class="justify-end font-sans text-text text-[13px]" />
                                        @else
                                            <span class="font-sans text-muted text-[12px]">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-backoffice.card>

        @if ($messages->hasPages())
            <div>{{ $messages->links('vendor.pagination.nextel') }}</div>
        @endif
    </x-backoffice.page>
</div>
