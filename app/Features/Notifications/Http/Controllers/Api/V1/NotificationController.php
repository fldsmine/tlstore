<?php

namespace App\Features\Notifications\Http\Controllers\Api\V1;

use App\Models\Notification;
use App\Models\UserDevice;
use App\Models\UserNotificationPreference;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NotificationController
{
    private const CATEGORIES = [
        'account' => ['label' => 'Account and security', 'description' => 'Important account and security updates.', 'locked' => true],
        'reviews' => ['label' => 'App review tasks', 'description' => 'Task assignments and app review decisions.'],
        'ad_shares' => ['label' => 'Ad share tasks', 'description' => 'Share assignments and verification results.'],
        'payments' => ['label' => 'Payments and top-ups', 'description' => 'Deposit and payment status updates.'],
        'withdrawals' => ['label' => 'Withdrawals', 'description' => 'Payout request updates.'],
        'referrals' => ['label' => 'Referrals and rewards', 'description' => 'Referral activity and reward updates.'],
        'system' => ['label' => 'Product updates', 'description' => 'Important TrustLens service announcements.'],
    ];

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->notifications()->latest()->paginate(30)]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = $request->user()->notifications()->whereNull('read_at')->count();

        return response()->json(['data' => ['unread_count' => $count]]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->forceFill(['read_at' => $notification->read_at ?? now()])->save();

        return response()->json(['data' => $notification->refresh()]);
    }

    public function preferences(Request $request): JsonResponse
    {
        $user = $request->user();
        $emailSetting = SystemSetting::query()->where('setting_key', 'notifications.email.enabled')->first()?->value ?? false;
        $pushSetting = SystemSetting::query()->where('setting_key', 'notifications.push.enabled')->first()?->value ?? true;
        $configuredMailer = (string) (SystemSetting::query()->where('setting_key', 'notifications.email.mailer')->first()?->value ?? config('mail.default', 'smtp'));
        $emailEnabled = filter_var(is_array($emailSetting) ? ($emailSetting['enabled'] ?? false) : $emailSetting, FILTER_VALIDATE_BOOLEAN);
        $pushEnabled = filter_var(is_array($pushSetting) ? ($pushSetting['enabled'] ?? true) : $pushSetting, FILTER_VALIDATE_BOOLEAN);
        $stored = $user->notificationPreferences()->get()->keyBy('category');
        $categories = [];
        foreach (self::CATEGORIES as $key => $meta) {
            $preference = $stored->get($key);
            $categories[] = [
                'key' => $key,
                ...$meta,
                'push' => $preference?->push_enabled ?? $pushEnabled,
                'email' => $preference?->email_enabled ?? false,
            ];
        }

        return response()->json(['data' => [
            'categories' => $categories,
            'push_available' => $pushEnabled && (bool) config('services.fcm.enabled'),
            'email_available' => $emailEnabled && $configuredMailer !== 'log' && array_key_exists($configuredMailer, config('mail.mailers', [])),
            'email_mailer' => $configuredMailer,
            'email_global_enabled' => $emailEnabled,
            'push_global_enabled' => $pushEnabled,
        ]]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['required', 'array'],
            'categories.*.push' => ['sometimes', 'boolean'],
            'categories.*.email' => ['sometimes', 'boolean'],
        ]);

        foreach ($data['categories'] as $category => $channels) {
            abort_unless(array_key_exists($category, self::CATEGORIES), 422, 'Unknown notification category.');
            abort_if(self::CATEGORIES[$category]['locked'] ?? false, 422, 'Account and security notifications cannot be disabled.');
            $emailSetting = SystemSetting::query()->where('setting_key', 'notifications.email.enabled')->first()?->value ?? false;
            $emailEnabled = filter_var(is_array($emailSetting) ? ($emailSetting['enabled'] ?? false) : $emailSetting, FILTER_VALIDATE_BOOLEAN);
            $selectedMailer = (string) (SystemSetting::query()->where('setting_key', 'notifications.email.mailer')->first()?->value ?? config('mail.default', 'smtp'));
            $mailerConfigured = $selectedMailer !== 'log' && array_key_exists($selectedMailer, config('mail.mailers', []));
            abort_if(array_key_exists('email', $channels) && $channels['email'] && (! $emailEnabled || ! $mailerConfigured), 422, 'Email notifications are not enabled by the platform or mail is not configured.');
            $pushSetting = SystemSetting::query()->where('setting_key', 'notifications.push.enabled')->first()?->value ?? true;
            $pushEnabled = filter_var(is_array($pushSetting) ? ($pushSetting['enabled'] ?? true) : $pushSetting, FILTER_VALIDATE_BOOLEAN);
            abort_if(array_key_exists('push', $channels) && $channels['push'] && (! $pushEnabled || ! config('services.fcm.enabled')), 422, 'Push notifications are not enabled by the platform or Firebase is not configured.');
            $preference = UserNotificationPreference::query()->firstOrNew([
                'user_id' => $request->user()->id,
                'category' => $category,
            ]);
            if (array_key_exists('push', $channels)) $preference->push_enabled = $channels['push'];
            if (array_key_exists('email', $channels)) $preference->email_enabled = $channels['email'];
            $preference->save();
        }

        return $this->preferences($request);
    }

    public function registerDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'platform' => ['required', 'in:android,ios,web'],
            'push_token' => ['required', 'string', 'max:4096'],
        ]);
        $hash = hash('sha256', $data['push_token']);
        $device = UserDevice::query()->updateOrCreate(
            ['token_hash' => $hash],
            [
                'user_id' => $request->user()->id,
                'platform' => $data['platform'],
                'push_token' => $data['push_token'],
                'last_seen_at' => now(),
                'revoked_at' => null,
            ],
        );

        return response()->json(['data' => ['id' => $device->id, 'platform' => $device->platform]], 201);
    }

    public function revokeDevice(Request $request, UserDevice $device): JsonResponse
    {
        abort_unless($device->user_id === $request->user()->id, 404);
        $device->forceFill(['revoked_at' => now()])->save();

        return response()->json(['message' => 'Device push token revoked.']);
    }
}
