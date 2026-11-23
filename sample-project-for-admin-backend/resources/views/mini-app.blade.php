<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#18443E">
    <title>Nextel</title>

    {{-- Telegram WebApp runtime — provides Telegram.WebApp (initData, MainButton, theme…). --}}
    <script src="https://telegram.org/js/telegram-web-app.js"></script>

    @include('partials.favicons')
    @vite(['resources/css/mini-app.css', 'resources/js/mini-app.js'])
</head>
<body class="mini-app-body">
    <div id="mini-app" aria-busy="true"></div>
</body>
</html>
