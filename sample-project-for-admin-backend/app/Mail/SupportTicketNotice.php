<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SupportTicketNotice extends BaseMail
{
    public function __construct(
        public string $ticketReference,
        public string $ticketSubject,
        public string $heading,
        public string $intro,
        public string $body,
        public string $actionUrl,
        public string $actionLabel,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->heading.' — '.$this->ticketReference);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.support-ticket-notice',
            with: [
                'ticketReference' => $this->ticketReference,
                'ticketSubject' => $this->ticketSubject,
                'heading' => $this->heading,
                'intro' => $this->intro,
                'body' => $this->body,
                'actionUrl' => $this->actionUrl,
                'actionLabel' => $this->actionLabel,
            ],
        );
    }
}
