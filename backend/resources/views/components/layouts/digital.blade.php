<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ isset($title) ? $title.' — '.config('app.name') : 'Nurture Calls — Digital Communication Business Guide' }}</title>
  @vite(['resources/css/digital.css', 'resources/js/digital.js'])
  @if (request()->routeIs('digital.checkout'))
    @vite('resources/css/product-details.css')
  @endif
  @livewireStyles
  {!! config('settings.analytics.header_code') ?? '' !!}
</head>
<body class="antialiased">
  {{ $slot }}
  @livewireScripts
</body>
</html>
