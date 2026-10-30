<div class="min-h-screen bg-background" @if ($selectedTicket) wire:poll.30s @endif>
    <div class="mx-auto w-full max-w-[760px] px-4 md:px-8 pb-[120px] lg:pb-12 flex flex-col gap-5">
        <x-app.app-bar :title="$selectedTicket ? 'Support ticket' : 'Help & Support'" />

        @if ($selectedTicket)
            <button type="button" wire:click="clearSelection" class="self-start inline-flex items-center gap-2 font-sans text-[13px] text-muted hover:text-primary">
                <i class="ri-arrow-left-line"></i> All tickets
            </button>

            <section class="rounded-[20px] bg-surface p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="font-sans text-muted text-[12px]">{{ $selectedTicket->reference() }} · {{ ucfirst($selectedTicket->category) }}</p>
                        <h1 class="mt-1 font-heading font-semibold text-text text-[21px] break-words">{{ $selectedTicket->subject }}</h1>
                        <p class="mt-1 font-sans text-muted text-[12px]">Updated {{ $selectedTicket->last_message_at?->diffForHumans() ?? $selectedTicket->created_at->diffForHumans() }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1.5 font-sans text-[12px] {{ $selectedTicket->status === \App\Models\SupportTicket::STATUS_CLOSED ? 'bg-muted/10 text-muted' : ($selectedTicket->status === \App\Models\SupportTicket::STATUS_WAITING_ON_USER ? 'bg-warning/15 text-warning' : 'bg-primary/10 text-primary') }}">
                        {{ $selectedTicket->statusLabel() }}
                    </span>
                </div>
            </section>

            <section class="rounded-[20px] bg-surface p-4 md:p-5 shadow-sm flex flex-col gap-4">
                <h2 class="font-heading font-medium text-text text-[16px]">Conversation</h2>
                @forelse ($selectedTicket->messages as $message)
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

            <form wire:submit="sendReply" class="rounded-[20px] bg-surface p-4 md:p-5 shadow-sm flex flex-col gap-3">
                <label for="support-reply" class="font-heading font-medium text-text text-[15px]">Your reply</label>
                <textarea id="support-reply" wire:model="reply" rows="4" maxlength="5000" placeholder="Add information or reply to support…"
                          class="w-full rounded-[14px] bg-background border border-muted/15 px-4 py-3 font-sans text-[14px] text-text placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-primary/30 resize-y"></textarea>
                @error('reply') <span class="font-sans text-danger text-[12px]">{{ $message }}</span> @enderror
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="font-sans text-muted text-[12px]">Replying reopens a closed ticket.</p>
                    <button type="submit" wire:loading.attr="disabled" wire:target="sendReply"
                            class="inline-flex items-center gap-2 rounded-full bg-primary px-5 h-[44px] text-white font-sans font-semibold text-[13px] hover:opacity-90 disabled:opacity-50">
                        <span wire:loading.remove wire:target="sendReply">Send reply</span>
                        <span wire:loading wire:target="sendReply">Sending…</span>
                    </button>
                </div>
            </form>
        @else
            <section class="rounded-[20px] bg-surface p-5 md:p-6 shadow-sm">
                <div class="mb-4">
                    <h1 class="font-heading font-semibold text-text text-[21px]">Open a support ticket</h1>
                    <p class="mt-1 font-sans text-muted text-[13px]">Tell us what happened. You can follow every reply here.</p>
                </div>

                <form wire:submit="createTicket" class="flex flex-col gap-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1.5">
                            <label for="ticket-subject" class="font-sans text-muted text-[12px]">Subject</label>
                            <input id="ticket-subject" type="text" wire:model="subject" maxlength="160" placeholder="Briefly describe the issue"
                                   class="h-[46px] rounded-[12px] bg-background border border-muted/15 px-3.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/30">
                            @error('subject') <span class="font-sans text-danger text-[12px]">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label for="ticket-category" class="font-sans text-muted text-[12px]">Category</label>
                            <select id="ticket-category" wire:model="category"
                                    class="h-[46px] rounded-[12px] bg-background border border-muted/15 px-3.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/30">
                                <option value="general">General enquiry</option>
                                <option value="account">Account access</option>
                                <option value="payments">Payments</option>
                                <option value="technical">Technical issue</option>
                            </select>
                            @error('category') <span class="font-sans text-danger text-[12px]">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="ticket-message" class="font-sans text-muted text-[12px]">Message</label>
                        <textarea id="ticket-message" wire:model="newMessage" rows="5" maxlength="5000" placeholder="Include details that will help our team assist you…"
                                  class="w-full rounded-[14px] bg-background border border-muted/15 px-4 py-3 font-sans text-[14px] text-text placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-primary/30 resize-y"></textarea>
                        @error('newMessage') <span class="font-sans text-danger text-[12px]">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" wire:loading.attr="disabled" wire:target="createTicket"
                                class="inline-flex items-center gap-2 rounded-full bg-primary px-5 h-[46px] text-white font-sans font-semibold text-[13px] hover:opacity-90 disabled:opacity-50">
                            <span wire:loading.remove wire:target="createTicket">Open ticket</span>
                            <span wire:loading wire:target="createTicket">Opening…</span>
                        </button>
                    </div>
                </form>
            </section>

            <section class="flex flex-col gap-3">
                <div class="flex items-end justify-between gap-3">
                    <div>
                        <h2 class="font-heading font-medium text-text text-[18px]">Your tickets</h2>
                        <p class="font-sans text-muted text-[12px]">Replies from our team appear in each conversation.</p>
                    </div>
                    <a href="{{ route('contact') }}" class="shrink-0 font-sans text-primary text-[12px] underline">Contact options</a>
                </div>

                @forelse ($tickets as $ticket)
                    <button type="button" wire:click="selectTicket('{{ $ticket->id }}')"
                            class="w-full text-left rounded-[18px] bg-surface p-4 shadow-sm hover:shadow-md transition-shadow">
                        <span class="flex items-start justify-between gap-3">
                            <span class="min-w-0">
                                <span class="block font-heading font-medium text-text text-[15px] truncate">{{ $ticket->subject }}</span>
                                <span class="mt-1 block font-sans text-muted text-[12px]">{{ $ticket->reference() }} · {{ ucfirst($ticket->category) }}</span>
                            </span>
                            <span class="shrink-0 rounded-full px-2.5 py-1 font-sans text-[11px] {{ $ticket->status === \App\Models\SupportTicket::STATUS_CLOSED ? 'bg-muted/10 text-muted' : ($ticket->status === \App\Models\SupportTicket::STATUS_WAITING_ON_USER ? 'bg-warning/15 text-warning' : 'bg-primary/10 text-primary') }}">
                                {{ $ticket->statusLabel() }}
                            </span>
                        </span>
                        @if ($ticket->latestMessage)
                            <span class="mt-3 block font-sans text-muted text-[12px] line-clamp-2">{{ $ticket->latestMessage->body }}</span>
                            <span class="mt-2 block font-sans text-muted/70 text-[11px]">Updated {{ $ticket->last_message_at?->diffForHumans() }}</span>
                        @endif
                    </button>
                @empty
                    <div class="rounded-[18px] bg-surface p-5 text-center">
                        <i class="ri-customer-service-2-line text-primary text-[24px]"></i>
                        <p class="mt-2 font-sans text-muted text-[13px]">You haven't opened a support ticket yet.</p>
                    </div>
                @endforelse

                @if ($tickets->hasPages())
                    <div>{{ $tickets->links() }}</div>
                @endif
            </section>
        @endif
    </div>
    <x-app.bottom-nav />
</div>
