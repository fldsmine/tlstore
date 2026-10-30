<?php

return [
    // Share the per-user marker across clients; the default suppression window is 24 hours.
    'cache_minutes' => env('WITHDRAWAL_TELEGRAM_REDIRECT_CACHE_MINUTES', 1440),
];
