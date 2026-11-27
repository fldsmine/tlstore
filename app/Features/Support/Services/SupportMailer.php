<?php

namespace App\Features\Support\Services;

use App\Mail\Support\GuestSupportAcknowledgement;
use App\Mail\Support\GuestSupportInboxNotification;
use App\Mail\Support\GuestSupportReply;
use App\Mail\Support\SupportMail;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Email side of the support inbox.
 *
 * A guest ticket has no account, so the usual `Notification` record (and its
 * push/email observer) cannot be used: replies have to be emailed to the address
 * the visitor typed. Delivery problems are reported and swallowed — a broken
 * SMTP configuration must never lose a support request or fail a request.
 */
final class SupportMailer
{
    public function acknowledgeGuest(SupportTicket $ticket): void
    {
        $this->deliver(
            $ticket->guest_email,
            $ticket->guest_name,
            new GuestSupportAcknowledgement($this->data($ticket) + [
                'guest_name' => (string) $ticket->guest_name,
                'ticket_message' => (string) $ticket->messages()->value('message'),
            ]),
        );
    }

    public function notifyInbox(SupportTicket $ticket): void
    {
        $inbox = $this->supportEmail();

        if ($inbox === null) {
            return;
        }

        $account = $ticket->guest_email !== null
            ? User::query()->whereRaw('lower(email) = ?', [mb_strtolower((string) $ticket->guest_email)])->first(['full_name', 'username', 'email'])
            : null;

        $this->deliver($inbox, null, new GuestSupportInboxNotification($this->data($ticket) + [
            'guest_name' => (string) $ticket->guest_name,
            'guest_email' => (string) $ticket->guest_email,
            'ticket_message' => (string) $ticket->messages()->value('message'),
            'ip_address' => (string) $ticket->ip_address,
            'account_name' => (string) ($account?->full_name ?: $account?->username),
            'account_email' => (string) $account?->email,
            'reply_to' => (string) $ticket->guest_email,
            'admin_url' => $this->adminUrl($ticket),
        ]));
    }

    public function sendGuestReply(SupportTicket $ticket, SupportTicketMessage $message): void
    {
        $this->deliver(
            $ticket->guest_email,
            $ticket->guest_name,
            new GuestSupportReply($this->data($ticket) + [
                'guest_name' => (string) $ticket->guest_name,
                'ticket_message' => (string) $message->message,
            ]),
        );
    }

    /** @return array<string, mixed> */
    private function data(SupportTicket $ticket): array
    {
        return [
            'ticket_number' => (string) $ticket->ticket_number,
            'subject' => (string) $ticket->subject,
            'support_email' => (string) $this->supportEmail(),
            'app_name' => (string) config('app.name', 'TLStore'),
            'from_address' => $this->fromAddress(),
            'from_name' => $this->fromName(),
            'reply_to' => (string) $this->supportEmail(),
        ];
    }

    private function deliver(?string $to, ?string $name, SupportMail $mail): void
    {
        $address = trim((string) $to);

        if ($address === '' || filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        try {
            $recipient = trim((string) $name) !== '' ? SupportMail::headerSafe((string) $name) : null;
            Mail::mailer($this->mailerName())->to($address, $recipient)->send($mail);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function supportEmail(): ?string
    {
        $email = trim((string) config('trustlens.support_email'));

        return filter_var($email, FILTER_VALIDATE_EMAIL) === false ? null : $email;
    }

    private function adminUrl(SupportTicket $ticket): string
    {
        try {
            return route('admin.support-tickets.show', $ticket);
        } catch (\Throwable) {
            return '';
        }
    }

    private function mailerName(): string
    {
        $mailer = (string) $this->setting('notifications.email.mailer', config('mail.default', 'smtp'));

        return array_key_exists($mailer, (array) config('mail.mailers', []))
            ? $mailer
            : (string) config('mail.default', 'smtp');
    }

    private function fromAddress(): string
    {
        return (string) $this->setting('notifications.email.from_address', config('mail.from.address'));
    }

    private function fromName(): string
    {
        return (string) $this->setting('notifications.email.from_name', config('mail.from.name'));
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        return SystemSetting::query()->where('setting_key', $key)->first()?->value ?? $default;
    }
}
