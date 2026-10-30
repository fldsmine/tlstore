<?php

namespace App\Livewire\Dashboard\Chat;

use App\Models\Chat;
use App\Models\ChatMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Invisible, always-mounted (app layout) notifier. Reverb pings + a 30s poll fallback both
 * call check(), which toasts new inbound chat activity — linking to the chat — on any page,
 * and keeps the agent's Live Chats sidebar badge live.
 */
class ChatNotifier extends Component
{
    public string $since = '';

    public function mount(): void
    {
        $this->since = now()->toDateTimeString();
    }

    /** Dynamic Echo listeners on this user's channels (+ wire:poll fallback in the view). */
    public function getListeners(): array
    {
        $id = auth()->id();

        return [
            'echo:chat-inbox.'.$id.',ChatInboxPing' => 'check',
        ];
    }

    public function check(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        if ($user->is_agent) {
            // Badge = conversations with unread inbound (customer) messages.
            $this->dispatch('live-chats-badge', count: Chat::query()
                ->where('agent_id', $user->id)
                ->whereHas('messages', fn ($q) => $q->where('sender', 'customer')
                    ->where(fn ($w) => $w->whereNull('chats.agent_read_at')
                        ->orWhereColumn('chat_messages.created_at', '>', 'chats.agent_read_at')))
                ->count());
        }

        $chatIds = Chat::query()->forParticipant($user->id)->pluck('id');
        if ($chatIds->isEmpty()) {
            return;
        }

        $messages = ChatMessage::query()
            ->whereIn('chat_id', $chatIds)
            ->where('created_at', '>', $this->since)
            ->with(['chat.customer', 'chat.agent'])
            ->orderBy('created_at')
            ->get();

        if ($messages->isEmpty()) {
            return;
        }

        $this->since = now()->toDateTimeString();

        // One toast per chat: the latest message not authored by this user.
        $perChat = [];
        foreach ($messages as $m) {
            $chat = $m->chat;
            if (! $chat) {
                continue;
            }
            // Only real messages notify — system status lines are already shown in-thread.
            if ($m->sender->value === 'system') {
                continue;
            }
            $imAgent = $chat->agent_id === $user->id;
            $mine = ($m->sender->value === 'agent' && $imAgent) || ($m->sender->value === 'customer' && ! $imAgent);
            if ($mine) {
                continue;
            }
            $perChat[$chat->id] = $m;
        }

        foreach ($perChat as $m) {
            $chat = $m->chat;
            $imAgent = $chat->agent_id === $user->id;
            $other = $imAgent ? $chat->customer : $chat->agent;

            $this->dispatch('chat-toast',
                key: $chat->id,
                name: $other->full_name ?: $other->username,
                username: (string) $other->username,
                avatar: $other->avatar,
                initials: Str::upper(Str::substr($other->first_name ?: $other->username, 0, 2)),
                preview: Str::limit(trim((string) $m->body), 70),
                url: $imAgent
                    ? route('agent.chats', ['chat' => $chat->id])
                    : route('chat.purchase', ['chat' => $chat->id]),
                duration: 8000,
            );
        }
    }

    public function render(): View
    {
        return view('livewire.dashboard.chat.chat-notifier');
    }
}
