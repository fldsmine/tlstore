<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
    <body class="antialiased bg-background text-text font-sans">
        {{-- Mobile: single column. Desktop (lg+): asymmetric 38/62 brand-rail split. --}}
        <div class="min-h-screen w-full bg-background lg:grid lg:grid-cols-[38fr_62fr]">

            {{-- Sticky full-height so tall forms scroll without dragging the art. --}}
            <aside class="hidden lg:flex lg:sticky lg:top-0 lg:h-screen relative overflow-hidden bg-primary text-surface flex-col justify-between p-12 xl:p-16">
                <div class="absolute -top-[90px] -right-[70px] size-[300px] rounded-full bg-[#e7e2d5]/10"></div>
                <div class="absolute top-[30px] right-[40px] size-[150px] rounded-full bg-[#ddd6c6]/[0.08]"></div>
                <div class="absolute top-[42%] -left-[170px] size-[320px] rounded-full bg-primary-light/20"></div>
                <div class="absolute -bottom-[150px] -right-[110px] size-[400px] rounded-full bg-secondary/[0.08]"></div>
                <div class="absolute bottom-[24%] right-[12%] size-[14px] rounded-full bg-secondary"></div>

                <div class="relative z-10">
                    <a href="{{ route('home') }}" wire:navigate class="inline-flex items-center">
                        <img src="{{ asset('images/public/logo-coloured.png') }}" alt="Nextel Connect" class="h-11 w-auto">
                    </a>
                </div>

                <div class="relative z-10 max-w-[420px]">
                    <h2 class="font-heading font-semibold text-surface text-[34px] xl:text-[40px] leading-[1.1]">
                        Your minutes are<br><span class="text-secondary">worth more</span> here.
                    </h2>
                    <p class="font-sans text-surface/70 text-[15px] leading-relaxed mt-5 max-w-[360px]">
                        Earn from every call, grow as a shareholder, and cash out anytime — all on one trusted network.
                    </p>
                </div>

                <div class="relative z-10 inline-flex items-center gap-3 font-sans text-surface/60 text-[13px]">
                    <span class="size-2 rounded-full bg-secondary"></span>
                    Join thousands of Nigerians earning &amp; growing with Nextel
                </div>
            </aside>

            <main class="relative bg-background lg:flex lg:items-center lg:justify-center lg:px-12 xl:px-20 lg:py-16 lg:min-h-screen">
                <div class="mx-auto w-full max-w-[440px] lg:max-w-none min-h-screen lg:min-h-0 bg-background relative overflow-hidden lg:overflow-visible">
                    {{ $slot }}
                </div>
            </main>
        </div>

        <x-app.toasts />

        @livewireScripts
        {!! config('settings.analytics.footer_code') !!}
    </body>
</html>
