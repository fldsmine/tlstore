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
<body class="antialiased bg-[#E8E4DB] text-text">

    {{-- Boxed frame to match the user app shell. --}}
    <div class="bg-background lg:max-w-[1280px] lg:mx-auto lg:flex lg:min-h-screen lg:shadow-[0_0_60px_rgba(0,0,0,0.08)]">
        <x-backoffice.sidebar />

        <div class="flex-1 min-w-0 min-h-screen">
            {{ $slot }}
        </div>
    </div>

    <livewire:backoffice.backoffice-search />
    <x-app.toasts />
    <x-backoffice.confirm-dialog />

    @livewireScripts
    {!! config('settings.analytics.footer_code') !!}
</body>
</html>
