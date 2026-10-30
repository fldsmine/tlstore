<?php

namespace App\Services;

use App\Mail\SupportTicketNotice;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SupportTicketNotifier
{
    public function notifySupport(SupportTicket $ticket, string $body, string $heading = 'New support ticket'): void
    {
        $email = trim((string) config('settings.contact.email'));

        if ($email === '') {
            return;
        }

        $this->queue(
            $email,
            $ticket,
            $heading,
            'A support ticket needs attention.',
            $body,
            route('backoffice.support-tickets.show', $ticket->id),
            'Open ticket in backoffice',
        );
    }

    public function notifyRequester(SupportTicket $ticket, string $body, string $heading): void
    {
        $email = trim($ticket->contact_email);

        if ($email === '') {
            return;
        }

        $this->queue(
            $email,
            $ticket,
            $heading,
            'There is an update to your Nextel support ticket. Open Help & Support in the Nextel app to view the conversation.',
            $body,
            route('support.tickets', ['ticket' => $ticket->id]),
            'View ticket conversation',
        );
    }

    private function queue(
        string $email,
        SupportTicket $ticket,
        string $heading,
        string $intro,
        string $body,
        string $actionUrl,
        string $actionLabel,
    ): void {
        try {
            Mail::to($email)->queue(new SupportTicketNotice(
                ticketReference: $ticket->reference(),
                ticketSubject: $ticket->subject,
                heading: $heading,
                intro: $intro,
                body: $body,
                actionUrl: $actionUrl,
                actionLabel: $actionLabel,
            ));
        } catch (Throwable $exception) {
            Log::warning('Support ticket email could not be queued.', [
                'ticket_id' => $ticket->id,
                'recipient' => $email,
                'exception' => $exception::class,
            ]);
        }
    }
}
