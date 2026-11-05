<?php

namespace App\Observers;

use App\Features\Notifications\Services\FirebaseCloudMessaging;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class NotificationObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly FirebaseCloudMessaging $messaging) {}

    public function created(Notification $notification): void
    {
        $user = $notification->user;
        if (! $user || $user->trashed()) {
            return;
        }

        $this->messaging->send($notification);

        $preference = $user->notificationPreferences()
            ->where('category', $notification->preferenceCategory())->first();
        if (! $preference || ! $preference->email_enabled) return;
        if (! $user->email) return;

        $delivery = NotificationDelivery::query()->create([
            'notification_id' => $notification->id,
            'channel' => 'email',
            'provider' => config('mail.default'),
            'status' => 'queued',
        ]);
        try {
            Mail::raw($notification->body ?? $notification->title, function ($message) use ($notification, $user): void {
                $message->to($user->email, $user->full_name)
                    ->subject($notification->title);
            });
            $delivery->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable $error) {
            $delivery->update(['status' => 'failed', 'failure_reason' => mb_substr($error->getMessage(), 0, 2000)]);
            report($error);
        }
    }
}
