<?php

namespace App\Features\Support\Http\Controllers\Api\V1;

use App\Features\Support\Services\SupportMailer;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SupportTicketController
{
    public function __construct(private readonly SupportMailer $mailer) {}

    private const CATEGORIES = ['general', 'account', 'payments', 'subscriptions', 'app_reviews', 'ad_shares', 'vas', 'other'];
    private const STATUSES = ['open', 'in_progress', 'waiting_on_user', 'resolved', 'closed'];

    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->supportTickets()->with('messages')->latest('last_message_at')->latest('id');
        if ($request->filled('status') && $request->string('status')->toString() !== 'all') {
            $query->where('status', $request->string('status')->toString());
        }
        return response()->json(['data' => $query->paginate(min(50, max(1, $request->integer('per_page', 20))))]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'min:4', 'max:160'],
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $ticket = DB::transaction(function () use ($request, $data): SupportTicket {
            $ticket = $request->user()->supportTickets()->create([
                'ticket_number' => 'TKT-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'subject' => trim($data['subject']),
                'category' => $data['category'],
                'status' => 'open',
                'priority' => 'normal',
                'last_message_at' => now(),
            ]);
            $ticket->messages()->create([
                'sender_type' => 'user',
                'sender_user_id' => $request->user()->id,
                'message' => trim($data['message']),
            ]);
            return $ticket;
        });

        return response()->json(['data' => $this->serialize($ticket->fresh('messages'))], 201);
    }

    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);
        $ticket->load(['messages.senderUser:id,full_name,username', 'messages.senderAdmin:id,full_name']);
        $ticket->messages()->where('sender_type', 'admin')->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['data' => $this->serialize($ticket)]);
    }

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);
        if ($ticket->status === 'closed') {
            throw ValidationException::withMessages(['ticket' => 'This ticket is closed. Reopen it or create a new ticket to continue.']);
        }
        $data = $request->validate(['message' => ['required', 'string', 'min:2', 'max:5000']]);
        DB::transaction(function () use ($request, $ticket, $data): void {
            $ticket->messages()->create([
                'sender_type' => 'user',
                'sender_user_id' => $request->user()->id,
                'message' => trim($data['message']),
            ]);
            $ticket->forceFill(['status' => 'open', 'last_message_at' => now(), 'closed_at' => null])->save();
        });
        return response()->json(['data' => $this->serialize($ticket->fresh(['messages.senderAdmin:id,full_name']))]);
    }

    public function close(Request $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);
        $ticket->forceFill(['status' => 'closed', 'closed_at' => now()])->save();
        return response()->json(['data' => $this->serialize($ticket->fresh('messages'))]);
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $query = SupportTicket::query()->with(['user:id,full_name,email,username', 'messages'])->latest('last_message_at')->latest('id');
        if ($request->filled('status') && $request->string('status')->toString() !== 'all') {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(fn ($builder) => $builder->where('ticket_number', 'like', $search)
                ->orWhere('subject', 'like', $search)
                ->orWhere('guest_email', 'like', $search)
                ->orWhere('guest_name', 'like', $search)
                ->orWhereHas('user', fn ($userQuery) => $userQuery->where('email', 'like', $search)->orWhere('full_name', 'like', $search)));
        }
        $tickets = $query->paginate(min(100, max(1, $request->integer('per_page', 30))))
            ->through(fn (SupportTicket $ticket): array => $this->serializeListRow($ticket));

        return response()->json(['data' => $tickets]);
    }

    public function adminShow(SupportTicket $ticket): JsonResponse
    {
        $ticket->load(['user:id,full_name,email,username', 'messages.senderUser:id,full_name,username', 'messages.senderAdmin:id,full_name']);
        $ticket->messages()->whereIn('sender_type', ['user', 'guest'])->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['data' => $this->serialize($ticket)]);
    }

    public function adminReply(Request $request, SupportTicket $ticket): JsonResponse
    {
        if ($ticket->status === 'closed') {
            throw ValidationException::withMessages(['ticket' => 'Reopen the ticket before replying.']);
        }
        $data = $request->validate(['message' => ['required', 'string', 'min:2', 'max:5000']]);
        DB::transaction(function () use ($request, $ticket, $data): void {
            $message = $ticket->messages()->create([
                'sender_type' => 'admin',
                'sender_admin_id' => $request->user()->id,
                'message' => trim($data['message']),
            ]);
            $ticket->forceFill(['status' => 'waiting_on_user', 'last_message_at' => now()])->save();

            if ($ticket->user_id !== null) {
                Notification::query()->firstOrCreate(
                    ['notification_key' => 'support-ticket-reply-'.$message->id],
                    [
                        'user_id' => $ticket->user_id,
                        'type' => 'support_ticket_reply',
                        'title' => 'Support replied: '.$ticket->ticket_number,
                        'body' => 'Our support team replied to ticket '.$ticket->ticket_number.': '.$ticket->subject,
                        'data' => ['support_ticket_id' => $ticket->id, 'support_ticket_message_id' => $message->id, 'ticket_number' => $ticket->ticket_number, 'ticket_subject' => $ticket->subject, 'reply' => $message->message],
                    ],
                );
            }
        });

        // A guest has no account inbox; the reply leaves by email after commit.
        if ($ticket->user_id === null) {
            $this->mailer->sendGuestReply($ticket, $ticket->messages()->latest('id')->first());
        }
        return response()->json(['data' => $this->serialize($ticket->fresh(['user:id,full_name,email,username', 'messages.senderUser:id,full_name,username', 'messages.senderAdmin:id,full_name']))]);
    }

    public function adminUpdate(Request $request, SupportTicket $ticket): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])],
        ]);
        $data['closed_at'] = ($data['status'] ?? $ticket->status) === 'closed' ? ($ticket->closed_at ?? now()) : null;
        $ticket->fill($data)->save();
        return response()->json(['data' => $this->serialize($ticket->fresh(['user:id,full_name,email,username', 'messages']))]);
    }

    /**
     * Admin list rows keep the shape the mobile admin app already consumes and
     * add the `guest` block, so a contact-form ticket is identifiable in the list.
     *
     * @return array<string, mixed>
     */
    private function serializeListRow(SupportTicket $ticket): array
    {
        $row = $ticket->toArray();
        $row['guest'] = $ticket->user_id === null
            ? ['name' => $ticket->guest_name, 'email' => $ticket->guest_email]
            : null;

        return $row;
    }

    private function serialize(SupportTicket $ticket): array
    {
        $ticket->loadMissing(['user:id,full_name,email,username', 'messages.senderUser:id,full_name,username', 'messages.senderAdmin:id,full_name']);
        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'category' => $ticket->category,
            'source' => $ticket->source ?? SupportTicket::SOURCE_USER,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'last_message_at' => $ticket->last_message_at,
            'closed_at' => $ticket->closed_at,
            'created_at' => $ticket->created_at,
            'user' => $ticket->user ? [
                'id' => $ticket->user->id,
                'full_name' => $ticket->user->full_name,
                'username' => $ticket->user->username,
                'email' => $ticket->user->email,
            ] : null,
            // Guest tickets are owned by whoever typed the form in; the address is
            // only echoed to administrators, never to another user's ticket list.
            'guest' => $ticket->user_id === null ? [
                'name' => $ticket->guest_name,
                'email' => $ticket->guest_email,
            ] : null,
            'messages' => $ticket->messages->map(fn (SupportTicketMessage $message) => [
                'id' => $message->id,
                'sender_type' => $message->sender_type,
                'sender_name' => match ($message->sender_type) {
                    'admin' => $message->senderAdmin?->full_name ?? 'TLStore Support',
                    'guest' => $ticket->guest_name ?: 'Guest',
                    default => $message->senderUser?->full_name ?? $ticket->user?->full_name ?? 'You',
                },
                'message' => $message->message,
                'read_at' => $message->read_at,
                'created_at' => $message->created_at,
            ])->values(),
        ];
    }
}
