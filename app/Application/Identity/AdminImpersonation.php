<?php

namespace App\Application\Identity;

use App\Exceptions\ImpersonationUnavailable;
use App\Features\Admin\Support\AdminAuditLogger;
use App\Models\Admin;
use App\Models\AdminImpersonationLink;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Issues and redeems the one-time hand-off an administrator uses to open the
 * Flutter web app as a user.
 *
 * Nothing here hands out a long-lived credential: the link code lives for
 * {@see config('impersonation.link_ttl_seconds')} seconds, can be redeemed
 * once, and the resulting user token expires after
 * {@see config('impersonation.session_minutes')} minutes.
 */
final class AdminImpersonation
{
    public function __construct(private readonly AdminAuditLogger $audit) {}

    public function enabled(): bool
    {
        return (bool) config('impersonation.enabled', true);
    }

    public static function allows(Admin $admin): bool
    {
        return $admin->role === 'super_admin';
    }

    /**
     * Create a single-use hand-off link for `$account` and return the plain
     * code and the URL the administrator should open.
     *
     * @return array{link: AdminImpersonationLink, code: string, url: string, expires_at: Carbon}
     */
    public function issue(Admin $admin, User $account): array
    {
        $this->guardIssuer($admin, $account);
        $this->assertAccountCanBeImpersonated($account);

        $code = Str::random(64);
        $expiresAt = now()->addSeconds((int) config('impersonation.link_ttl_seconds', 120));

        $link = AdminImpersonationLink::query()->create([
            'admin_id' => $admin->id,
            'user_id' => $account->id,
            'code_hash' => hash('sha256', $code),
            'expires_at' => $expiresAt,
        ]);

        $this->audit->log('user.impersonation_link_issued', $account, null, [
            'link_id' => $link->id,
            'admin_id' => $admin->id,
            'expires_at' => $expiresAt->toISOString(),
        ], $admin->id);

        return [
            'link' => $link,
            'code' => $code,
            'url' => $this->handoffUrl($code),
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Redeem a hand-off code: marks it used and returns the user token the web
     * app should store.
     *
     * @return array{user: User, token: string, expires_at: Carbon, admin: Admin, link: AdminImpersonationLink}
     */
    public function redeem(string $code, ?Request $request = null): array
    {
        if (! $this->enabled()) {
            throw new ImpersonationUnavailable('Administrator impersonation is disabled on this deployment.');
        }

        $code = trim($code);
        if ($code === '') {
            throw new ImpersonationUnavailable('This impersonation link is missing its code.');
        }

        $link = DB::transaction(function () use ($code, $request): AdminImpersonationLink {
            /** @var AdminImpersonationLink|null $link */
            $link = AdminImpersonationLink::query()
                ->where('code_hash', hash('sha256', $code))
                ->lockForUpdate()
                ->first();

            if (! $link) {
                throw new ImpersonationUnavailable('This impersonation link is not valid.');
            }
            if ($link->used_at !== null) {
                throw new ImpersonationUnavailable('This impersonation link has already been used. Ask the administrator for a new one.');
            }
            if ($link->expires_at->isPast()) {
                throw new ImpersonationUnavailable('This impersonation link has expired. Ask the administrator for a new one.');
            }

            $link->forceFill([
                'used_at' => now(),
                'consumed_ip' => $request?->ip(),
                'consumed_user_agent' => $request ? Str::limit((string) $request->userAgent(), 255, '') : null,
            ])->save();

            return $link;
        });

        $admin = $link->admin;
        $account = $link->user;

        if (! $admin || $admin->status !== 'active' || ! self::allows($admin)) {
            throw new ImpersonationUnavailable('The administrator who created this link no longer has impersonation access.');
        }
        if (! $account) {
            throw new ImpersonationUnavailable('The account no longer exists.');
        }
        $this->assertAccountCanBeImpersonated($account);

        $expiresAt = now()->addMinutes((int) config('impersonation.session_minutes', 60));
        $newToken = $account->createToken('admin-impersonation:'.$admin->id, ['*'], $expiresAt);
        $link->forceFill(['personal_access_token_id' => $newToken->accessToken->getKey()])->save();

        $this->audit->log('user.impersonation_session_started', $account, null, [
            'link_id' => $link->id,
            'admin_id' => $admin->id,
            'personal_access_token_id' => $link->personal_access_token_id,
            'expires_at' => $expiresAt->toISOString(),
        ], $admin->id);

        return [
            'user' => $account,
            'token' => $newToken->plainTextToken,
            'expires_at' => $expiresAt,
            'admin' => $admin,
            'link' => $link,
        ];
    }

    /** The Flutter web app reads the code from `?impersonation=` on the app root. */
    public function handoffUrl(string $code): string
    {
        return url('/').'?impersonation='.urlencode($code);
    }

    private function guardIssuer(Admin $admin, User $account): void
    {
        if (! $this->enabled()) {
            throw new ImpersonationUnavailable('Administrator impersonation is disabled on this deployment.');
        }
        if (! self::allows($admin)) {
            throw new ImpersonationUnavailable('Only super administrators can impersonate an account.');
        }
        if ($admin->status !== 'active') {
            throw new ImpersonationUnavailable('This administrator account is not active.');
        }
    }

    /**
     * The mobile/web API rejects sessions for accounts that are not active, so
     * an impersonation link for such an account would only open a broken tab.
     */
    private function assertAccountCanBeImpersonated(User $account): void
    {
        if ($account->trashed()) {
            throw new ImpersonationUnavailable('Restore the account before impersonating it.');
        }
        if ($account->is_banned || ($account->status?->value ?? $account->status) !== 'active') {
            throw new ImpersonationUnavailable('Only active accounts can be impersonated. Set the status to active (and lift any ban) first.');
        }
    }
}
