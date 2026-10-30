<div>
    <x-backoffice.page title="Mini App users" subtitle="People who sign in through the Telegram Mini App">
        @if ($this->canWrite())
            <x-slot:action>
                <button type="button" wire:click="openBroadcast" aria-label="Message all Mini App users"
                        class="inline-flex items-center justify-center gap-1.5 h-[44px] w-[44px] sm:w-auto sm:px-4 rounded-[14px] bg-primary text-white font-sans text-[13px]">
                    <i class="ri-megaphone-line text-[20px] sm:text-[16px]"></i><span class="hidden sm:inline">Message all</span>
                </button>
            </x-slot:action>
        @endif

        @php
            $input = 'h-[46px] rounded-[12px] bg-background border border-muted/15 px-3.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40';
        @endphp

        @unless ($botConfigured)
            <div class="flex items-start gap-3 rounded-[16px] bg-[#d79937]/12 border border-[#d79937]/25 px-4 py-3.5">
                <i class="ri-error-warning-fill text-[#d79937] text-[20px] shrink-0 mt-0.5"></i>
                <div class="flex-1 min-w-0">
                    <p class="font-heading font-medium text-text text-[14px]">Telegram bot is not configured</p>
                    <p class="font-sans text-muted text-[13px]">Set <span class="font-mono">TELEGRAM_BOT_TOKEN</span> to deliver DMs. You can still browse Mini App users and inbound messages.</p>
                </div>
            </div>
        @endunless

        <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 lg:gap-4">
            <x-backoffice.stat label="Mini App users" :value="$totalUsers" icon="ri-telegram-line" hint="Telegram as access point" />
            <x-backoffice.stat label="Unread chats" :value="$unreadConversations" icon="ri-chat-unread-line" tone="warning" hint="waiting on a reply" />
            <x-backoffice.stat label="Linked this week" :value="$linkedThisWeek" icon="ri-user-add-line" tone="success" />
        </div>

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[380px]">
                <i class="ri-search-line text-muted text-[17px]"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search Nextel user, Telegram name or @handle…"
                       class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
                @if ($search !== '')
                    <button type="button" wire:click="$set('search', '')" class="text-muted hover:text-text"><i class="ri-close-line text-[16px]"></i></button>
                @endif
            </div>

            <div class="flex items-center gap-1.5 flex-wrap">
                @foreach (['all' => 'All', 'unread' => 'Unread'] as $key => $label)
                    <button type="button" wire:click="setFilter('{{ $key }}')"
                            class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $filter === $key ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($identities->isEmpty())
                <div class="p-10 flex flex-col items-center gap-2.5 text-center">
                    <span class="size-12 rounded-full bg-background flex items-center justify-center text-muted"><i class="ri-telegram-line text-[22px]"></i></span>
                    <p class="font-sans text-muted text-[14px]">{{ $search !== '' || $filter !== 'all' ? 'No Mini App users match these filters.' : 'No one has linked Telegram yet.' }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">Mini App user</th>
                                <th class="px-5 py-3 font-medium">Nextel account</th>
                                <th class="px-5 py-3 font-medium">Last message</th>
                                <th class="px-5 py-3 font-medium">Linked</th>
                                <th class="px-5 py-3 font-medium text-right"> </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($identities as $identity)
                                <tr wire:key="tg-{{ $identity->id }}"
                                    onclick="window.location='{{ route('backoffice.telegram-users.show', $identity) }}'"
                                    class="border-b border-muted/5 last:border-0 hover:bg-background/60 cursor-pointer transition-colors">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="size-9 rounded-full bg-[#2AABEE]/12 overflow-hidden flex items-center justify-center font-heading text-[#2AABEE] text-[12px] shrink-0">
                                                {{ strtoupper(substr($identity->displayName(), 0, 2)) }}
                                            </span>
                                            <div class="min-w-0">
                                                <p class="font-sans text-text text-[13px] truncate flex items-center gap-1.5">
                                                    {{ $identity->displayName() }}
                                                    @if ($identity->unread_count > 0)
                                                        <x-backoffice.badge tone="warning">{{ $identity->unread_count }} new</x-backoffice.badge>
                                                    @endif
                                                </p>
                                                <p class="font-sans text-muted text-[12px] truncate">{{ $identity->handle() ?? 'ID '.$identity->telegram_user_id }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3">
                                        @if ($identity->user)
                                            <p class="font-sans text-text text-[13px] truncate">{{ $identity->user->full_name }}</p>
                                            <p class="font-sans text-muted text-[12px] truncate">{{ '@'.$identity->user->username }} · {{ $identity->user->package?->name ?? 'No package' }}</p>
                                        @else
                                            <p class="font-sans text-muted text-[13px]">Unlinked account</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 max-w-[280px]">
                                        @if ($identity->latestMessage)
                                            <p class="font-sans text-text text-[13px] truncate">{{ $identity->latestMessage->isInbound() ? 'Them: ' : 'You: ' }}{{ $identity->latestMessage->body }}</p>
                                            <p class="font-sans text-muted text-[12px]">{{ $identity->latestMessage->created_at?->diffForHumans() }}</p>
                                        @else
                                            <p class="font-sans text-muted text-[13px]">No messages yet</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $identity->created_at?->format('j M Y, g:i A') }}</td>
                                    <td class="px-5 py-3 text-right">
                                        <a href="{{ route('backoffice.telegram-users.show', $identity) }}" wire:navigate
                                           onclick="event.stopPropagation()"
                                           class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-3 h-[34px] font-sans font-medium text-primary text-[12px] hover:bg-primary/15">
                                            Chat <i class="ri-arrow-right-line"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-backoffice.card>

        @if ($identities->hasPages())
            <div>{{ $identities->links('vendor.pagination.nextel') }}</div>
        @endif

        <x-backoffice.modal :show="$showBroadcast" title="Message every Mini App user" closeAction="$set('showBroadcast', false)" size="lg">
            <form wire:submit="sendBroadcast" class="flex flex-col gap-3.5">
                <p class="font-sans text-muted text-[13px]">
                    Sends a Telegram DM to all {{ number_format($totalUsers) }} linked Mini App {{ \Illuminate\Support\Str::plural('user', $totalUsers) }}.
                    Telegram only delivers if they have started the bot.
                </p>
                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-muted text-[12px]">Message</label>
                    <textarea wire:model="broadcastBody" rows="5" maxlength="4000" placeholder="Write the update you want every Mini App user to see…"
                              class="rounded-[12px] bg-background border border-muted/15 px-3 py-2 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40 resize-y"></textarea>
                    @error('broadcastBody')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                </div>
                <div class="flex justify-end gap-2 mt-1">
                    <button type="button" wire:click="$set('showBroadcast', false)" class="h-[42px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="sendBroadcast" class="h-[42px] px-5 rounded-full bg-primary text-white font-sans text-[13px] disabled:opacity-60">
                        <span wire:loading.remove wire:target="sendBroadcast">Send to everyone</span>
                        <span wire:loading wire:target="sendBroadcast" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Sending…</span>
                    </button>
                </div>
            </form>
        </x-backoffice.modal>
    </x-backoffice.page>
</div>
