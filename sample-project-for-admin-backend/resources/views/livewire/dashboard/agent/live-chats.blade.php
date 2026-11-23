<div>
    @php
        $card = 'bg-surface rounded-[24px] shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]';
        $supportTelegram = config('settings.contact.telegram_activation');
        $onlineSeconds = (int) config('settings.cloud.online_window_seconds', 60);
    @endphp

    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[98px] lg:pb-10 flex flex-col gap-5" wire:poll.10s="poll">
        <x-app.app-bar :title="'Live Chats'" :subtitle="'Chat with customers buying from you'" />

        <div class="{{ $card }} overflow-hidden flex flex-col lg:flex-row h-[calc(100vh-178px)] min-h-[500px] lg:h-[calc(100vh-150px)]">

            <aside class="lg:w-[320px] lg:shrink-0 lg:border-r border-background min-h-0 {{ $chat ? 'hidden lg:flex' : 'flex' }} flex-col">
                <div class="p-3 border-b border-background shrink-0">
                    <div class="relative">
                        <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-muted text-[15px]"></i>
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search customers…" class="w-full h-10 rounded-full bg-background pl-9 pr-3 font-sans text-[12.5px] text-text focus:outline-none focus:ring-2 focus:ring-primary/20">
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto min-h-0 p-2 flex flex-col gap-1">
                    @forelse ($conversations as $c)
                        <button type="button" wire:key="conv-{{ $c->id }}" wire:click="openChat('{{ $c->id }}')"
                                class="flex items-center gap-3 rounded-[14px] px-2.5 py-2.5 hover:bg-background transition text-left {{ $chat && $chat->id === $c->id ? 'bg-primary/[0.06]' : '' }}">
                            <div class="relative shrink-0">
                                <x-app.chat-avatar :user="$c->customer" :size="38" />
                                @if (($unread[$c->id] ?? 0) > 0)<span class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-danger text-white text-[10px] font-bold flex items-center justify-center">{{ $unread[$c->id] > 9 ? '9+' : $unread[$c->id] }}</span>@endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-sans font-semibold text-text text-[13px] truncate">{{ $c->customer->full_name }}</p>
                                <p class="font-sans text-muted text-[11px] truncate">&#64;{{ $c->customer->username }}</p>
                            </div>
                            <p class="font-sans text-muted text-[10px] shrink-0">{{ ($c->last_message_at ?? $c->created_at)->format('d M, g:i A') }}</p>
                        </button>
                    @empty
                        <div class="text-center py-12 flex flex-col items-center gap-2">
                            <span class="size-11 rounded-full bg-background inline-flex items-center justify-center text-muted"><i class="ri-chat-3-line text-[20px]"></i></span>
                            <p class="font-sans text-muted text-[12px]">No conversations yet.<br>Customers who message you appear here.</p>
                        </div>
                    @endforelse
                    @if ($hasMore)
                        <div wire:intersect="loadMore" class="flex justify-center py-3">
                            <span class="size-5 rounded-full border-2 border-primary/30 border-t-primary animate-spin"></span>
                        </div>
                    @endif
                </div>
            </aside>

            <section class="flex-1 min-w-0 min-h-0 {{ $chat ? 'flex' : 'hidden lg:flex' }} flex-col">
                @if ($chat)
                    @php
                        $seen = $chat->customer->last_seen_at;
                        $custOnline = $seen && $seen->gt(now()->subSeconds($onlineSeconds));
                    @endphp

                    <div class="flex items-center gap-3 px-4 py-3 border-b border-background shrink-0">
                        <button type="button" wire:click="close" class="lg:hidden size-9 rounded-full bg-background flex items-center justify-center text-text shrink-0"><i class="ri-arrow-left-s-line text-[22px]"></i></button>
                        <div class="relative shrink-0">
                            <x-app.chat-avatar :user="$chat->customer" :size="42" />
                            <span class="absolute bottom-0 right-0 size-3 rounded-full border-2 border-surface {{ $custOnline ? 'bg-success' : 'bg-muted/40' }}"></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-heading font-medium text-text text-[15px] truncate">{{ $chat->customer->full_name }}</p>
                            <p class="font-sans text-muted text-[11px] truncate mt-0.5">{{ $custOnline ? 'Online' : ($seen ? 'Last seen '.$seen->format('d M, g:i A') : '@'.$chat->customer->username) }}</p>
                        </div>
                        @if ($chat->customer->telegram_url || $supportTelegram)
                            <div x-data="{ open: false }" class="relative shrink-0">
                                <button type="button" @click="open = !open" class="size-9 rounded-full bg-background flex items-center justify-center text-text hover:bg-primary/10 transition"><i class="ri-more-2-fill text-[18px]"></i></button>
                                <div x-show="open" x-cloak x-transition.origin.top.right @click.away="open = false"
                                     class="absolute right-0 mt-2 w-56 rounded-[16px] bg-surface shadow-[0_10px_30px_rgba(0,0,0,0.12)] border border-background p-1.5 z-30">
                                    @if ($chat->customer->telegram_url)
                                        <a href="{{ $chat->customer->telegram_url }}" target="_blank" rel="noopener" class="flex items-center gap-2.5 rounded-[11px] px-3 py-2.5 hover:bg-background transition">
                                            <i class="ri-telegram-2-fill text-[#229ED9] text-[18px]"></i><span class="font-sans text-[12.5px] text-text">Message on Telegram</span>
                                        </a>
                                    @endif
                                    <a href="{{ $supportTelegram }}" target="_blank" rel="noopener" class="flex items-center gap-2.5 rounded-[11px] px-3 py-2.5 hover:bg-background transition">
                                        <i class="ri-customer-service-2-line text-primary text-[18px]"></i><span class="font-sans text-[12.5px] text-text">Contact Nextel Support</span>
                                    </a>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="flex-1 min-h-0 relative" x-data="chatThread">
                        <div x-ref="scroll" data-count="{{ $chat->messages->count() }}" class="h-full overflow-y-auto bg-background px-4 py-4 flex flex-col gap-1.5">
                        @foreach ($chat->messages as $m)
                            @php $s = $m->sender->value; $t = $m->type->value; @endphp
                            <div wire:key="cm-{{ $m->id }}">
                                @if ($s === 'system')
                                    <div class="flex justify-center py-1"><p class="font-sans text-[11px] text-muted/90 text-center leading-snug max-w-[85%]">{{ $m->body }}</p></div>
                                @else
                                    @php $mine = $s === 'agent'; @endphp
                                    <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                                        <div class="max-w-[82%] flex flex-col {{ $mine ? 'items-end' : 'items-start' }}">
                                            <div class="{{ $mine ? 'bg-primary text-surface rounded-br-[6px]' : 'bg-surface text-text rounded-bl-[6px] shadow-[0_2px_6px_rgba(150,150,150,0.08)]' }} rounded-[18px] px-3.5 py-2.5">
                                                @if ($t === 'image' && $m->attachment_url)
                                                    <button type="button" @click="$dispatch('open-image', { url: @js($m->attachment_url) })" class="block"><img src="{{ $m->attachment_url }}" class="rounded-[12px] max-h-56 w-auto object-cover" alt="Photo"></button>
                                                @elseif ($t === 'file' && $m->attachment_url)
                                                    <a href="{{ $m->attachment_url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-[10px] {{ $mine ? 'bg-surface/20' : 'bg-background' }} px-3 py-2 text-[12.5px]">
                                                        <i class="ri-file-pdf-2-line text-[18px] {{ $mine ? '' : 'text-danger' }}"></i>
                                                        <span class="truncate max-w-[180px]">{{ $m->attachment_name }}</span>
                                                    </a>
                                                @else
                                                    <p class="font-sans text-[13px] leading-relaxed whitespace-pre-line">{{ $m->body }}</p>
                                                @endif
                                            </div>
                                            <span class="font-sans text-[9.5px] text-muted mt-1 {{ $mine ? 'mr-1' : 'ml-1' }}">{{ $m->created_at->format('g:i A') }}</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        <div wire:ignore>
                            <div x-data="chatTyping('{{ $chat->id }}', 'agent')" x-show="typing" x-cloak class="flex justify-start mt-1.5">
                                <div class="bg-surface rounded-[18px] rounded-bl-[6px] px-4 py-3 shadow-[0_2px_6px_rgba(150,150,150,0.08)]">
                                    <span class="flex gap-1 items-center"><span class="size-1.5 rounded-full bg-muted/60 animate-bounce"></span><span class="size-1.5 rounded-full bg-muted/60 animate-bounce [animation-delay:0.15s]"></span><span class="size-1.5 rounded-full bg-muted/60 animate-bounce [animation-delay:0.3s]"></span></span>
                                </div>
                            </div>
                        </div>
                        </div>

                        <button type="button" x-show="hasNew" x-cloak x-transition @click="scrollBottom()"
                                class="absolute bottom-3 left-1/2 -translate-x-1/2 z-10 h-8 px-3.5 rounded-full bg-primary text-surface font-sans font-semibold text-[12px] shadow-[0_4px_14px_rgba(0,0,0,0.18)] inline-flex items-center gap-1.5">New messages <i class="ri-arrow-down-line text-[14px]"></i></button>
                    </div>

                    <x-app.chat-composer :attachment="$attachment" />
                @else
                    <div class="flex-1 flex flex-col items-center justify-center text-center gap-4 px-6 py-20">
                        <span class="size-16 rounded-full bg-background inline-flex items-center justify-center text-primary"><i class="ri-chat-smile-2-line text-[28px]"></i></span>
                        <div>
                            <p class="font-heading font-medium text-text text-[16px]">Your customer chats</p>
                            <p class="font-sans text-muted text-[13px] max-w-[300px] mt-1">Open a conversation from the list to reply.</p>
                        </div>
                    </div>
                @endif
            </section>
        </div>
    </div>

    <x-app.bottom-nav />
</div>
