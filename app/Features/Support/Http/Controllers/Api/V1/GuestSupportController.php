<?php

namespace App\Features\Support\Http\Controllers\Api\V1;

use App\Application\Actions\Support\SubmitGuestSupportTicket;
use App\Features\Support\Http\ContactFormRules;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `POST /api/v1/guest-support` — the endpoint the reviewer app's landing-page
 * contact form (and any other public surface) posts to.
 *
 * No authentication: anyone may ask for help. The response deliberately says
 * nothing about the submitter's email address being associated with an account,
 * and it never returns a token. Rate limiting lives on the route
 * (`throttle:guest-support`).
 */
final class GuestSupportController
{
    public function store(Request $request, SubmitGuestSupportTicket $submit): JsonResponse
    {
        $data = $request->validate(
            ContactFormRules::rules(),
            ContactFormRules::messages(),
            ContactFormRules::attributes(),
        );

        $ticket = $submit->handle([
            'name' => (string) $data['name'],
            'email' => (string) $data['email'],
            'subject' => (string) $data['subject'],
            'message' => (string) $data['message'],
            'category' => $data['category'] ?? null,
            'honeypot' => (string) $request->input('website', ''),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $message = $ticket === null
            ? 'Thanks — if your message needs a reply we will be in touch.'
            : 'Thanks, '.$this->firstName($ticket).' — your message is with our support team. '
                .'We will reply to '.$ticket->guest_email.' and your reference is '.$ticket->ticket_number.'.';

        return response()->json([
            'message' => $message,
            'data' => $ticket === null ? null : [
                'ticket_number' => $ticket->ticket_number,
                'status' => $ticket->status,
                'email' => $ticket->guest_email,
                'subject' => $ticket->subject,
                'created_at' => $ticket->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    private function firstName(SupportTicket $ticket): string
    {
        $name = trim((string) $ticket->guest_name);

        return $name === '' ? 'there' : explode(' ', $name)[0];
    }
}
