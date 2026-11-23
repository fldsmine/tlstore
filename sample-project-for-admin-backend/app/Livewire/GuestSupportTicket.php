<?php

namespace App\Livewire;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Services\SupportTicketNotifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.public')]
#[Title('Support Ticket')]
class GuestSupportTicket extends Component
{
    #[Locked]
    public string $ticketId;

    public string $reply = '';

    public function mount(SupportTicket $ticket): void
    {
        abort_unless($this->hasSessionAccess($ticket), 404);
        $this->ticketId = $ticket->id;
    }

    public function sendReply(): void
    {
        $this->reply = trim($this->reply);
        $data = $this->validate([
            'reply' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        $ticket = $this->ticket();
        $rateLimitKey = 'support-ticket-reply:'.$ticket->id;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            $this->addError('reply', 'Please wait a moment before sending another reply.');

            return;
        }
        RateLimiter::hit($rateLimitKey, 60);
        $body = $data['reply'];

        DB::transaction(function () use ($ticket, $body): void {
            $lockedTicket = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            abort_unless($this->hasSessionAccess($lockedTicket), 404);

            $lockedTicket->messages()->create([
                'sender_type' => SupportTicketMessage::SENDER_GUEST,
                'sender_name' => $lockedTicket->contact_name,
                'body' => $body,
            ]);
            $lockedTicket->forceFill([
                'status' => SupportTicket::STATUS_OPEN,
                'last_message_at' => now(),
            ])->save();
        });

        $this->reset('reply');
        app(SupportTicketNotifier::class)->notifySupport($ticket, $body, 'Customer replied to a support ticket');
        $this->dispatch('toast', message: 'Your reply has been sent.', type: 'success');
    }

    private function ticket(): SupportTicket
    {
        $ticket = SupportTicket::query()->with('messages')->findOrFail($this->ticketId);
        abort_unless($this->hasSessionAccess($ticket), 404);

        return $ticket;
    }

    private function hasSessionAccess(SupportTicket $ticket): bool
    {
        return $ticket->user_id === null
            && session()->get($ticket->guestSessionKey()) === true;
    }

    public function render(): View
    {
        return view('livewire.guest-support-ticket', [
            'ticket' => $this->ticket(),
        ]);
    }
}
