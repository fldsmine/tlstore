<?php

namespace App\Services\Telegram;

use App\Models\User;

/**
 * Tears down a Telegram ↔ Nextel binding. Shared by the Mini App API and the dashboard
 * profile page so both surfaces keep the same rule: the sessions a link minted go away
 * with the link, while password and web-session logins are untouched.
 */
class TelegramLinkService
{
    public function unlink(User $user): void
    {
        $user->telegramIdentity()->delete();

        // A `telegram-mini-app` token is only ever minted for a linked account, so
        // keeping it alive would leave instant sign-in working after an unlink.
        $user->tokens()->where('name', TelegramAuthService::TOKEN_NAME)->delete();

        // Keep the in-memory instance honest for whatever renders next.
        $user->unsetRelation('telegramIdentity');
    }
}
