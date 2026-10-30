<?php

namespace App\Livewire\Backoffice\Support;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Services\SupportTicketNotifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.backoffice')]
#[Title('Support Ticket — Backoffice')]
class ShowSupportTicket extends Component
{
    public string $ticketId;

    public string $reply = '';

    public function mount(SupportTicket $ticket): void
    {
        $this->ticketId = $ticket->id;
    }

    public function sendReply(): void
    {
        $this->reply = trim($this->reply);
        $data = $this->validate([
            'reply' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        $admin = auth('admin')->user();
        abort_unless($admin !== null, 403);
        $body = $data['reply'];

        $ticket = DB::transaction(function () use ($admin, $body): SupportTicket {
            $ticket = SupportTicket::query()->lockForUpdate()->findOrFail($this->ticketId);
            $ticket->messages()->create([
                'admin_id' => $admin->id,
                'sender_type' => SupportTicketMessage::SENDER_ADMIN,
                'sender_name' => $admin->name,
                'body' => $body,
            ]);
            $ticket->forceFill([
                'status' => SupportTicket::STATUS_WAITING_ON_USER,
                'last_message_at' => now(),
            ])->save();

            return $ticket;
        });

        $this->reset('reply');
        app(SupportTicketNotifier::class)->notifyRequester($ticket, $body, 'Nextel Support replied to your ticket');
        $this->dispatch('toast', message: 'Your reply was sent to the customer.', type: 'success');
    }

    public function closeTicket(): void
    {
        $ticket = $this->ticket();
        $ticket->forceFill(['status' => SupportTicket::STATUS_CLOSED])->save();
        $this->dispatch('toast', message: 'Ticket closed.', type: 'success');
    }

    public function reopenTicket(): void
    {
        $ticket = $this->ticket();
        $ticket->forceFill(['status' => SupportTicket::STATUS_OPEN])->save();
        $this->dispatch('toast', message: 'Ticket reopened.', type: 'success');
    }

    private function ticket(): SupportTicket
    {
        abort_unless(auth('admin')->check(), 403);

        return SupportTicket::query()->with(['user', 'messages'])->findOrFail($this->ticketId);
    }

    public function render(): View
    {
        return view('livewire.backoffice.support.show-support-ticket', [
            'ticket' => $this->ticket(),
        ]);
    }
}
