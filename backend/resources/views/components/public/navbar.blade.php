{{-- Off-canvas must be a SIBLING of <nav>: the nav's backdrop-blur creates a containing block that would trap the `fixed inset-0` overlay inside the navbar height. --}}
<div x-data="{ open: false, scrolled: false }" @scroll.window="scrolled = window.scrollY > 8">

    <nav class="fixed top-0 inset-x-0 z-50 backdrop-blur-md transition-all duration-300"
         :class="scrolled ? 'bg-background/85 border-b border-primary/10 shadow-[0_2px_24px_rgba(0,0,0,0.05)]' : 'bg-background/30'">

        <div class="max-w-[1280px] mx-auto px-5 md:px-[60px]">
            <div class="flex items-center justify-between h-16 md:h-20">

                <a href="{{ route('home') }}" wire:navigate aria-label="Nextel home" class="shrink-0">
                    <img src="{{ asset('images/public/logo-dark.png') }}" alt="Nextel Connect" class="h-9 md:h-11 w-auto object-contain">
                </a>

                <div class="hidden md:flex items-center gap-9 font-sans text-[15px]">
                    <a href="{{ route('home') }}" wire:navigate class="text-text hover:text-primary transition-colors">Home</a>
                    <a href="{{ route('top-earners') }}" wire:navigate class="text-muted hover:text-primary transition-colors">Top Earners</a>
                    <a href="{{ route('agents') }}" wire:navigate class="text-muted hover:text-primary transition-colors">Distributors</a>
                    <a href="{{ route('verify-token') }}" wire:navigate class="text-muted hover:text-primary transition-colors">Verify Token</a>
                    <a href="{{ route('about') }}" wire:navigate class="text-muted hover:text-primary transition-colors">About Us</a>

                    <div x-data="{ p: false }" class="relative" @mouseenter="p = true" @mouseleave="p = false">
                        <button class="flex items-center gap-1.5 text-muted hover:text-primary transition-colors">
                            More
                            <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 10 6"><path d="M0 0l5 6 5-6z"/></svg>
                        </button>
                        <div x-show="p" x-cloak x-transition class="absolute right-0 top-full pt-3 w-52">
                            <div class="bg-surface border border-border rounded-2xl shadow-lg p-2 flex flex-col">
                                <a href="{{ route('contact') }}" wire:navigate class="px-4 py-2.5 rounded-xl text-muted hover:text-primary hover:bg-background transition-colors">Contact</a>
                                <a href="{{ route('privacy') }}" wire:navigate class="px-4 py-2.5 rounded-xl text-muted hover:text-primary hover:bg-background transition-colors">Privacy Policy</a>
                                <a href="{{ route('terms') }}" wire:navigate class="px-4 py-2.5 rounded-xl text-muted hover:text-primary hover:bg-background transition-colors">Terms</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hidden md:flex items-center gap-5">
                    <a href="{{ route('auth.login') }}" wire:navigate class="font-sans text-[15px] text-text hover:text-primary transition-colors">Sign in</a>
                    <a href="{{ route('auth.register') }}" wire:navigate class="btn-primary btn-md">Sign Up</a>
                </div>

                <button @click="open = true"
                        class="md:hidden size-10 rounded-full bg-surface/90 shadow-sm flex items-center justify-center text-text"
                        aria-label="Open menu">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16"/>
                    </svg>
                </button>
            </div>
        </div>
    </nav>

    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @keydown.escape.window="open = false"
         class="md:hidden fixed inset-0 z-[60] flex flex-col text-white px-6 pt-12 pb-8 overflow-y-auto"
         style="background: radial-gradient(120% 85% at 60% 18%, #2E7468 0%, #18443E 55%, #123A34 100%); background-color: #18443E;">

        <div class="flex items-center justify-between mb-9 shrink-0">
            <a href="{{ route('home') }}" wire:navigate @click="open=false" aria-label="Nextel Connect home">
                <img src="{{ asset('images/public/logo-coloured.png') }}" alt="Nextel Connect" class="h-8 w-auto object-contain">
            </a>
            <button @click="open=false" class="size-10 rounded-full bg-white flex items-center justify-center text-text" aria-label="Close menu">
                <i class="ri-close-line text-[22px]"></i>
            </button>
        </div>

        <nav class="flex flex-col gap-1 font-sans text-[16px]">
            <a href="{{ route('home') }}" wire:navigate @click="open=false" class="py-3 hover:text-secondary transition-colors">Home</a>
            <a href="{{ route('top-earners') }}" wire:navigate @click="open=false" class="py-3 text-white/85 hover:text-secondary transition-colors">Top Earners</a>
            <a href="{{ route('agents') }}" wire:navigate @click="open=false" class="py-3 text-white/85 hover:text-secondary transition-colors">Distributors</a>
            <a href="{{ route('verify-token') }}" wire:navigate @click="open=false" class="py-3 text-white/85 hover:text-secondary transition-colors">Verify Token</a>
            <a href="{{ route('about') }}" wire:navigate @click="open=false" class="py-3 text-white/85 hover:text-secondary transition-colors">About Us</a>

            <div x-data="{ m: false }" class="border-t border-white/10">
                <button @click="m = !m" class="w-full flex items-center justify-between py-3 text-white/85">
                    <span>More</span>
                    <i class="ri-arrow-down-s-line text-[20px] transition-transform" :class="m && 'rotate-180'"></i>
                </button>
                <div x-show="m" x-collapse class="flex flex-col pl-3 pb-2 text-white/70 text-[15px]">
                    <a href="{{ route('contact') }}" wire:navigate @click="open=false" class="py-2.5 hover:text-secondary transition-colors">Contact</a>
                    <a href="{{ route('privacy') }}" wire:navigate @click="open=false" class="py-2.5 hover:text-secondary transition-colors">Privacy Policy</a>
                    <a href="{{ route('terms') }}" wire:navigate @click="open=false" class="py-2.5 hover:text-secondary transition-colors">Terms</a>
                </div>
            </div>
        </nav>

        <div class="mt-auto pt-8 flex flex-col gap-3">
            <a href="{{ route('auth.login') }}" wire:navigate @click="open=false"
               class="h-[52px] rounded-full border border-white/40 text-white flex items-center justify-center font-sans font-semibold text-[14px] hover:bg-white/10 transition-colors">Sign in</a>
            <a href="{{ route('auth.register') }}" wire:navigate @click="open=false"
               class="h-[52px] rounded-full bg-secondary text-primary flex items-center justify-center font-sans font-semibold text-[14px] hover:opacity-95 transition">Sign Up</a>
        </div>
    </div>
</div>
