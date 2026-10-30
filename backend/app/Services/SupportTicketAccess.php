<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

/** Issues short-lived, support-only bearer tokens for accounts that cannot sign in normally. */
class SupportTicketAccess
{
    /** @return array{support_token: string, user: array{id: string, name: string, email: string}} */
    public function issue(User $user): array
    {
        $token = $user->createToken(
            'android-support-'.Str::lower(Str::random(8)),
            ['support-tickets'],
            now()->addHours(2),
        );

        // Suspension invalidates every previous device/session token, so no full
        // token can become useful again if the account is later reactivated.
        $user->tokens()
            ->where('id', '!=', $token->accessToken->getKey())
            ->delete();

        return [
            'support_token' => $token->plainTextToken,
            'user' => [
                'id' => (string) $user->id,
                'name' => $user->full_name,
                'email' => (string) $user->email,
            ],
        ];
    }
}
