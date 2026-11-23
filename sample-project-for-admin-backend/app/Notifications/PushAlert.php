<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Generic, app-wide web-push notification: `$user->notify(new PushAlert($title, $body, $url))`.
 * The service worker (public/sw.js) renders it and deep-links to `url` on click. Queued so the
 * outbound HTTP call to the push service never blocks the request that triggered it.
 */
class PushAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public ?string $url = null,
        public ?string $tag = null,
        public ?string $icon = null,
        public ?string $actionLabel = 'Open',
    ) {}

    /** @return array<int, class-string> */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $message = (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon($this->icon ?? '/favicon-192x192.png')
            ->badge('/favicon-192x192.png')
            ->tag($this->tag ?? 'nextel')
            ->options(['TTL' => 1800])
            ->data(['url' => $this->url ?? '/']);

        // A tap-through action button that opens the same deep-link target as the notification body.
        if ($this->url && $this->actionLabel) {
            $message->action($this->actionLabel, 'open');
        }

        return $message;
    }
}
