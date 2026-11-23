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
<body class="antialiased bg-[#E8E4DB] text-text">

    {{-- Boxed frame matching the design reference's app shell. --}}
    <div class="bg-background lg:max-w-[1280px] lg:mx-auto lg:flex lg:min-h-screen lg:shadow-[0_0_60px_rgba(0,0,0,0.08)]">
        <x-backoffice.sidebar />

        <div class="flex-1 min-w-0 min-h-screen">
            {{ $slot }}
        </div>
    </div>

    <x-backoffice.confirm-dialog />
    <x-app.toasts />

    @livewireScripts
</body>
</html>
