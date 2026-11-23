<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @include('partials.favicons')

        <title>{{ isset($title) ? $title.' — '.config('app.name') : config('app.name').' — Stay Connected, Earn Every Day' }}</title>
        <meta name="description" content="{{ $description ?? config('app.name').' is a virtual telecom operator and performance ad network. Activate your eSIM, stay connected, and earn from sponsored calls, SMS, and ads.' }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        {!! config('settings.analytics.header_code') !!}
    </head>
    <body class="antialiased bg-background text-text font-sans">
        <x-public.navbar />

        <div class="relative overflow-x-clip">
            <main>
                {{ $slot }}
            </main>

            <x-public.footer />
        </div>

        @livewireScripts
        {!! config('settings.analytics.footer_code') !!}
    </body>
</html>
