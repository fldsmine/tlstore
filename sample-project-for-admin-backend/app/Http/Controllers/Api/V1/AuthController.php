<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LoginChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\Auth\LoginService;
use App\Services\Auth\RegistrationService;
use App\Services\SupportTicketAccess;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegistrationService $registration): JsonResponse
    {
        $data = $request->validated();

        [$first, $last] = $this->splitName((string) $data['full_name']);

        $user = $registration->register([
            'first_name' => $first,
            'last_name' => $last,
            'username' => $data['username'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'country' => $data['country'],
            'password' => $data['password'],
            'referral_code' => $data['referral_code'] ?? null,
        ]);

        $verificationChannel = $registration->dispatchVerification($user, $request->validated('init_data'));

        $token = $user->createToken($this->tokenName($request))->plainTextToken;

        return ApiResponse::created('Registration successful.', [
            'token' => $token,
            'token_type' => 'Bearer',
            'verification_required' => $verificationChannel !== null,
            'verification_channel' => $verificationChannel,
            'user' => new UserResource($user->load('package', 'referrer')),
        ]);
    }

    public function login(LoginRequest $request, LoginService $logins, SupportTicketAccess $supportAccess): JsonResponse
    {
        $login = (string) $request->validated('login');
        $password = (string) $request->validated('password');
        $ip = $request->ip();

        if ($logins->throttled($login, $ip)) {
            return ApiResponse::error(
                'Too many login attempts. Try again in '.$logins->availableIn($login, $ip).' seconds.',
                429,
            )->withHeaders(['Retry-After' => (string) $logins->availableIn($login, $ip)]);
        }

        $user = $logins->attempt($login, $password);

        if (! $user) {
            return ApiResponse::error('These credentials do not match our records.', 401);
        }

        if ($logins->isSuspended($user)) {
            return ApiResponse::error(
                'This account has been suspended. Please contact support.',
                403,
                data: $supportAccess->issue($user),
            );
        }

        // The API is what the Android app signs in through.
        $logins->touchLastLogin($user, LoginChannel::APP);

        $token = $user->createToken($this->tokenName($request))->plainTextToken;

        return ApiResponse::success('Login successful.', [
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user->load('package', 'referrer')),
        ]);
    }

    /**
     * Mini App → Livewire handoff. Logs the Sanctum-authenticated user into
     * the web guard so the WebView's next navigation (e.g. /dashboard) rides
     * a regular session cookie — the Livewire app sees the same signed-in
     * user, no second login.
     */
    public function webSession(Request $request): JsonResponse
    {
        $user = $request->user();

        \Illuminate\Support\Facades\Auth::guard('web')->login($user);
        $request->session()->regenerate();

        // Flag the session so the Livewire views render the Telegram-native
        // chrome (no app-bar, menu/search in the bottom nav, link guard).
        $request->session()->put('mini_app', true);

        return ApiResponse::success('Web session established.', [
            'redirect' => route('dashboard'),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        // Revoke ONLY the token this device used; other devices stay signed in.
        $token = $request->user()->currentAccessToken();

        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        }

        return ApiResponse::success('Logged out successfully.');
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user()->load('package', 'referrer', 'telegramIdentity');

        return ApiResponse::success('Authenticated user.', [
            'user' => new UserResource($user),
            'wallets' => $this->walletSummary($user),
        ]);
    }

    /** @return array<int, array{wallet: string, label: string, balance_usd: float}> */
    private function walletSummary(User $user): array
    {
        $wallets = collect(User::WALLET_COLUMNS)
            ->map(fn (string $column, string $wallet): array => [
                'wallet' => $wallet,
                'label' => User::WALLET_LABELS[$wallet] ?? $wallet,
                'balance_usd' => round((float) $user->{$column}, 6),
                'spendable' => in_array($wallet, User::SPENDABLE_WALLETS, true),
            ])
            ->values();

        $wallets->push([
            'wallet' => 'legacy_shareholder',
            'label' => 'Legacy Nextel Shares (locked)',
            'balance_usd' => round((float) $user->legacy_shareholder_balance, 6),
            'spendable' => false,
        ]);

        return $wallets->all();
    }

    private function splitName(string $name): array
    {
        $parts = explode(' ', trim($name), 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    private function tokenName(Request $request): string
    {
        $device = trim((string) $request->input('device_name', ''));

        return $device !== '' ? \Illuminate\Support\Str::limit($device, 60, '') : 'mobile';
    }
}
