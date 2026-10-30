<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\HtmlString;

class MailingCampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $campaignSubject,
        public string $htmlBody,
        public string $textBody,
        public ?string $replyToAddress = null,
    ) {}

    public function envelope(): Envelope
    {
        $replyTo = $this->replyToAddress !== null
            ? [new Address($this->replyToAddress)]
            : [];

        return new Envelope(
            subject: $this->campaignSubject,
            replyTo: $replyTo,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.mailing-campaign',
            text: 'emails.mailing-campaign-text',
            with: [
                'bodyHtml' => new HtmlString($this->htmlBody),
                'bodyText' => $this->textBody,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
