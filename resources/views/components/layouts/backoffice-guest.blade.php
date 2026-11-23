<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' — '.config('backoffice.name') : config('backoffice.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/backoffice.css', 'resources/js/backoffice.js'])
    @livewireStyles
</head>
<body class="antialiased text-text min-h-screen flex items-center justify-center p-4"
      style="background: radial-gradient(125% 110% at 78% 78%, #2E726699 0%, #18443E 52%, #123A34 100%); background-color: #123A34;">

    {{ $slot }}

    <x-app.toasts />

    @livewireScripts
</body>
</html>
