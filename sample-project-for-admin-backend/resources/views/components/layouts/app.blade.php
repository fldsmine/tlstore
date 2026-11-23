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
<body class="antialiased bg-[#E8E4DB] text-text">

    @if (session()->has('impersonator_admin_id'))
        <div class="sticky top-0 z-[60] bg-warning text-[#3a2a00] px-4 py-2 flex items-center justify-center gap-3 font-sans text-[13px] shadow-sm">
            <i class="ri-spy-line text-[16px] shrink-0"></i>
            <span class="truncate">You are viewing the app as <span class="font-semibold">{{ auth()->user()?->full_name }}</span> (@<span>{{ auth()->user()?->username }}</span>)</span>
            <form method="POST" action="{{ route('backoffice.impersonate.stop') }}" class="shrink-0">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-full bg-[#3a2a00] text-warning px-3 py-1 font-semibold hover:opacity-90 transition">
                    <i class="ri-logout-box-r-line text-[14px]"></i> Return to admin
                </button>
            </form>
        </div>
    @endif

    <div class="bg-background lg:max-w-[1280px] lg:mx-auto lg:flex lg:min-h-screen lg:shadow-[0_0_60px_rgba(0,0,0,0.08)]">
        <x-app.sidebar />

        <div class="flex-1 min-w-0 min-h-screen">
            {{ $slot }}
        </div>
    </div>

    @auth
        <livewire:dashboard.global-search />
        <livewire:dashboard.activate />

        {{-- Pushes new chat requests + messages to the recipient on any page, linking to the chat. --}}
        @if (config('settings.chat.enabled'))
            <livewire:dashboard.chat.chat-notifier />
        @endif

        {{-- Rings + answers incoming sponsored calls on ANY page (cloud users only). --}}
        @if (auth()->user()->canCloudCall() || (float) auth()->user()->cloud_call_balance > 0 || auth()->user()->canCloudSms() || (float) auth()->user()->cloud_sms_balance > 0)
            <livewire:dashboard.cloud.incoming-call-dock />
        @endif

        {{-- Toasts new messages on ANY page + keeps the sidebar badge live. --}}
        @if (auth()->user()->cloud_line_number)
            <livewire:dashboard.cloud.inbox-notifier />
        @endif
    @endauth

    <x-app.toasts />
    <x-app.confirm-dialog />
    <x-app.lightbox />
    @auth <x-app.push-prompt /> @endauth

    @if (session('mini_app'))
        {{-- Telegram Mini App WebView: there are no extra tabs or windows.
             Internal target="_blank" anchors navigate in place; external URLs
             open through Telegram's own browser. The official SDK is loaded here
             (the Mini App shell page loads it too, but the dashboard is a separate
             document) so downloads can go through Telegram.WebApp.downloadFile and
             links through openLink. --}}
        <script src="https://telegram.org/js/telegram-web-app.js"></script>
        <script>
            document.addEventListener('click', (event) => {
                const anchor = event.target.closest('a[target="_blank"]');

                if (! anchor || ! anchor.href) return;

                let url;

                try {
                    url = new URL(anchor.href, window.location.href);
                } catch {
                    return;
                }

                event.preventDefault();

                if (url.origin === window.location.origin) {
                    window.location.assign(url.href);
                    return;
                }

                const telegram = window.Telegram && window.Telegram.WebApp;

                if (telegram && typeof telegram.openLink === 'function') {
                    telegram.openLink(url.href);
                } else {
                    window.location.assign(url.href);
                }
            }, true);
        </script>
    @endif

    @livewireScripts
    {!! config('settings.analytics.footer_code') !!}
</body>
</html>
