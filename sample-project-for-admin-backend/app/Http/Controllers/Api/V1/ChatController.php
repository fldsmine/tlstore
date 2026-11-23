<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ChatMessageSender;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ChatMessageRequest;
use App\Http\Requests\Api\V1\OpenChatRequest;
use App\Http\Resources\Api\V1\ChatMessageResource;
use App\Http\Resources\Api\V1\ChatResource;
use App\Models\Chat;
use App\Models\User;
use App\Services\ChatService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $chats = Chat::query()
            ->forParticipant($user->id)
            ->latest('last_message_at')
            ->latest('created_at')
            ->get()
            ->each(function (Chat $chat) use ($user): void {
                $chat->api_other = $chat->otherParticipant($user);
                $chat->api_unread_count = $this->unreadCount($chat, $user);
            });

        return ApiResponse::success('Conversations retrieved successfully.', [
            'chats' => ChatResource::collection($chats),
        ]);
    }

    /** Open (or fetch) the conversation between the requester and a distributor. */
    public function open(OpenChatRequest $request, ChatService $chats): JsonResponse
    {
        $agent = User::query()->where('username', $request->validated('agent_username'))->firstOrFail();

        $chat = $chats->conversationFor($request->user(), $agent);

        $chat->api_other = $chat->otherParticipant($request->user());
        $chat->api_unread_count = $this->unreadCount($chat, $request->user());

        return ApiResponse::success('Conversation ready.', ['chat' => new ChatResource($chat)]);
    }

    public function messages(Request $request, string $chat): JsonResponse
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        $record = $this->participating($request->user(), $chat);

        $paginator = $record->messages()
            ->orderBy('created_at')
            ->paginate((int) $request->input('per_page', 50));

        return ApiResponse::paginated($paginator, ChatMessageResource::class, 'Messages retrieved successfully.');
    }

    public function storeMessage(ChatMessageRequest $request, string $chat, ChatService $chats): JsonResponse
    {
        $record = $this->participating($request->user(), $chat);

        $chats->postMessage($record, $request->user(), (string) $request->validated('body'));

        return ApiResponse::created('Message sent.', [
            'message' => new ChatMessageResource($record->messages()->latest()->first()),
        ]);
    }

    public function markRead(Request $request, string $chat): JsonResponse
    {
        $record = $this->participating($request->user(), $chat);

        $record->markReadFor($request->user());

        return ApiResponse::success('Conversation marked as read.');
    }

    private function participating(User $user, string $chatId): Chat
    {
        $chat = Chat::query()->whereKey($chatId)->firstOrFail();

        abort_unless($chat->customer_id === $user->id || $chat->agent_id === $user->id, 403, 'You are not part of this conversation.');

        return $chat;
    }

    private function unreadCount(Chat $chat, User $user): int
    {
        $isAgent = $chat->agent_id === $user->id;
        $readAt = $isAgent ? $chat->agent_read_at : $chat->customer_read_at;

        return $chat->messages()
            ->where('sender', $isAgent ? ChatMessageSender::CUSTOMER : ChatMessageSender::AGENT)
            ->when($readAt, fn ($q) => $q->where('created_at', '>', $readAt))
            ->count();
    }
}
