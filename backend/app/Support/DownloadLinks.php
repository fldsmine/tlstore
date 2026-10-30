<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * Links for the file downloads the dashboard offers (receipts, token exports).
 *
 * A download can be asked for by three different clients, and only two of them carry a session:
 *
 *   browser       — session cookie, works with a plain authenticated URL
 *   Android app   — its native downloader replays the WebView's cookies, so also fine
 *   Telegram Mini App — Telegram's own downloadFile() fetches the URL from the Telegram client,
 *                       which does NOT send the Mini App's session cookie
 *
 * So every link the dashboard renders is a TEMPORARY SIGNED URL, and the signature (not a cookie)
 * is what proves the download was authorised. The signed payload always carries `u` — the id of
 * the signed-in user the link was minted for — which is how a cookie-less request is resolved to
 * its owner (see signedUser()); the controller then scopes the file to that user, so a link can
 * only ever download the file of the person it was generated for.
 *
 * The session is still accepted by the download routes, so browsers and the Android app keep
 * working even after a link expires — a stale signature never breaks them.
 *
 * Everything that must survive a round trip has to go INSIDE $parameters: Laravel signs the exact
 * query string, so appending a parameter afterwards (…&download=1) invalidates the signature.
 */
class DownloadLinks
{
    /** How long a rendered download link stays valid. Long enough to save, short enough to not leak. */
    public const VALID_FOR_MINUTES = 60;

    /**
     * A signed, expiring URL for a named download route.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function signed(string $route, array $parameters = [], ?int $minutes = null): string
    {
        // `u` is added unless the caller set it explicitly (later keys win in array_merge).
        $parameters = array_merge(['u' => auth()->id()], $parameters);

        return URL::temporarySignedRoute(
            $route,
            now()->addMinutes($minutes ?? self::VALID_FOR_MINUTES),
            $parameters,
        );
    }

    /**
     * The user a signed, cookie-less download was minted for.
     *
     * Returns null when the request carries no `u` — a normal session request, whose user the
     * caller reads from the session instead. A request that does carry `u` is already past the
     * signature check (EnsureDownloadable), so the value can be trusted as the link's owner.
     */
    public static function signedUser(): ?User
    {
        $id = request()->query('u');

        return is_string($id) && $id !== '' ? User::query()->find($id) : null;
    }
}
