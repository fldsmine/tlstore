<?php

namespace App\Features\Notifications\Services;

use App\Enums\NotificationDeliveryStatus;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\UserDevice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class FirebaseCloudMessaging
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function send(Notification $notification, ?string $title = null, ?string $body = null): void
    {
        $preference = $notification->user->notificationPreferences()
            ->where('category', $notification->preferenceCategory())->first();
        if ($preference && ! $preference->push_enabled) {
            return;
        }

        $devices = UserDevice::query()
            ->where('user_id', $notification->user_id)
            ->whereNull('revoked_at')
            ->get();

        foreach ($devices as $device) {
            $delivery = NotificationDelivery::query()->create([
                'notification_id' => $notification->id,
                'user_device_id' => $device->id,
                'channel' => 'push',
                'provider' => 'fcm',
                'status' => NotificationDeliveryStatus::Queued,
            ]);

            try {
                [$projectId, $accessToken] = $this->credentials();
                $data = [];
                foreach (($notification->data ?? []) as $key => $value) {
                    if (is_scalar($value)) {
                        $data[(string) $key] = (string) $value;
                    }
                }
                $data['notification_id'] = (string) $notification->id;

                $response = Http::acceptJson()
                    ->withToken($accessToken)
                    ->timeout(12)
                    ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                        'message' => [
                            'token' => $device->push_token,
                            'notification' => [
                                'title' => $title ?? $notification->title,
                                'body' => $body ?? $notification->body ?? '',
                            ],
                            'data' => $data,
                            'android' => [
                                'priority' => 'HIGH',
                                'notification' => [
                                    'channel_id' => 'trustlens_updates',
                                    'sound' => 'default',
                                ],
                            ],
                            'apns' => [
                                'headers' => ['apns-priority' => '10'],
                                'payload' => ['aps' => ['sound' => 'default']],
                            ],
                        ],
                    ]);

                if ($response->successful()) {
                    $delivery->update([
                        'status' => NotificationDeliveryStatus::Sent,
                        'provider_message_id' => $response->json('name'),
                        'sent_at' => now(),
                    ]);

                    continue;
                }

                $errorCode = $response->json('error.details.0.errorCode');
                $errorStatus = $response->json('error.status');
                if (in_array($errorCode, ['UNREGISTERED', 'SENDER_ID_MISMATCH'], true)
                    || $errorStatus === 'NOT_FOUND') {
                    $device->forceFill(['revoked_at' => now()])->save();
                }

                $delivery->update([
                    'status' => NotificationDeliveryStatus::Failed,
                    'failure_reason' => mb_substr((string) ($response->json('error.message') ?? 'FCM rejected the notification.'), 0, 2000),
                ]);
            } catch (Throwable $error) {
                $delivery->update([
                    'status' => NotificationDeliveryStatus::Failed,
                    'failure_reason' => mb_substr($error->getMessage(), 0, 2000),
                ]);
                Log::warning('FCM notification delivery failed.', [
                    'notification_id' => $notification->id,
                    'device_id' => $device->id,
                    'error' => $error->getMessage(),
                ]);
            }
        }
    }

    /** @return array{string, string} Firebase project ID and OAuth access token. */
    private function credentials(): array
    {
        if (! config('services.fcm.enabled')) {
            throw new \RuntimeException('FCM_ENABLED is false.');
        }

        $path = config('services.fcm.credentials_path');
        if (! is_string($path) || $path === '' || ! is_file($path) || ! is_readable($path)) {
            throw new \RuntimeException('The FCM service-account key file is not configured or readable.');
        }

        $serviceAccount = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $email = $serviceAccount['client_email'] ?? null;
        $privateKey = $serviceAccount['private_key'] ?? null;
        $tokenUri = $serviceAccount['token_uri'] ?? 'https://oauth2.googleapis.com/token';
        $projectId = config('services.fcm.project_id') ?: ($serviceAccount['project_id'] ?? null);

        if (! is_string($email) || ! is_string($privateKey) || ! is_string($projectId) || $projectId === '') {
            throw new \RuntimeException('The FCM service-account key is missing client_email, private_key, or project_id.');
        }

        $cacheKey = 'fcm:oauth-token:'.hash('sha256', $email.'|'.$projectId);
        $accessToken = Cache::remember($cacheKey, now()->addMinutes(50), function () use ($email, $privateKey, $tokenUri): string {
            $now = time();
            $encode = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
            $header = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
            $claims = $encode(json_encode([
                'iss' => $email,
                'scope' => self::SCOPE,
                'aud' => $tokenUri,
                'iat' => $now,
                'exp' => $now + 3600,
            ], JSON_THROW_ON_ERROR));
            $unsigned = $header.'.'.$claims;
            if (! openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('Could not sign the FCM service-account assertion.');
            }
            $assertion = $unsigned.'.'.$encode($signature);

            $response = Http::asForm()->acceptJson()->timeout(12)->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);
            if (! $response->successful() || ! is_string($response->json('access_token'))) {
                throw new \RuntimeException('Google OAuth did not issue an FCM access token.');
            }

            return $response->json('access_token');
        });

        return [$projectId, $accessToken];
    }
}
