<?php

namespace App\Livewire\Dashboard\Agent;

use App\Enums\ChatMessageType;
use App\Events\ChatTyping;
use App\Models\Chat;
use App\Services\ChatService;
use App\Services\ImageCompressionService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Live Chats')]
class LiveChats extends Component
{
    use WithFileUploads;

    public ?Chat $chat = null;

    public string $body = '';

    public $attachment = null;

    public string $search = '';

    public int $perPage = 20;

    public function mount(): void
    {
        abort_unless((bool) auth()->user()?->is_agent, 403);
        abort_unless((bool) config('settings.chat.enabled'), 404);

        // Deep-link from a notification (?chat=…) opens that chat if it belongs to this distributor.
        if ($ref = request()->query('chat')) {
            $this->chat = Chat::query()->where('id', $ref)->where('agent_id', auth()->id())->first();
            $this->chat?->markReadFor(auth()->user());
        }
    }

    public function getListeners(): array
    {
        return ['echo:chat-inbox.'.auth()->id().',ChatInboxPing' => 'poll'];
    }

    public function openChat(string $id): void
    {
        $this->chat = Chat::query()->where('id', $id)->where('agent_id', auth()->id())->firstOrFail();
        $this->chat->markReadFor(auth()->user());
    }

    public function close(): void
    {
        $this->chat = null;
        $this->reset('body', 'attachment');
    }

    public function send(ChatService $chats): void
    {
        if (! $this->chat) {
            return;
        }

        $this->validate(['body' => ['required', 'string', 'max:1000']]);
        $chats->postMessage($this->ownChat(), auth()->user(), trim($this->body));
        $this->reset('body');
        $this->refreshChat();
    }

    public function sendAttachment(ChatService $chats, ImageCompressionService $images): void
    {
        if (! $this->chat) {
            return;
        }

        $this->validate(['attachment' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120']]);

        if (in_array(strtolower($this->attachment->getClientOriginalExtension()), ['jpg', 'jpeg', 'png'], true)) {
            $path = $images->compressAndStore($this->attachment, 'chat-attachments');
            $chats->postAttachment($this->ownChat(), auth()->user(), $path, ChatMessageType::IMAGE);
        } else {
            $path = $this->attachment->store('chat-attachments', config('filesystems.default'));
            $chats->postAttachment($this->ownChat(), auth()->user(), $path, ChatMessageType::FILE, $this->attachment->getClientOriginalName());
        }

        $this->reset('attachment');
        $this->refreshChat();
    }

    public function removeAttachment(): void
    {
        $this->reset('attachment');
    }

    public function poll(): void
    {
        $this->refreshChat();
        $this->chat?->markReadFor(auth()->user()); // viewing it = read
    }

    /** Debounced from the composer — tells the customer "typing…" (transient, no persistence). */
    public function typing(): void
    {
        if ($this->chat) {
            rescue(fn () => ChatTyping::dispatch($this->chat->id, 'agent'));
        }
        $this->skipRender();
    }

    public function loadMore(): void
    {
        $this->perPage += 20;
    }

    public function updatedSearch(): void
    {
        $this->perPage = 20;
    }

    private function ownChat(): Chat
    {
        abort_unless($this->chat && $this->chat->agent_id === auth()->id(), 403);

        return $this->chat;
    }

    private function refreshChat(): void
    {
        $this->chat = $this->chat?->fresh();
    }

    public function render(): View
    {
        $me = auth()->id();

        if ($this->chat) {
            $this->chat->load(['messages', 'customer']);
        }

        $query = Chat::query()->where('agent_id', $me)
            ->with('customer')
            ->when($this->search !== '', fn ($q) => $q->whereHas('customer', fn ($c) => $c
                ->where('username', 'like', '%'.$this->search.'%')
                ->orWhere('first_name', 'like', '%'.$this->search.'%')
                ->orWhere('last_name', 'like', '%'.$this->search.'%')))
            ->orderByDesc('last_message_at')->orderByDesc('created_at');
        $total = (clone $query)->count();
        $conversations = $query->limit($this->perPage)->get();

        // Unread-per-chat: customer messages newer than my read marker.
        $unread = DB::table('chat_messages')
            ->join('chats', 'chats.id', '=', 'chat_messages.chat_id')
            ->where('chats.agent_id', $me)
            ->where('chat_messages.sender', 'customer')
            ->where(fn ($q) => $q->whereNull('chats.agent_read_at')->orWhereColumn('chat_messages.created_at', '>', 'chats.agent_read_at'))
            ->groupBy('chat_messages.chat_id')
            ->selectRaw('chat_messages.chat_id as cid, count(*) as c')
            ->pluck('c', 'cid');

        return view('livewire.dashboard.agent.live-chats', [
            'conversations' => $conversations,
            'hasMore' => $total > $conversations->count(),
            'unread' => $unread,
        ]);
    }
}
