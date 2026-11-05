<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

final class SocialIdentityVerifier
{
    /** @return array{provider_user_id:string,email:?string,full_name:?string,username:?string,email_verified:bool,avatar:?string} */
    public function verify(string $provider, array $credentials): array
    {
        return match ($provider) {
            'google' => $this->google($credentials),
            'facebook' => $this->facebook($credentials),
            'telegram' => $this->telegram($credentials),
            default => throw ValidationException::withMessages(['provider' => 'Supported providers are Google, Facebook, and Telegram.']),
        };
    }

    private function google(array $credentials): array
    {
        $idToken = $credentials['id_token'] ?? null;
        $clientId = config('services.social.google_client_id');
        if (! $idToken || ! $clientId) {
            throw ValidationException::withMessages(['provider' => 'Google sign-in is not configured or the ID token is missing.']);
        }

        $response = Http::timeout(8)->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);
        if (! $response->successful()) {
            throw ValidationException::withMessages(['id_token' => 'Google did not accept this ID token.']);
        }
        $profile = $response->json();
        if (($profile['aud'] ?? null) !== $clientId || ($profile['email_verified'] ?? null) !== 'true' || empty($profile['sub'])) {
            throw ValidationException::withMessages(['id_token' => 'The Google token audience or verified identity is invalid.']);
        }

        return [
            'provider_user_id' => (string) $profile['sub'], 'email' => $profile['email'] ?? null,
            'full_name' => $profile['name'] ?? null, 'username' => null, 'email_verified' => true,
            'avatar' => $profile['picture'] ?? null,
        ];
    }

    private function facebook(array $credentials): array
    {
        $accessToken = $credentials['access_token'] ?? null;
        $appId = config('services.social.facebook_app_id');
        $appSecret = config('services.social.facebook_app_secret');
        if (! $accessToken || ! $appId || ! $appSecret) {
            throw ValidationException::withMessages(['provider' => 'Facebook sign-in is not configured or the access token is missing.']);
        }

        $appToken = $appId.'|'.$appSecret;
        $debug = Http::timeout(8)->get('https://graph.facebook.com/debug_token', [
            'input_token' => $accessToken, 'access_token' => $appToken,
        ]);
        $tokenData = $debug->json('data', []);
        if (! $debug->successful() || ! ($tokenData['is_valid'] ?? false) || ($tokenData['app_id'] ?? null) !== $appId) {
            throw ValidationException::withMessages(['access_token' => 'Facebook did not accept this access token for this application.']);
        }

        $profileResponse = Http::withToken($accessToken)->timeout(8)->get('https://graph.facebook.com/me', [
            'fields' => 'id,name,email,picture.type(large)',
        ]);
        $profile = $profileResponse->json();
        if (! $profileResponse->successful() || empty($profile['id']) || (string) $profile['id'] !== (string) ($tokenData['user_id'] ?? '')) {
            throw ValidationException::withMessages(['access_token' => 'The Facebook profile does not match the verified token.']);
        }

        return [
            'provider_user_id' => (string) $profile['id'], 'email' => $profile['email'] ?? null,
            'full_name' => $profile['name'] ?? null, 'username' => null, 'email_verified' => false,
            'avatar' => $profile['picture']['data']['url'] ?? null,
        ];
    }

    private function telegram(array $credentials): array
    {
        $botToken = config('services.social.telegram_bot_token');
        $hash = $credentials['hash'] ?? null;
        if (! $botToken || ! $hash || empty($credentials['id']) || empty($credentials['auth_date'])) {
            throw ValidationException::withMessages(['provider' => 'Telegram sign-in is not configured or signed login data is incomplete.']);
        }
        if ((int) $credentials['auth_date'] > now()->timestamp || now()->timestamp - (int) $credentials['auth_date'] > 86400) {
            throw ValidationException::withMessages(['auth_date' => 'Telegram login data has expired.']);
        }

        $data = array_filter($credentials, fn ($value, $key) => $key !== 'hash' && ! is_array($value), ARRAY_FILTER_USE_BOTH);
        ksort($data);
        $checkString = implode("\n", array_map(fn ($key, $value) => $key.'='.$value, array_keys($data), array_values($data)));
        $secretKey = hash('sha256', $botToken, true);
        $expectedHash = hash_hmac('sha256', $checkString, $secretKey);
        if (! hash_equals($expectedHash, (string) $hash)) {
            throw ValidationException::withMessages([
                'hash' => 'Telegram login signature is invalid. Confirm TELEGRAM_BOT_TOKEN belongs to the bot configured for the login widget.',
            ]);
        }

        return [
            'provider_user_id' => (string) $credentials['id'], 'email' => null,
            'full_name' => trim(($credentials['first_name'] ?? '').' '.($credentials['last_name'] ?? '')) ?: null,
            'username' => $credentials['username'] ?? null, 'email_verified' => false,
            'avatar' => $credentials['photo_url'] ?? null,
        ];
    }
}
