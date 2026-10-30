<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.favicons')
    <title>{{ isset($title) ? $title.' — '.config('app.name').' Backoffice' : config('app.name').' Backoffice' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    {!! config('settings.analytics.header_code') !!}
</head>
<body class="antialiased text-text min-h-screen flex items-center justify-center p-4"
      style="background: radial-gradient(125% 110% at 78% 78%, #2E726699 0%, #18443E 52%, #123A34 100%); background-color: #123A34;">

    {{ $slot }}

    <x-app.toasts />

    @livewireScripts
    {!! config('settings.analytics.footer_code') !!}
</body>
</html>
