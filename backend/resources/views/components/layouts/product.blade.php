<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('partials.favicons')

        <title>{{ isset($title) ? $title.' — '.config('app.name') : 'Buy an eSIM — '.config('app.name') }}</title>
        <meta name="description" content="{{ $description ?? 'Buy a Nextel eSIM and start earning from sponsored calls and messages. No account needed — pay securely and we send your order to your email.' }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        {!! config('settings.analytics.header_code') !!}
    </head>
    <body class="antialiased bg-background text-text font-sans flex flex-col min-h-screen">
        @php
            // Anchors only resolve on the storefront itself; from a checkout or receipt page they have
            // to travel back to the index first, so the nav links are absolute there.
            $onIndex = request()->routeIs('product.index', 'product.index.host');
            $anchor = fn (string $fragment): string => $onIndex
                ? '#'.$fragment
                : product_index_url().'#'.$fragment;
        @endphp

        {{-- Storefront chrome: its own nav, because this domain exists to sell — and everything it
             links to lives on the main site. --}}
        <header class="border-b border-muted/10 bg-background/90 backdrop-blur sticky top-0 z-30">
            <div class="max-w-[1280px] mx-auto px-5 md:px-[60px] h-[68px] flex items-center justify-between gap-4">
                <a href="{{ product_index_url() }}" wire:navigate class="flex items-center gap-2.5 shrink-0">
                    <img src="{{ asset('images/public/logo-coloured.png') }}" alt="{{ config('app.name') }}" class="h-8 w-auto object-contain">
                    <span class="font-sans text-[11px] uppercase tracking-[0.18em] text-muted hidden lg:inline">Store</span>
                </a>

                <nav class="hidden md:flex items-center gap-7 font-sans text-[14px]">
                    <a href="{{ product_index_url() }}" wire:navigate class="text-text hover:text-primary transition-colors">Store home</a>
                    <a href="{{ $anchor('products') }}" class="text-muted hover:text-primary transition-colors">Products</a>
                    <a href="{{ $anchor('features') }}" class="text-muted hover:text-primary transition-colors">Features</a>
                    <a href="{{ $anchor('faq') }}" class="text-muted hover:text-primary transition-colors">FAQ</a>

                    <div x-data="{ p: false }" class="relative" @mouseenter="p = true" @mouseleave="p = false">
                        <button type="button" class="flex items-center gap-1.5 text-muted hover:text-primary transition-colors">
                            More
                            <i class="ri-arrow-down-s-line text-[15px]"></i>
                        </button>
                        <div x-show="p" x-cloak x-transition class="absolute right-0 top-full pt-3 w-52">
                            <div class="bg-surface border border-muted/15 rounded-2xl shadow-lg p-2 flex flex-col">
                                <a href="{{ product_main_url() }}" class="px-4 py-2.5 rounded-xl text-muted hover:text-primary hover:bg-background transition-colors">Main site</a>
                                <a href="{{ product_main_url('top-earners') }}" class="px-4 py-2.5 rounded-xl text-muted hover:text-primary hover:bg-background transition-colors">Top earners</a>
                                <a href="{{ product_main_url('contact') }}" class="px-4 py-2.5 rounded-xl text-muted hover:text-primary hover:bg-background transition-colors">Contact us</a>
                                <a href="{{ product_main_url('terms') }}" class="px-4 py-2.5 rounded-xl text-muted hover:text-primary hover:bg-background transition-colors">Terms</a>
                                <a href="{{ product_main_url('privacy') }}" class="px-4 py-2.5 rounded-xl text-muted hover:text-primary hover:bg-background transition-colors">Privacy</a>
                            </div>
                        </div>
                    </div>
                </nav>

                <div class="flex items-center gap-2 sm:gap-3">
                    <a href="{{ product_main_url() }}"
                       class="hidden sm:inline-flex items-center h-[38px] px-4 rounded-full border border-muted/20 font-sans text-[13px] text-text hover:border-primary/40 hover:text-primary transition-colors">
                        Download app
                    </a>
                    <a href="{{ product_main_url('auth/login') }}"
                       class="inline-flex items-center h-[38px] px-4 rounded-full bg-primary text-white font-sans text-[13px] hover:bg-primary/90 transition-colors">
                        Sign in
                    </a>
                </div>
            </div>
        </header>

        <main class="flex-1">
            {{ $slot }}
        </main>

        <footer class="border-t border-muted/15 bg-surface/40">
            <div class="max-w-[1280px] mx-auto px-5 md:px-[60px] py-12 md:py-14 grid gap-10 md:grid-cols-[1.4fr_1fr_1fr_1fr]">
                <div class="flex flex-col gap-4">
                    <img src="{{ asset('images/public/logo-coloured.png') }}" alt="{{ config('app.name') }}" class="h-9 w-auto object-contain self-start">
                    <p class="font-sans text-muted text-[13px] leading-relaxed max-w-[320px]">
                        The official {{ config('app.name') }} store. Buy an eSIM plan or catalog product as a guest, pay securely,
                        and we send it straight to your email.
                    </p>
                    <div class="flex items-center gap-2 font-sans text-[12px] text-muted">
                        <i class="ri-shield-check-line text-primary"></i>
                        Card payments handled by our payment partner
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <p class="font-heading font-medium text-text text-[14px]">Store</p>
                    <ul class="flex flex-col gap-2.5 font-sans text-[13px]">
                        <li><a href="{{ $anchor('products') }}" class="text-muted hover:text-primary transition-colors">All products</a></li>
                        <li><a href="{{ $anchor('features') }}" class="text-muted hover:text-primary transition-colors">Why Nextel</a></li>
                        <li><a href="{{ $anchor('faq') }}" class="text-muted hover:text-primary transition-colors">FAQ</a></li>
                        <li><a href="{{ product_main_url() }}" class="text-muted hover:text-primary transition-colors">Download the app</a></li>
                    </ul>
                </div>

                <div class="flex flex-col gap-3">
                    <p class="font-heading font-medium text-text text-[14px]">Company</p>
                    <ul class="flex flex-col gap-2.5 font-sans text-[13px]">
                        <li><a href="{{ product_main_url() }}" class="text-muted hover:text-primary transition-colors">Home</a></li>
                        <li><a href="{{ product_main_url('about') }}" class="text-muted hover:text-primary transition-colors">About us</a></li>
                        <li><a href="{{ product_main_url('agents') }}" class="text-muted hover:text-primary transition-colors">Distributors</a></li>
                        <li><a href="{{ product_main_url('top-earners') }}" class="text-muted hover:text-primary transition-colors">Top earners</a></li>
                    </ul>
                </div>

                <div class="flex flex-col gap-3">
                    <p class="font-heading font-medium text-text text-[14px]">Support</p>
                    <ul class="flex flex-col gap-2.5 font-sans text-[13px]">
                        <li><a href="{{ product_main_url('contact') }}" class="text-muted hover:text-primary transition-colors">Contact us</a></li>
                        <li><a href="{{ product_main_url('verify-token') }}" class="text-muted hover:text-primary transition-colors">Verify a token</a></li>
                        <li><a href="{{ product_main_url('terms') }}" class="text-muted hover:text-primary transition-colors">Terms</a></li>
                        <li><a href="{{ product_main_url('privacy') }}" class="text-muted hover:text-primary transition-colors">Privacy policy</a></li>
                        @if (config('settings.contact.email'))
                            <li><a href="mailto:{{ config('settings.contact.email') }}" class="text-muted hover:text-primary transition-colors">Email support</a></li>
                        @endif
                    </ul>
                </div>
            </div>

            <div class="border-t border-muted/15">
                <div class="max-w-[1280px] mx-auto px-5 md:px-[60px] py-5 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <p class="font-sans text-muted text-[12px] text-center sm:text-left">
                        &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                    </p>
                    <p class="font-sans text-muted text-[12px]">Prices in naira. Guest checkout — no account required.</p>
                </div>
            </div>
        </footer>

        @livewireScripts
        {!! config('settings.analytics.footer_code') !!}
    </body>
</html>
