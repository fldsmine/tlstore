<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Opening Telegram</title>
    <noscript><meta http-equiv="refresh" content="0;url={{ $destination }}"></noscript>

    @if (session('mini_app'))
        <script src="https://telegram.org/js/telegram-web-app.js"></script>
    @endif

    <script>
        (() => {
            const destination = @json($destination);
            const telegram = window.Telegram && window.Telegram.WebApp;

            if (telegram && typeof telegram.openTelegramLink === 'function') {
                try {
                    telegram.openTelegramLink(destination);
                    return;
                } catch {
                    // Fall through to a regular navigation if the native API is unavailable.
                }
            }

            window.location.replace(destination);
        })();
    </script>
</head>
<body>
    <p role="status">Opening Telegram… <a href="{{ $destination }}">Continue to Telegram</a></p>
</body>
</html>
