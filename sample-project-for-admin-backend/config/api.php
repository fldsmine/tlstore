<?php

/*
|--------------------------------------------------------------------------
| API layer configuration
|--------------------------------------------------------------------------
*/

return [

    // Catch-all per-client request budget for /api/* (requests per minute).
    // Tighter, purpose-specific limiters (api-auth, api-register, api-otp,
    // api-sensitive) are defined in AppServiceProvider and applied per route.
    'rate_limit' => (int) env('API_RATE_LIMIT', 120),

];
