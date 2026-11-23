<?php

namespace App\Livewire\Dashboard\Account;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Services\SupportTicketNotifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Help & Support')]
class SupportTickets extends Component
{
    use WithPagination;

    #[Url(as: 'ticket')]
    public ?string $selectedTicketId = null;

    public string $subject = '';

    public string $category = 'general';

    public string $newMessage = '';

    public string $reply = '';

    public function selectTicket(string $id): void
    {
        $this->ownedTicket($id);
        $this->selectedTicketId = $id;
        $this->reset('reply');
    }

    public function clearSelection(): void
    {
        $this->selectedTicketId = null;
        $this->reset('reply');
    }

    public function createTicket(): void
    {
        $this->subject = trim($this->subject);
        $this->newMessage = trim($this->newMessage);
        $data = $this->validate([
            'subject' => ['required', 'string', 'min:4', 'max:160'],
            'category' => ['required', 'in:general,account,payments,technical'],
            'newMessage' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        $user = auth()->user();
        abort_unless($user !== null, 401);
        $rateLimitKey = 'support-ticket-open-user:'.$user->id;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $this->addError('newMessage', 'Please wait a moment before opening another support ticket.');

            return;
        }
        RateLimiter::hit($rateLimitKey, 60);
        $body = trim($data['newMessage']);

        $ticket = DB::transaction(function () use ($user, $data, $body): SupportTicket {
            $ticket = $user->supportTickets()->create([
                'contact_name' => $user->full_name ?: $user->username,
                'contact_email' => $user->email,
                'subject' => trim($data['subject']),
                'category' => $data['category'],
                'status' => SupportTicket::STATUS_OPEN,
                'last_message_at' => now(),
            ]);

            $ticket->messages()->create([
                'user_id' => $user->id,
                'sender_type' => SupportTicketMessage::SENDER_USER,
                'sender_name' => $user->full_name ?: $user->username,
                'body' => $body,
            ]);

            return $ticket;
        });

        app(SupportTicketNotifier::class)->notifySupport($ticket, $body);
        $this->selectedTicketId = $ticket->id;
        $this->reset('subject', 'category', 'newMessage');
        $this->category = 'general';
        $this->dispatch('toast', message: 'Your support ticket has been opened.', type: 'success');
    }

    public function sendReply(): void
    {
        $this->reply = trim($this->reply);
        $data = $this->validate([
            'reply' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        $user = auth()->user();
        abort_unless($user !== null, 401);
        $rateLimitKey = 'support-ticket-reply-user:'.$user->id;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            $this->addError('reply', 'Please wait a moment before sending another reply.');

            return;
        }
        RateLimiter::hit($rateLimitKey, 60);
        $body = trim($data['reply']);
        $ticketId = (string) $this->selectedTicketId;

        $ticket = DB::transaction(function () use ($user, $ticketId, $body): SupportTicket {
            $ticket = $user->supportTickets()->lockForUpdate()->findOrFail($ticketId);
            $ticket->messages()->create([
                'user_id' => $user->id,
                'sender_type' => SupportTicketMessage::SENDER_USER,
                'sender_name' => $user->full_name ?: $user->username,
                'body' => $body,
            ]);
            $ticket->forceFill([
                'status' => SupportTicket::STATUS_OPEN,
                'last_message_at' => now(),
            ])->save();

            return $ticket;
        });

        $this->reset('reply');
        app(SupportTicketNotifier::class)->notifySupport($ticket, $body, 'Customer replied to a support ticket');
        $this->dispatch('toast', message: 'Your reply has been sent.', type: 'success');
    }

    private function ownedTicket(string $id): SupportTicket
    {
        $user = auth()->user();
        abort_unless($user !== null, 401);

        return $user->supportTickets()->findOrFail($id);
    }

    public function render(): View
    {
        $user = auth()->user();
        abort_unless($user !== null, 401);

        $tickets = $user->supportTickets()
            ->with('latestMessage')
            ->latest('last_message_at')
            ->paginate(8);

        $selectedTicket = $this->selectedTicketId !== null
            ? $user->supportTickets()->with('messages')->find($this->selectedTicketId)
            : null;

        if ($this->selectedTicketId !== null && $selectedTicket === null) {
            $this->selectedTicketId = null;
        }

        return view('livewire.dashboard.account.support-tickets', [
            'tickets' => $tickets,
            'selectedTicket' => $selectedTicket,
        ]);
    }
}
