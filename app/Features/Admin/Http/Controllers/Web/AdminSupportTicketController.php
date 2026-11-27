<?php

namespace App\Features\Admin\Http\Controllers\Web;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class AdminSupportTicketController
{
    public function index(Request $request): View
    {
        $this->authorizeSupportAccess();

        $query = SupportTicket::query()
            ->with(['user:id,full_name,email,username', 'messages' => fn ($messages) => $messages->latest('id')->limit(1)])
            ->latest('last_message_at')
            ->latest('id');

        if ($request->filled('status') && $request->string('status')->toString() !== 'all') {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(fn ($tickets) => $tickets
                ->where('ticket_number', 'like', $term)
                ->orWhere('subject', 'like', $term)
                ->orWhereHas('user', fn ($user) => $user->where('email', 'like', $term)->orWhere('full_name', 'like', $term)));
        }

        return view('admin.support-tickets.index', [
            'title' => 'Support tickets',
            'tickets' => $query->paginate(30)->withQueryString(),
        ]);
    }

    public function show(SupportTicket $ticket): View
    {
        $this->authorizeSupportAccess();
        $ticket->load(['user:id,full_name,email,username', 'messages.senderUser:id,full_name,username', 'messages.senderAdmin:id,full_name']);
        $ticket->messages()->where('sender_type', 'user')->whereNull('read_at')->update(['read_at' => now()]);

        return view('admin.support-tickets.show', [
            'title' => 'Support · '.$ticket->ticket_number,
            'ticket' => $ticket,
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorizeSupportAccess();
        abort_if($ticket->status === 'closed', 422, 'Reopen this ticket before replying.');
        $data = $request->validate(['message' => ['required', 'string', 'min:2', 'max:5000']]);
        $before = ['status' => $ticket->status];

        $message = $ticket->messages()->create([
            'sender_type' => 'admin',
            'sender_admin_id' => Auth::guard('admin')->id(),
            'message' => trim($data['message']),
        ]);
        $ticket->forceFill(['status' => 'waiting_on_user', 'last_message_at' => now()])->save();
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
        $this->audit('support_ticket.replied', $ticket, $before, ['status' => $ticket->status]);

        return redirect()->route('admin.support-tickets.show', $ticket)->with('success', 'Reply sent to the user.');
    }

    public function update(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->authorizeSupportAccess();
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'in_progress', 'waiting_on_user', 'resolved', 'closed'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
        ]);
        $before = ['status' => $ticket->status, 'priority' => $ticket->priority];
        $ticket->fill($data);
        $ticket->closed_at = $ticket->status === 'closed' ? ($ticket->closed_at ?? now()) : null;
        $ticket->save();
        $this->audit('support_ticket.updated', $ticket, $before, ['status' => $ticket->status, 'priority' => $ticket->priority]);

        return redirect()->route('admin.support-tickets.show', $ticket)->with('success', 'Ticket status updated.');
    }

    private function authorizeSupportAccess(): void
    {
        abort_unless(in_array(Auth::guard('admin')->user()?->role, ['super_admin', 'admin', 'moderator'], true), 403);
    }

    private function audit(string $action, SupportTicket $ticket, array $before, array $after): void
    {
        AuditLog::query()->create([
            'audit_key' => (string) Str::uuid(),
            'actor_admin_id' => Auth::guard('admin')->id(),
            'action' => $action,
            'entity_type' => class_basename($ticket),
            'entity_id' => (string) $ticket->getKey(),
            'before_values' => $before,
            'after_values' => $after,
            'created_at' => now(),
        ]);
    }
}
