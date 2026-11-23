<?php

return [
    // Bounded audience fan-out keeps the request path and each builder job small.
    'audience_chunk_size' => (int) env('MAILING_AUDIENCE_CHUNK_SIZE', 500),
    'dispatch_chunk_size' => (int) env('MAILING_DISPATCH_CHUNK_SIZE', 200),
    // Bound queued deliveries globally and per campaign; delayed slots prevent queue burst/release storms.
    'max_inflight_recipients' => (int) env('MAILING_MAX_INFLIGHT_RECIPIENTS', 200),
    'per_campaign_inflight' => (int) env('MAILING_PER_CAMPAIGN_INFLIGHT', 20),
    'max_selected_recipients' => (int) env('MAILING_MAX_SELECTED_RECIPIENTS', 10000),
    'max_campaign_recipients' => (int) env('MAILING_MAX_CAMPAIGN_RECIPIENTS', 250000),
    // Global paced dispatch plus queue middleware to enforce the configured provider rate.
    'rate_per_minute' => (int) env('MAILING_RATE_PER_MINUTE', 60),
    'max_attempts' => (int) env('MAILING_MAX_ATTEMPTS', 3),
    'backoff_seconds' => [60, 300, 900],
    'max_html_bytes' => (int) env('MAILING_MAX_HTML_BYTES', 100000),
    'max_text_bytes' => (int) env('MAILING_MAX_TEXT_BYTES', 100000),
];
