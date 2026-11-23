<div wire:poll.20s>
    <x-backoffice.page :title="$identity->displayName()" subtitle="Telegram Mini App conversation">
        <a href="{{ route('backoffice.telegram-users.index') }}" wire:navigate class="inline-flex items-center gap-2 font-sans text-[13px] text-muted hover:text-primary">
            <i class="ri-arrow-left-line"></i> All Mini App users
        </a>

        @unless ($botConfigured)
            <div class="flex items-start gap-3 rounded-[16px] bg-[#d79937]/12 border border-[#d79937]/25 px-4 py-3.5">
                <i class="ri-error-warning-fill text-[#d79937] text-[20px] shrink-0 mt-0.5"></i>
                <p class="font-sans text-muted text-[13px]">The bot token is not set, so replies will be stored as failed until <span class="font-mono">TELEGRAM_BOT_TOKEN</span> is configured.</p>
            </div>
        @endunless

        <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_280px] gap-5 items-start">
            <div class="flex flex-col gap-4 min-w-0">
                <x-backoffice.card class="flex flex-col gap-4">
                    <h2 class="font-heading font-medium text-text text-[16px]">Conversation</h2>
                    @forelse ($messages as $message)
                        @php $fromAdmin = $message->isOutbound(); @endphp
                        <article wire:key="msg-{{ $message->id }}" class="max-w-[92%] rounded-[16px] px-4 py-3 {{ $fromAdmin ? 'self-end bg-primary/8 border border-primary/10' : 'self-start bg-background' }}">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="font-sans font-semibold text-text text-[13px]">{{ $fromAdmin ? ($message->admin?->name ?: 'Support') : $identity->displayName() }}</span>
                                @if ($fromAdmin)
                                    <span class="rounded-full bg-primary/10 px-2 py-0.5 font-sans text-primary text-[10px]">Admin</span>
                                    @if ($message->status === \App\Models\TelegramMessage::STATUS_FAILED)
                                        <x-backoffice.badge tone="danger">Not delivered</x-backoffice.badge>
                                    @endif
                                @endif
                                <time class="font-sans text-muted text-[11px]" datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('M j, Y · g:i A') }}</time>
                            </div>
                            <p class="mt-2 font-sans text-text text-[14px] leading-relaxed whitespace-pre-wrap break-words">{{ $message->body }}</p>
                        </article>
                    @empty
                        <p class="font-sans text-muted text-[13px]">No messages yet. Send one below — Telegram will deliver it if they have started the bot.</p>
                    @endforelse
                </x-backoffice.card>

                <x-backoffice.card>
                    <form wire:submit="sendReply" class="flex flex-col gap-3">
                        <label for="backoffice-telegram-reply" class="font-heading font-medium text-text text-[15px]">Message this user</label>
                        <textarea id="backoffice-telegram-reply" wire:model="reply" rows="5" maxlength="4000" placeholder="Write a reply. It is delivered as a Telegram bot DM…"
                                  class="w-full rounded-[12px] bg-background border border-muted/15 px-4 py-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40 resize-y"></textarea>
                        @error('reply') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="font-sans text-muted text-[11px]">Bots can only message people who have opened this bot at least once.</p>
                            <button type="submit" wire:loading.attr="disabled" wire:target="sendReply" class="inline-flex items-center gap-2 h-[44px] px-5 rounded-[12px] bg-primary text-white font-sans text-[13px] hover:opacity-90 disabled:opacity-50">
                                <span wire:loading.remove wire:target="sendReply">Send on Telegram</span>
                                <span wire:loading wire:target="sendReply">Sending…</span>
                            </button>
                        </div>
                    </form>
                </x-backoffice.card>
            </div>

            <aside class="flex flex-col gap-4">
                <x-backoffice.card>
                    <h2 class="font-heading font-medium text-text text-[15px]">Telegram</h2>
                    <dl class="mt-3 flex flex-col gap-3">
                        <div>
                            <dt class="font-sans text-muted text-[11px]">Name</dt>
                            <dd class="mt-0.5 font-sans text-text text-[13px]">{{ $identity->displayName() }}</dd>
                        </div>
                        <div>
                            <dt class="font-sans text-muted text-[11px]">Handle</dt>
                            <dd class="mt-0.5 font-sans text-text text-[13px]">{{ $identity->handle() ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="font-sans text-muted text-[11px]">Telegram user ID</dt>
                            <dd class="mt-0.5 font-mono text-text text-[12px]">{{ $identity->telegram_user_id }}</dd>
                        </div>
                        <div>
                            <dt class="font-sans text-muted text-[11px]">Language</dt>
                            <dd class="mt-0.5 font-sans text-text text-[13px]">{{ $identity->language_code ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="font-sans text-muted text-[11px]">Linked</dt>
                            <dd class="mt-0.5 font-sans text-text text-[13px]">{{ $identity->created_at?->format('j M Y, g:i A') }}</dd>
                        </div>
                    </dl>
                </x-backoffice.card>

                <x-backoffice.card>
                    <h2 class="font-heading font-medium text-text text-[15px]">Nextel account</h2>
                    @if ($identity->user)
                        <p class="mt-3 font-sans font-medium text-text text-[14px]">{{ $identity->user->full_name }}</p>
                        <p class="mt-0.5 font-sans text-muted text-[12px]">{{ '@'.$identity->user->username }}</p>
                        <p class="mt-0.5 font-sans text-muted text-[12px] break-all">{{ $identity->user->email }}</p>
                        <p class="mt-2 font-sans text-muted text-[12px]">{{ $identity->user->package?->name ?? 'No package' }}</p>
                        <a href="{{ route('backoffice.users.show', $identity->user) }}" wire:navigate class="mt-3 inline-flex items-center gap-1 font-sans text-primary text-[12px]">Open profile <i class="ri-external-link-line"></i></a>
                    @else
                        <p class="mt-3 font-sans text-muted text-[13px]">The Nextel account for this identity is gone.</p>
                    @endif
                </x-backoffice.card>
            </aside>
        </div>
    </x-backoffice.page>
</div>
