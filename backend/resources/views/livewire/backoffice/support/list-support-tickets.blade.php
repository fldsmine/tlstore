<div>
    <x-backoffice.page title="Support Tickets" subtitle="Customer questions and replies">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[360px]">
                <i class="ri-search-line text-muted text-[17px]"></i>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Subject, name, email, or reference…"
                       class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
            </div>
            <div class="flex items-center gap-1.5 flex-wrap">
                @foreach ($filters as $key => $label)
                    <button type="button" wire:click="setStatus('{{ $key }}')"
                            class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $status === $key ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">
                        {{ $label }}
                        @if ($key !== 'all') <span class="opacity-70">({{ number_format($counts->get($key, 0)) }})</span> @endif
                    </button>
                @endforeach
            </div>
        </div>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($tickets->isEmpty())
                <div class="p-10 text-center font-sans text-muted text-[14px]">No support tickets match this filter.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">Ticket</th>
                                <th class="px-5 py-3 font-medium">Requester</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 font-medium">Last activity</th>
                                <th class="px-5 py-3 font-medium text-right"> </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tickets as $ticket)
                                <tr wire:key="ticket-{{ $ticket->id }}" class="border-b border-muted/8 last:border-0 hover:bg-background/50">
                                    <td class="px-5 py-4 max-w-[380px]">
                                        <a href="{{ route('backoffice.support-tickets.show', $ticket->id) }}" wire:navigate class="block font-heading font-medium text-text text-[14px] hover:text-primary truncate">{{ $ticket->subject }}</a>
                                        <span class="mt-1 block font-sans text-muted text-[11px]">{{ $ticket->reference() }} · {{ ucfirst($ticket->category) }}</span>
                                        @if ($ticket->latestMessage)
                                            <span class="mt-1 block font-sans text-muted text-[12px] truncate">{{ $ticket->latestMessage->body }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="block font-sans text-text text-[13px]">{{ $ticket->contact_name }}</span>
                                        <a href="mailto:{{ $ticket->contact_email }}" class="block font-sans text-muted text-[11px] hover:text-primary">{{ $ticket->contact_email }}</a>
                                        <span class="mt-1 inline-flex rounded-full px-2 py-0.5 bg-background font-sans text-muted text-[10px]">{{ $ticket->user_id ? 'Account' : 'Guest' }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <x-backoffice.badge :tone="$ticket->status === \App\Models\SupportTicket::STATUS_CLOSED ? 'muted' : ($ticket->status === \App\Models\SupportTicket::STATUS_WAITING_ON_USER ? 'warning' : 'success')">{{ $ticket->status === \App\Models\SupportTicket::STATUS_WAITING_ON_USER ? 'Waiting on requester' : $ticket->statusLabel() }}</x-backoffice.badge>
                                    </td>
                                    <td class="px-5 py-4 font-sans text-muted text-[12px]">
                                        {{ $ticket->last_message_at?->format('M j, Y g:i A') ?? $ticket->created_at->format('M j, Y g:i A') }}
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <a href="{{ route('backoffice.support-tickets.show', $ticket->id) }}" wire:navigate class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-3 h-[34px] font-sans font-medium text-primary text-[12px] hover:bg-primary/15">Open <i class="ri-arrow-right-line"></i></a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($tickets->hasPages())
                    <div class="px-5 py-4 border-t border-muted/10">{{ $tickets->links() }}</div>
                @endif
            @endif
        </x-backoffice.card>
    </x-backoffice.page>
</div>
