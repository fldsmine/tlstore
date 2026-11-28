<?php

namespace App\Application\Actions\Support;

use App\Features\Support\Services\SupportMailer;
use App\Models\SupportTicket;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Public contact form submission.
 *
 * Anyone can submit one, signed in or not, so the ticket is *never* attached to
 * an account automatically — typing a registered email address must not let a
 * stranger create messages inside someone else's ticket list. Support still sees
 * the matching account in the inbox notification and can act on it.
 */
final class SubmitGuestSupportTicket
{
    public function __construct(private readonly SupportMailer $mailer) {}

    /**
     * @param  array{name: string, email: string, subject: string, message: string, category?: string|null, honeypot?: string|null, ip_address?: string|null, user_agent?: string|null}  $input
     * @return SupportTicket|null  null when the honeypot was tripped (treated as success by callers)
     */
    public function handle(array $input): ?SupportTicket
    {
        // Bots fill every field they find; humans never see this one.
        if (trim((string) ($input['honeypot'] ?? '')) !== '') {
            return null;
        }

        $email = mb_strtolower(trim((string) $input['email']));
        $name = Str::limit(trim((string) $input['name']), 120, '');
        $subject = Str::limit(trim((string) $input['subject']), 160, '');
        $message = trim((string) $input['message']);
        $category = trim((string) ($input['category'] ?? '')) ?: 'general';

        // A double-tapped "Send" button (or a retried request) reuses the ticket
        // that was just created instead of opening a second one.
        $existing = $this->recentDuplicate($email, $subject);
        if ($existing !== null) {
            return $existing;
        }

        $ticket = DB::transaction(function () use ($email, $name, $subject, $message, $category, $input): SupportTicket {
            $ticket = $this->createTicket([
                'user_id' => null,
                'guest_name' => $name,
                'guest_email' => $email,
                'source' => SupportTicket::SOURCE_PUBLIC_FORM,
                'ip_address' => $this->truncate($input['ip_address'] ?? null, 45),
                'user_agent' => $this->truncate($input['user_agent'] ?? null, 255),
                'subject' => $subject,
                'category' => $category,
                'status' => 'open',
                'priority' => 'normal',
                'last_message_at' => now(),
            ]);

            $ticket->messages()->create([
                'sender_type' => 'guest',
                'message' => $message,
            ]);

            return $ticket;
        });

        // After the transaction, and after the visitor already has their answer:
        // SMTP can be slow or down, and neither may delay or lose the request.
        $mailer = $this->mailer;
        defer(static function () use ($mailer, $ticket): void {
            $mailer->acknowledgeGuest($ticket);
            $mailer->notifyInbox($ticket);
        });

        return $ticket;
    }

    /** @param  array<string, mixed>  $attributes */
    private function createTicket(array $attributes): SupportTicket
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return SupportTicket::query()->create($attributes + [
                    'ticket_number' => 'TKT-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                ]);
            } catch (UniqueConstraintViolationException) {
                // Ticket number collision — extremely unlikely, just retry.
            }
        }

        throw new \RuntimeException('Could not allocate a support ticket number.');
    }

    private function recentDuplicate(string $email, string $subject): ?SupportTicket
    {
        return SupportTicket::query()
            ->whereNull('user_id')
            ->where('guest_email', $email)
            ->whereRaw('lower(subject) = ?', [mb_strtolower($subject)])
            ->where('created_at', '>=', now()->subMinutes(5))
            ->latest('id')
            ->first();
    }

    private function truncate(mixed $value, int $length): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : Str::limit($text, $length, '');
    }
}
