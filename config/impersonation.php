<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Administrator impersonation
    |--------------------------------------------------------------------------
    |
    | A super administrator can open the Flutter web app signed in as a user to
    | reproduce a problem or help them through a flow. The console requests a
    | one-time hand-off code, the web app exchanges it for a short-lived user
    | token, and both steps are written to `audit_logs`.
    |
    | `link_ttl_seconds` is how long the hand-off link stays usable (it is also
    | single use). `session_minutes` is the lifetime of the user token the web
    | app receives, so an impersonation session can never outlive it even if the
    | tab is left open.
    |
    */

    'enabled' => filter_var(env('ADMIN_IMPERSONATION_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    'link_ttl_seconds' => max(30, (int) env('ADMIN_IMPERSONATION_LINK_TTL_SECONDS', 120)),

    'session_minutes' => max(1, (int) env('ADMIN_IMPERSONATION_SESSION_MINUTES', 60)),
];
