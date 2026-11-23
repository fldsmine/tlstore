<div class="min-h-screen bg-background px-4 py-8 md:py-12" wire:poll.30s>
    <div class="mx-auto max-w-[760px] flex flex-col gap-6">
        <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 font-sans text-[13px] text-muted hover:text-primary">
            <i class="ri-arrow-left-line"></i> Contact support
        </a>

        <section class="rounded-[22px] bg-surface p-5 md:p-7 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="font-sans text-[12px] uppercase tracking-wide text-muted">{{ $ticket->reference() }} · {{ ucfirst($ticket->category) }}</p>
                    <h1 class="mt-1 font-heading font-semibold text-text text-[22px] md:text-[26px] break-words">{{ $ticket->subject }}</h1>
                    <p class="mt-1 font-sans text-muted text-[13px]">Opened by {{ $ticket->contact_name }}</p>
                </div>
                <span class="shrink-0 rounded-full px-3 py-1.5 font-sans text-[12px] {{ $ticket->status === \App\Models\SupportTicket::STATUS_CLOSED ? 'bg-muted/10 text-muted' : ($ticket->status === \App\Models\SupportTicket::STATUS_WAITING_ON_USER ? 'bg-warning/15 text-warning' : 'bg-primary/10 text-primary') }}">
                    {{ $ticket->statusLabel() }}
                </span>
            </div>
        </section>

        <section class="rounded-[22px] bg-surface p-4 md:p-6 shadow-sm flex flex-col gap-4">
            <h2 class="font-heading font-medium text-text text-[17px]">Conversation</h2>

            @forelse ($ticket->messages as $message)
                @php $fromSupport = $message->sender_type === \App\Models\SupportTicketMessage::SENDER_ADMIN; @endphp
                <article class="max-w-[92%] rounded-[18px] px-4 py-3 {{ $fromSupport ? 'self-start bg-primary/8 border border-primary/10' : 'self-end bg-background' }}">
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span class="font-sans font-semibold text-text text-[13px]">{{ $fromSupport ? ($message->sender_name ?: 'Nextel Support') : 'You' }}</span>
                        <time class="font-sans text-muted text-[11px]" datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('M j, Y · g:i A') }}</time>
                    </div>
                    <p class="mt-2 font-sans text-text text-[14px] leading-relaxed whitespace-pre-wrap break-words">{{ $message->body }}</p>
                </article>
            @empty
                <p class="font-sans text-muted text-[13px]">No messages yet.</p>
            @endforelse
        </section>

        <form wire:submit="sendReply" class="rounded-[22px] bg-surface p-4 md:p-6 shadow-sm flex flex-col gap-3">
            <label for="guest-ticket-reply" class="font-heading font-medium text-text text-[16px]">Reply to support</label>
            <textarea id="guest-ticket-reply" wire:model="reply" rows="4" maxlength="5000"
                      placeholder="Write your reply…"
                      class="w-full rounded-[14px] bg-background border border-muted/15 px-4 py-3 font-sans text-[14px] text-text placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-primary/30 resize-y"></textarea>
            @error('reply') <span class="font-sans text-danger text-[12px]">{{ $message }}</span> @enderror
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="font-sans text-muted text-[12px]">Replies to a closed ticket will reopen it.</p>
                <button type="submit" wire:loading.attr="disabled" wire:target="sendReply"
                        class="inline-flex items-center gap-2 rounded-full bg-primary px-5 h-[44px] text-white font-sans font-semibold text-[13px] hover:opacity-90 disabled:opacity-50">
                    <span wire:loading.remove wire:target="sendReply">Send reply</span>
                    <span wire:loading wire:target="sendReply">Sending…</span>
                </button>
            </div>
        </form>

        <p class="text-center font-sans text-muted text-[12px]">This private ticket link is the key to your conversation. Keep it in your email and don't share it.</p>
    </div>
</div>
