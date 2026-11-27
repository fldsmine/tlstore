<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Telegram notification bot
    |--------------------------------------------------------------------------
    |
    | One bot posts admin announcements and withdrawal notifications to a
    | group or channel. The token can also be entered in the admin console,
    | where it is stored encrypted with APP_KEY; a value saved there wins over
    | these environment defaults. `TELEGRAM_BOT_TOKEN` is shared with the
    | Telegram login widget, so a single bot can serve both features.
    |
    */

    'api_base' => env('TELEGRAM_API_BASE', 'https://api.telegram.org'),

    // `?:` rather than a default so an empty TELEGRAM_NOTIFY_BOT_TOKEN= line
    // still falls back to the shared bot.
    'token' => env('TELEGRAM_NOTIFY_BOT_TOKEN') ?: env('TELEGRAM_BOT_TOKEN'),

    // Group id (e.g. -1001234567890) or public channel username (@tlstore).
    'chat_id' => env('TELEGRAM_NOTIFY_CHAT_ID') ?: null,

    'timeout' => (int) env('TELEGRAM_TIMEOUT', 15),

    'connect_timeout' => (int) env('TELEGRAM_CONNECT_TIMEOUT', 5),

    // Delivery retries for queued notifications: 3 attempts, spaced out so a
    // Telegram hiccup or rate-limit cools down without losing the message.
    'tries' => 3,

    'backoff' => [15, 120, 600],

    // Telegram Bot API limits.
    'message_max_length' => 4096,

    'caption_max_length' => 1024,

    'photo_max_bytes' => 5 * 1024 * 1024,

    'document_max_bytes' => 20 * 1024 * 1024,

    // Attach the receipt as an inline photo when Ghostscript is available to
    // rasterise the PDF; otherwise the PDF itself is attached as a document.
    // Ghostscript is auto-detected, or point TELEGRAM_GHOSTSCRIPT_BINARY at it.
    'receipt_image' => env('TELEGRAM_RECEIPT_IMAGE', true),

    'ghostscript_binary' => env('TELEGRAM_GHOSTSCRIPT_BINARY', ''),

    // Local (private) disk paths for admin uploads and generated receipts.
    'announcement_directory' => 'telegram/announcements',

    'receipt_directory' => 'telegram/receipts',

];
