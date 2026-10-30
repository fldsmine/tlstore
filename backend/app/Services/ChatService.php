<?php

namespace App\Services;

use App\Enums\ChatMessageSender;
use App\Enums\ChatMessageType;
use App\Events\ChatInboxPing;
use App\Events\ChatUpdated;
use App\Models\Chat;
use App\Models\User;
use App\Notifications\PushAlert;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Plain conversational messaging between a customer and a distributor. No purchase flow —
 * one persistent thread per pair, opened on demand, with text + image/file attachments.
 */
class ChatService
{
    /** Find (or open) the single conversation between a customer and a distributor. */
    public function conversationFor(User $customer, User $agent): Chat
    {
        if (! $agent->is_agent || $agent->is($customer)) {
            throw ValidationException::withMessages(['agent' => 'This distributor is not available.']);
        }

        return Chat::query()->firstOrCreate([
            'customer_id' => $customer->id,
            'agent_id' => $agent->id,
        ]);
    }

    /** Send a text message. */
    public function postMessage(Chat $chat, User $sender, string $body): void
    {
        $this->deliver($chat, $sender, $body, ChatMessageType::TEXT);
    }

    /** Send an already-stored image/file attachment. */
    public function postAttachment(Chat $chat, User $sender, string $path, ChatMessageType $type, ?string $name = null): void
    {
        $label = $type === ChatMessageType::IMAGE ? 'Photo' : ($name ?: 'File');
        $this->deliver($chat, $sender, $label, $type, array_filter(['path' => $path, 'name' => $name]));
    }

    /** Shared send path (text + attachments): rate-limit, persist, broadcast + push. */
    private function deliver(Chat $chat, User $sender, string $body, ChatMessageType $type, array $meta = []): void
    {
        // Cap message rate to blunt spam / runaway loops (per sender, across chats).
        if (RateLimiter::tooManyAttempts('chat-message:'.$sender->id, 60)) {
            throw ValidationException::withMessages(['body' => 'You’re sending messages too quickly — take a breath and try again.']);
        }
        RateLimiter::hit('chat-message:'.$sender->id, 60);

        $isAgent = $sender->is($chat->agent);
        $this->message($chat, $isAgent ? ChatMessageSender::AGENT : ChatMessageSender::CUSTOMER, $body, $type, $meta);
        $this->ping($chat);
        $this->pushToParticipant($chat, $isAgent ? $chat->customer : $chat->agent, $sender->full_name ?: $sender->username, $body);
    }

    private function message(Chat $chat, ChatMessageSender $sender, string $body, ChatMessageType $type = ChatMessageType::TEXT, array $meta = []): void
    {
        $chat->messages()->create([
            'sender' => $sender,
            'type' => $type,
            'body' => $body,
            'meta' => $meta ?: null,
        ]);

        $chat->forceFill(['last_message_at' => now()])->saveQuietly();
    }

    private function ping(Chat $chat): void
    {
        rescue(function () use ($chat) {
            ChatUpdated::dispatch($chat->id);
            // Nudge both participants' global notifiers; each toasts only its own inbound activity.
            ChatInboxPing::dispatch((string) $chat->customer_id);
            ChatInboxPing::dispatch((string) $chat->agent_id);
        });
    }

    /** Queue a web-push to a chat participant, deep-linking to their side of the thread. */
    private function pushToParticipant(Chat $chat, User $recipient, string $title, string $body): void
    {
        $url = $chat->agent_id === $recipient->id
            ? route('agent.chats', ['chat' => $chat->id])
            : route('chat.purchase', ['chat' => $chat->id]);

        rescue(fn () => $recipient->notify(new PushAlert($title, $body, $url, 'chat-'.$chat->id)));
    }
}
