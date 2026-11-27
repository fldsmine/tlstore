<?php

namespace App\Observers;

use App\Features\Notifications\Services\FirebaseCloudMessaging;
use App\Features\Notifications\Services\NotificationTemplateService;
use App\Models\Notification;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

final class NotificationObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly FirebaseCloudMessaging $messaging,
        private readonly NotificationTemplateService $templates,
    ) {}

    public function created(Notification $notification): void
    {
        $user = $notification->user;
        if (! $user || $user->trashed()) {
            return;
        }

        $template = $this->templates->templateFor($notification->type);
        if ($this->templates->pushEnabled() && $template?->firebase_status) {
            $copy = $this->templates->pushCopy($notification, $template);
            $this->messaging->send($notification, $copy['title'], $copy['body']);
        }

        if ($template) $this->templates->sendNotificationEmail($notification, $template);
    }
}
