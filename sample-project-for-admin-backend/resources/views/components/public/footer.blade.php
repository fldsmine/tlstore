@php
    // Only configured handles render (an unset link is '#' or empty).
    $socials = collect([
        ['label' => 'Instagram', 'icon' => 'ri-instagram-line', 'url' => config('settings.contact.social.instagram')],
        ['label' => 'Facebook', 'icon' => 'ri-facebook-fill', 'url' => config('settings.contact.social.facebook')],
        ['label' => 'TikTok', 'icon' => 'ri-tiktok-fill', 'url' => config('settings.contact.social.tiktok')],
        ['label' => 'Telegram', 'icon' => 'ri-telegram-2-line', 'url' => config('settings.contact.social.telegram')],
        ['label' => 'X', 'icon' => 'ri-twitter-x-line', 'url' => config('settings.contact.social.x')],
        ['label' => 'Website', 'icon' => 'ri-global-line', 'url' => config('settings.contact.social.website')],
    ])->filter(fn ($s) => filled($s['url']) && $s['url'] !== '#')->values();
@endphp

<footer>
    <div class="md:hidden relative overflow-hidden px-4 py-[30px] flex flex-col gap-[30px]">
        <img src="{{ asset('images/public/footer-bg.jpg') }}" alt="" class="absolute inset-0 w-full h-full object-cover pointer-events-none">

        <img src="{{ asset('images/public/logo-coloured.png') }}" alt="Nextel Connect" class="relative z-10 self-start h-[40px] w-auto object-contain">


        <p class="relative z-10 font-sans text-[14px] text-muted leading-relaxed">
            Track calls, monitor earnings and grow your income in one place. Every action moves you closer to increment.
        </p>

        <div class="relative z-10 flex flex-col gap-[25px]">
            <p class="font-sans text-[18px] text-surface">Quick Links</p>
            <ul class="flex flex-col gap-[15px] font-sans text-[14px] text-muted">
                <li><a href="{{ route('home') }}" wire:navigate class="hover:text-surface transition-colors">Home</a></li>
                <li><a href="{{ route('about') }}" wire:navigate class="hover:text-surface transition-colors">About Us</a></li>
                <li><a href="{{ route('contact') }}" wire:navigate class="hover:text-surface transition-colors">Contact Us</a></li>
                <li><a href="{{ route('privacy') }}" wire:navigate class="hover:text-surface transition-colors">Privacy Policy</a></li>
                <li><a href="{{ route('terms') }}" wire:navigate class="hover:text-surface transition-colors">Terms</a></li>
            </ul>
        </div>

        <div class="relative z-10 flex flex-col gap-[15px]">
            <p class="font-sans text-[18px] text-surface">Support</p>
            <p class="font-sans text-[14px] text-muted leading-relaxed">
                Need help? We typically respond within 24 hours. You are our priority.
                <a href="{{ route('contact') }}" wire:navigate class="text-surface hover:underline">Contact our support team today to get instant response.</a>
            </p>
        </div>

        <div class="relative z-10 flex flex-col gap-[15px] items-center">
            <p class="font-sans text-[14px] text-muted">Follow our social media handles</p>
            <div class="flex items-center gap-5">
                @foreach ($socials as $s)
                    <a href="{{ $s['url'] }}" target="_blank" rel="noopener" aria-label="{{ $s['label'] }}" class="text-surface hover:opacity-80 transition-opacity">
                        <i class="{{ $s['icon'] }} text-[20px]"></i>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="hidden md:block relative overflow-hidden py-[90px]">
        <img src="{{ asset('images/public/footer-bg.jpg') }}" alt="" class="absolute inset-0 w-full h-full object-cover pointer-events-none">
        <div class="relative z-10 max-w-[1280px] mx-auto px-5 md:px-[60px] flex items-start justify-between gap-16">
            <div class="flex flex-col gap-5 max-w-[420px]">
                <img src="{{ asset('images/public/logo-coloured.png') }}" alt="Nextel Connect" class="h-[48px] w-auto object-contain self-start">
                <p class="font-sans text-[18px] text-muted leading-relaxed">
                    With Nextel, you activate an eSIM, stay connected, and grow your income every day.
                </p>
                <div class="flex items-center gap-[10px] pt-[50px]">
                    @foreach ($socials as $s)
                        <a href="{{ $s['url'] }}" target="_blank" rel="noopener" aria-label="{{ $s['label'] }}" class="size-[50px] rounded-full bg-white/10 flex items-center justify-center text-surface hover:bg-white/20 transition-colors">
                            <i class="{{ $s['icon'] }} text-[20px]"></i>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="flex gap-24">
                <div class="flex flex-col gap-6">
                    <p class="font-heading font-medium text-surface text-[22px]">Company</p>
                    <ul class="flex flex-col gap-4 font-sans text-[16px] text-muted">
                        <li><a href="{{ route('about') }}" wire:navigate class="hover:text-surface transition-colors">About</a></li>
                        <li><a href="{{ route('top-earners') }}" wire:navigate class="hover:text-surface transition-colors">Top Earners</a></li>
                        <li><a href="{{ route('contact') }}" wire:navigate class="hover:text-surface transition-colors">Contact Us</a></li>
                    </ul>
                </div>
                <div class="flex flex-col gap-6">
                    <p class="font-heading font-medium text-surface text-[22px]">Legal</p>
                    <ul class="flex flex-col gap-4 font-sans text-[16px] text-muted">
                        <li><a href="{{ route('privacy') }}" wire:navigate class="hover:text-surface transition-colors">Privacy Policy</a></li>
                        <li><a href="{{ route('terms') }}" wire:navigate class="hover:text-surface transition-colors">Terms</a></li>
                        <li><a href="{{ route('verify-token') }}" wire:navigate class="hover:text-surface transition-colors">Verify Token</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</footer>
