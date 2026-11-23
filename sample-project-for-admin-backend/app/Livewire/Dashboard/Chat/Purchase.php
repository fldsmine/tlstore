<?php

namespace App\Livewire\Dashboard\Chat;

use App\Enums\ChatMessageType;
use App\Events\ChatTyping;
use App\Models\Chat;
use App\Models\User;
use App\Services\ChatService;
use App\Services\ImageCompressionService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Chat')]
class Purchase extends Component
{
    use WithFileUploads;

    public ?Chat $chat = null;

    // Keep the open chat in the URL so a full reload (e.g. the currency switch) returns here, not to the list.
    #[Url(as: 'chat')]
    public ?string $chatRef = null;

    public string $body = '';

    public $attachment = null;

    public string $vendorSearch = '';

    public string $sessionSearch = '';

    public int $sessionsPerPage = 15;

    public function mount(): void
    {
        abort_unless((bool) config('settings.chat.enabled'), 404);

        if ($this->chatRef) {
            rescue(fn () => $this->openChat($this->chatRef), fn () => $this->chatRef = null);
        }

        // Arriving from a distributor's LiveChat icon (?vendor=…) opens the conversation with them,
        // and ?message=… pre-fills the composer with the purchase-enquiry template from that page.
        if (! $this->chat && filled(request()->query('vendor'))) {
            rescue(fn () => $this->startChat((string) request()->query('vendor'), app(ChatService::class)));

            if ($this->chat && $this->body === '' && filled(request()->query('message'))) {
                $this->body = mb_substr((string) request()->query('message'), 0, 1000);
            }
        }
    }

    /** Echo listener (+ wire:poll fallback in the view). */
    public function getListeners(): array
    {
        return ['echo:chat-inbox.'.auth()->id().',ChatInboxPing' => 'poll'];
    }

    public function startChat(string $vendorId, ChatService $chats): void
    {
        $vendor = User::query()->where('id', $vendorId)->where('is_agent', true)->firstOrFail();

        try {
            $this->chat = $chats->conversationFor(auth()->user(), $vendor);
            $this->chatRef = $this->chat->id;
            $this->chat->markReadFor(auth()->user());
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: collect($e->errors())->flatten()->first() ?? 'Could not open the chat — please try again.', type: 'error');
        }
    }

    public function openChat(string $id): void
    {
        $this->chat = Chat::query()->where('id', $id)->where('customer_id', auth()->id())->firstOrFail();
        $this->chatRef = $this->chat->id;
        $this->chat->markReadFor(auth()->user());
    }

    public function backToList(): void
    {
        $this->chat = null;
        $this->chatRef = null;
        $this->reset('body', 'attachment');
    }

    public function loadMoreSessions(): void
    {
        $this->sessionsPerPage += 15;
    }

    public function updatedSessionSearch(): void
    {
        $this->sessionsPerPage = 15;
    }

    public function send(ChatService $chats): void
    {
        if (! $this->chat) {
            return;
        }

        $this->validate(['body' => ['required', 'string', 'max:1000']]);
        $chats->postMessage($this->chat, auth()->user(), trim($this->body));
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
            $chats->postAttachment($this->chat, auth()->user(), $path, ChatMessageType::IMAGE);
        } else {
            $path = $this->attachment->store('chat-attachments', config('filesystems.default'));
            $chats->postAttachment($this->chat, auth()->user(), $path, ChatMessageType::FILE, $this->attachment->getClientOriginalName());
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

    /** Debounced from the composer — tells the distributor "typing…" (transient, no persistence). */
    public function typing(): void
    {
        if ($this->chat) {
            rescue(fn () => ChatTyping::dispatch($this->chat->id, 'customer'));
        }
        $this->skipRender();
    }

    private function refreshChat(): void
    {
        $this->chat = $this->chat?->fresh();
    }

    public function render(): View
    {
        $user = auth()->user();

        if ($this->chat) {
            $this->chat->load(['messages', 'agent']);
        }

        $sessions = Chat::query()->where('customer_id', $user->id)
            ->with('agent')
            ->when($this->sessionSearch !== '', fn ($q) => $q->whereHas('agent', fn ($a) => $a
                ->where('username', 'like', '%'.$this->sessionSearch.'%')
                ->orWhere('first_name', 'like', '%'.$this->sessionSearch.'%')
                ->orWhere('last_name', 'like', '%'.$this->sessionSearch.'%')))
            ->orderByDesc('last_message_at')->orderByDesc('created_at');
        $sessionsTotal = (clone $sessions)->count();
        $myChats = $sessions->limit($this->sessionsPerPage)->get();

        // Unread-per-conversation: distributor messages newer than my read marker.
        $unread = DB::table('chat_messages')
            ->join('chats', 'chats.id', '=', 'chat_messages.chat_id')
            ->where('chats.customer_id', $user->id)
            ->where('chat_messages.sender', 'agent')
            ->where(fn ($q) => $q->whereNull('chats.customer_read_at')->orWhereColumn('chat_messages.created_at', '>', 'chats.customer_read_at'))
            ->groupBy('chat_messages.chat_id')
            ->selectRaw('chat_messages.chat_id as cid, count(*) as c')
            ->pluck('c', 'cid');

        return view('livewire.dashboard.chat.purchase', [
            'vendors' => $this->vendors($user),
            'myChats' => $myChats,
            'hasMoreSessions' => $sessionsTotal > $myChats->count(),
            'unread' => $unread,
        ]);
    }

    /** Region-matched distributors to start a conversation with (online-first). */
    private function vendors(User $user): Collection
    {
        $onlineSeconds = (int) config('settings.cloud.online_window_seconds', 60);

        $query = User::query()
            ->where('is_agent', true)
            ->where('show_on_agents_page', true)
            ->where('id', '!=', $user->id)
            ->when($this->vendorSearch !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('username', 'like', '%'.$this->vendorSearch.'%')
                ->orWhere('first_name', 'like', '%'.$this->vendorSearch.'%')
                ->orWhere('last_name', 'like', '%'.$this->vendorSearch.'%')))
            ->orderByRaw('CASE WHEN last_seen_at >= ? THEN 0 ELSE 1 END', [now()->subSeconds($onlineSeconds)->toDateTimeString()])
            ->orderByDesc('last_seen_at');

        $query = $user->isNigerian()
            ? $query->where('country', 'NG')
            : $query->where('country', '!=', 'NG');

        return $query->get(['id', 'username', 'first_name', 'last_name', 'avatar', 'last_seen_at'])
            ->map(fn (User $v): array => [
                'id' => $v->id,
                'name' => $v->full_name,
                'username' => $v->username,
                'has_name' => filled($v->first_name),
                'avatar' => $v->avatar,
                'online' => $v->last_seen_at && $v->last_seen_at->gt(now()->subSeconds($onlineSeconds)),
                'last_seen' => $v->last_seen_at?->format('g:i A'),
            ]);
    }
}
