<div wire:poll.30s>
    <x-backoffice.page :title="$ticket->reference()" subtitle="Support ticket conversation">
        <x-slot:action>
            @if ($ticket->status === \App\Models\SupportTicket::STATUS_CLOSED)
                <button type="button" wire:click="reopenTicket" class="inline-flex items-center gap-2 h-[42px] px-4 rounded-[12px] bg-primary text-white font-sans text-[13px] hover:opacity-90">
                    <i class="ri-refresh-line"></i> Reopen
                </button>
            @else
                <button type="button" wire:click="closeTicket" wire:confirm="Close this support ticket?" class="inline-flex items-center gap-2 h-[42px] px-4 rounded-[12px] bg-surface text-text font-sans text-[13px] hover:bg-background">
                    <i class="ri-check-line"></i> Close ticket
                </button>
            @endif
        </x-slot:action>

        <a href="{{ route('backoffice.support-tickets.index') }}" wire:navigate class="inline-flex items-center gap-2 font-sans text-[13px] text-muted hover:text-primary">
            <i class="ri-arrow-left-line"></i> All support tickets
        </a>

        <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_280px] gap-5 items-start">
            <div class="flex flex-col gap-4 min-w-0">
                <x-backoffice.card>
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h1 class="font-heading font-semibold text-text text-[20px] break-words">{{ $ticket->subject }}</h1>
                            <p class="mt-1 font-sans text-muted text-[12px]">{{ ucfirst($ticket->category) }} · Opened {{ $ticket->created_at->format('M j, Y g:i A') }}</p>
                        </div>
                        <x-backoffice.badge :tone="$ticket->status === \App\Models\SupportTicket::STATUS_CLOSED ? 'muted' : ($ticket->status === \App\Models\SupportTicket::STATUS_WAITING_ON_USER ? 'warning' : 'success')">{{ $ticket->status === \App\Models\SupportTicket::STATUS_WAITING_ON_USER ? 'Waiting on requester' : $ticket->statusLabel() }}</x-backoffice.badge>
                    </div>
                </x-backoffice.card>

                <x-backoffice.card class="flex flex-col gap-4">
                    <h2 class="font-heading font-medium text-text text-[16px]">Conversation</h2>
                    @forelse ($ticket->messages as $message)
                        @php $fromAdmin = $message->sender_type === \App\Models\SupportTicketMessage::SENDER_ADMIN; @endphp
                        <article class="max-w-[92%] rounded-[16px] px-4 py-3 {{ $fromAdmin ? 'self-end bg-primary/8 border border-primary/10' : 'self-start bg-background' }}">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="font-sans font-semibold text-text text-[13px]">{{ $fromAdmin ? ($message->sender_name ?: 'Support') : ($message->sender_name ?: $ticket->contact_name) }}</span>
                                @if ($fromAdmin)<span class="rounded-full bg-primary/10 px-2 py-0.5 font-sans text-primary text-[10px]">Support</span>@endif
                                <time class="font-sans text-muted text-[11px]" datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('M j, Y · g:i A') }}</time>
                            </div>
                            <p class="mt-2 font-sans text-text text-[14px] leading-relaxed whitespace-pre-wrap break-words">{{ $message->body }}</p>
                        </article>
                    @empty
                        <p class="font-sans text-muted text-[13px]">No messages on this ticket.</p>
                    @endforelse
                </x-backoffice.card>

                <x-backoffice.card>
                    <form wire:submit="sendReply" class="flex flex-col gap-3">
                        <label for="backoffice-ticket-reply" class="font-heading font-medium text-text text-[15px]">Reply to customer</label>
                        <textarea id="backoffice-ticket-reply" wire:model="reply" rows="5" maxlength="5000" placeholder="Write a clear and helpful response…"
                                  class="w-full rounded-[12px] bg-background border border-muted/15 px-4 py-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40 resize-y"></textarea>
                        @error('reply') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="font-sans text-muted text-[11px]">The customer will receive an email with a link to this conversation.</p>
                            <button type="submit" wire:loading.attr="disabled" wire:target="sendReply" class="inline-flex items-center gap-2 h-[44px] px-5 rounded-[12px] bg-primary text-white font-sans text-[13px] hover:opacity-90 disabled:opacity-50">
                                <span wire:loading.remove wire:target="sendReply">Send reply</span>
                                <span wire:loading wire:target="sendReply">Sending…</span>
                            </button>
                        </div>
                    </form>
                </x-backoffice.card>
            </div>

            <aside class="flex flex-col gap-4">
                <x-backoffice.card>
                    <h2 class="font-heading font-medium text-text text-[15px]">Requester</h2>
                    <p class="mt-3 font-sans font-medium text-text text-[14px]">{{ $ticket->contact_name }}</p>
                    <a href="mailto:{{ $ticket->contact_email }}" class="mt-1 block break-all font-sans text-primary text-[12px]">{{ $ticket->contact_email }}</a>
                    @if ($ticket->user)
                        <div class="mt-3 pt-3 border-t border-muted/10">
                            <span class="block font-sans text-muted text-[11px]">Account</span>
                            <a href="{{ route('backoffice.users.show', $ticket->user_id) }}" wire:navigate class="mt-1 inline-flex items-center gap-1 font-sans text-primary text-[12px]">{{ '@'.$ticket->user->username }} <i class="ri-external-link-line"></i></a>
                        </div>
                    @else
                        <span class="mt-3 inline-flex rounded-full bg-background px-2.5 py-1 font-sans text-muted text-[11px]">Guest / unlinked account</span>
                    @endif
                </x-backoffice.card>

                <x-backoffice.card>
                    <h2 class="font-heading font-medium text-text text-[15px]">Ticket details</h2>
                    <dl class="mt-3 flex flex-col gap-3">
                        <div><dt class="font-sans text-muted text-[11px]">Reference</dt><dd class="mt-0.5 font-mono text-text text-[12px]">{{ $ticket->reference() }}</dd></div>
                        <div><dt class="font-sans text-muted text-[11px]">Category</dt><dd class="mt-0.5 font-sans text-text text-[13px]">{{ ucfirst($ticket->category) }}</dd></div>
                        <div><dt class="font-sans text-muted text-[11px]">Last activity</dt><dd class="mt-0.5 font-sans text-text text-[13px]">{{ $ticket->last_message_at?->diffForHumans() ?? '—' }}</dd></div>
                    </dl>
                </x-backoffice.card>
            </aside>
        </div>
    </x-backoffice.page>
</div>
