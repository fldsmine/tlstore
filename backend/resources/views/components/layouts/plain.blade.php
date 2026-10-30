<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.favicons')
    <title>{{ isset($title) ? $title.' — '.config('app.name') : config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    {!! config('settings.analytics.header_code') !!}
</head>
<body class="antialiased bg-background text-text min-h-screen flex items-stretch sm:items-center justify-center px-4 py-2 sm:py-4">
    {{ $slot }}

    <x-app.toasts />

    @livewireScripts
    {!! config('settings.analytics.footer_code') !!}
</body>
</html>
