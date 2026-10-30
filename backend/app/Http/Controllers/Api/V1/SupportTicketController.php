<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\SupportTicketNotifier;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class SupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $tickets = $user->supportTickets()
            ->with('latestMessage')
            ->orderByDesc('last_message_at')
            ->limit(50)
            ->get()
            ->map(fn (SupportTicket $ticket): array => $this->ticketSummary($ticket))
            ->values();

        return ApiResponse::success('Your support tickets.', [
            'user' => [
                'name' => $user->full_name,
                'email' => (string) $user->email,
            ],
            'tickets' => $tickets,
        ]);
    }

    public function store(Request $request, SupportTicketNotifier $notifier): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $data = $request->validate([
            'subject' => ['required', 'string', 'min:4', 'max:160'],
            'category' => ['required', 'in:general,account,payments,technical'],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        $key = 'support-ticket-open-user:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return ApiResponse::error('Please wait a moment before opening another support ticket.', 429)
                ->withHeaders(['Retry-After' => (string) RateLimiter::availableIn($key)]);
        }
        RateLimiter::hit($key, 60);

        $body = trim($data['message']);
        $ticket = DB::transaction(function () use ($user, $data, $body): SupportTicket {
            $name = $user->full_name;
            $ticket = $user->supportTickets()->create([
                'contact_name' => $name,
                'contact_email' => $user->email,
                'subject' => trim($data['subject']),
                'category' => $data['category'],
                'status' => SupportTicket::STATUS_OPEN,
                'last_message_at' => now(),
            ]);
            $ticket->messages()->create([
                'user_id' => $user->id,
                'sender_type' => SupportTicketMessage::SENDER_USER,
                'sender_name' => $name,
                'body' => $body,
            ]);

            return $ticket;
        });

        $notifier->notifySupport($ticket, $body);

        return ApiResponse::created('Your support ticket has been opened.', [
            'user' => [
                'name' => $user->full_name,
                'email' => (string) $user->email,
            ],
            'ticket' => $this->ticketSummary($ticket->load('latestMessage')),
        ]);
    }

    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        $ticket = $this->ownedTicket($request, $ticket);

        return ApiResponse::success('Support ticket conversation.', [
            'ticket' => $this->ticketSummary($ticket),
            'messages' => $ticket->messages->map(fn (SupportTicketMessage $message): array => $this->messageData($message)),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket, SupportTicketNotifier $notifier): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $data = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        $key = 'support-ticket-reply-user:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return ApiResponse::error('Please wait a moment before sending another reply.', 429)
                ->withHeaders(['Retry-After' => (string) RateLimiter::availableIn($key)]);
        }
        RateLimiter::hit($key, 60);
        $body = trim($data['message']);

        $ticket = DB::transaction(function () use ($user, $ticket, $body): SupportTicket {
            $lockedTicket = $user->supportTickets()->lockForUpdate()->findOrFail($ticket->id);
            $lockedTicket->messages()->create([
                'user_id' => $user->id,
                'sender_type' => SupportTicketMessage::SENDER_USER,
                'sender_name' => $user->full_name,
                'body' => $body,
            ]);
            $lockedTicket->forceFill([
                'status' => SupportTicket::STATUS_OPEN,
                'last_message_at' => now(),
            ])->save();

            return $lockedTicket;
        });

        $notifier->notifySupport($ticket, $body, 'Customer replied to a support ticket');

        return ApiResponse::success('Your reply has been sent.', [
            'ticket' => $this->ticketSummary($ticket->load('latestMessage')),
        ]);
    }

    private function ownedTicket(Request $request, SupportTicket $ticket): SupportTicket
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user->supportTickets()->with(['messages', 'latestMessage'])->findOrFail($ticket->id);
    }

    /** @return array<string, mixed> */
    private function ticketSummary(SupportTicket $ticket): array
    {
        $message = $ticket->latestMessage;

        return [
            'id' => (string) $ticket->id,
            'reference' => $ticket->reference(),
            'subject' => $ticket->subject,
            'category' => $ticket->category,
            'status' => $ticket->status,
            'status_label' => $ticket->statusLabel(),
            'last_message_at' => $ticket->last_message_at?->toIso8601String(),
            'created_at' => $ticket->created_at?->toIso8601String(),
            'latest_message' => $message ? $this->messageData($message) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function messageData(SupportTicketMessage $message): array
    {
        return [
            'id' => (string) $message->id,
            'sender_type' => $message->sender_type,
            'sender_name' => $message->sender_name,
            'body' => $message->body,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
