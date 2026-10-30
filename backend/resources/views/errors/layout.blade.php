<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', 'Error') — Nextel Connect</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="antialiased relative bg-background text-text font-sans min-h-screen flex flex-col overflow-hidden">
        <div class="pointer-events-none absolute -top-[120px] -right-[100px] size-[360px] rounded-full bg-primary/[0.06]"></div>
        <div class="pointer-events-none absolute -bottom-[150px] -left-[120px] size-[420px] rounded-full bg-secondary/[0.12]"></div>

        <header class="relative z-10 px-6 md:px-[60px] py-7">
            <a href="{{ url('/') }}" class="inline-flex" aria-label="Nextel Connect home">
                <img src="{{ asset('images/public/logo-dark.png') }}" alt="Nextel Connect" class="h-9 w-auto object-contain">
            </a>
        </header>

        <main class="relative z-10 flex-1 flex flex-col items-center justify-center text-center px-6 pb-24">
            <div class="size-[88px] rounded-full bg-primary/10 text-primary flex items-center justify-center text-[40px] mb-7">
                <i class="@yield('icon', 'ri-error-warning-line')"></i>
            </div>

            @hasSection('code')
                <p class="font-heading font-bold text-primary text-[64px] md:text-[84px] leading-none tracking-tight">@yield('code')</p>
            @endif

            <h1 class="font-heading font-semibold text-text text-[26px] md:text-[30px] mt-3">@yield('title', 'Something went wrong')</h1>
            <p class="font-sans text-muted text-[16px] max-w-[460px] mt-3 leading-relaxed">@yield('message')</p>

            <div class="mt-9">
                @hasSection('action')
                    @yield('action')
                @else
                    <a href="{{ url('/') }}" class="btn-primary btn-md inline-flex items-center gap-2">
                        <i class="ri-arrow-left-line"></i> Back to Home
                    </a>
                @endif
            </div>
        </main>
    </body>
</html>
