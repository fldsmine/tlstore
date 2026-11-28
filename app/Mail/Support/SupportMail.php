<?php

namespace App\Mail\Support;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Base class for the three emails the public contact form produces: an
 * acknowledgement to the guest, a heads-up to the support inbox and an admin
 * reply sent back to the guest's own address.
 *
 * Mailables are used (rather than a raw `Mail::html()` closure) because the
 * fake mailer records them, so the feature tests can assert exactly who was
 * emailed and about which ticket.
 */
abstract class SupportMail extends Mailable
{
    use Queueable;

    /** @param array<string, mixed> $data */
    public function __construct(public readonly array $data) {}

    abstract protected function viewName(): string;

    abstract protected function subjectLine(): string;

    public function envelope(): Envelope
    {
        $fromAddress = (string) ($this->data['from_address'] ?? config('mail.from.address'));
        $fromName = (string) ($this->data['from_name'] ?? config('mail.from.name'));
        $replyTo = trim((string) ($this->data['reply_to'] ?? ''));

        return new Envelope(
            from: new Address($fromAddress, self::headerSafe($fromName)),
            // Mail headers cannot contain line breaks: a subject or name typed into
            // the public form must not be able to inject extra headers.
            subject: self::headerSafe($this->subjectLine()),
            replyTo: $replyTo !== '' ? [new Address($replyTo)] : [],
        );
    }

    public static function headerSafe(string $value): string
    {
        return trim((string) preg_replace('/[\r\n]+/', ' ', $value));
    }

    public function content(): Content
    {
        return new Content(view: $this->viewName(), with: $this->data);
    }
}
