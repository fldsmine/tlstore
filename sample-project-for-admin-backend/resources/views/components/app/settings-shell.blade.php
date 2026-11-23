{{-- Shell for the profile sub-pages. `back`: true (→ dashboard) or a URL/route string. --}}
@props(['title', 'back' => null, 'card' => true])

@php
    $links = [
        ['label' => 'Edit Profile', 'route' => 'profile.edit', 'icon' => 'ri-user-line'],
        ['label' => 'Payment Settings', 'route' => 'payment-settings', 'icon' => 'ri-bank-card-line'],
        ['label' => 'Change Password', 'route' => 'change-password', 'icon' => 'ri-lock-2-line'],
        ['label' => 'Change PIN', 'route' => 'change-pin', 'icon' => 'ri-shield-keyhole-line'],
        ['label' => 'Reset PIN', 'route' => 'reset-pin', 'icon' => 'ri-lock-password-line'],
    ];
@endphp

<div class="min-h-screen bg-background">
    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-12 flex flex-col gap-5">

        <x-app.app-bar :title="$title" :back="$back" />

        <div class="flex flex-col gap-5 lg:grid lg:grid-cols-12 lg:gap-x-10 lg:items-start">

            <aside class="hidden lg:flex lg:col-span-4 flex-col gap-1 lg:sticky lg:top-[78px]">
                <p class="font-sans text-[11px] uppercase tracking-wide text-muted px-3.5 pb-1.5">Account</p>
                @foreach ($links as $link)
                    @php $active = request()->routeIs($link['route']); @endphp
                    <a href="{{ route($link['route']) }}" wire:navigate
                       class="flex items-center gap-3 px-3.5 py-3 rounded-[14px] transition-colors {{ $active ? 'bg-surface shadow-[0_4px_9px_rgba(194,194,194,0.1)] ring-1 ring-primary/10' : 'hover:bg-surface/60' }}">
                        <i class="{{ $link['icon'] }} text-[18px] {{ $active ? 'text-primary' : 'text-muted' }}"></i>
                        <span class="font-sans text-[14px] {{ $active ? 'font-medium text-primary' : 'text-text' }}">{{ $link['label'] }}</span>
                        @if ($active)<i class="ri-arrow-right-s-line text-[18px] text-primary ml-auto"></i>@endif
                    </a>
                @endforeach
            </aside>

            {{-- Pages with their own cards pass :card="false" to skip the surface wrapper. --}}
            <div class="lg:col-span-8">
                @if ($card)
                    <div class="bg-surface rounded-[24px] p-5 lg:max-w-[640px] lg:p-8 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                        {{ $slot }}
                    </div>
                @else
                    <div class="lg:max-w-[640px]">
                        {{ $slot }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <x-app.bottom-nav />
</div>
