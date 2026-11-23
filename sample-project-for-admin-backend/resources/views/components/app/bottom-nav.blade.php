@php
    // Keep general dashboard destinations available; eSIM-only features remain route-gated.
    $activated = (bool) auth()->user()->package_id;

    // Inside the Telegram Mini App the app-bar is suppressed; its menu and
    // search actions move into this bar so the shell stays Telegram-native.
    $miniApp = (bool) session('mini_app');

    $items = $activated ? [
        ['label' => 'Home', 'icon' => 'ri-home-5-line', 'route' => 'dashboard'],
        ['label' => 'Beneficiaries', 'icon' => 'ri-group-line', 'route' => 'beneficiaries'],
        ['label' => 'Transactions', 'icon' => 'ri-exchange-dollar-line', 'route' => 'transactions'],
        ['label' => 'Profile', 'icon' => 'ri-user-3-line', 'route' => 'profile'],
    ] : [
        ['label' => 'Home', 'icon' => 'ri-home-5-line', 'route' => 'dashboard'],
        ['label' => 'Transactions', 'icon' => 'ri-exchange-dollar-line', 'route' => 'transactions'],
        ['label' => 'Profile', 'icon' => 'ri-user-3-line', 'route' => 'profile'],
        ['label' => 'Activate eSIM', 'icon' => 'ri-sim-card-2-line', 'route' => 'activate'],
    ];
@endphp

{{-- Hide only when the soft keyboard is actually up (visual viewport shrinks) so the fixed bar
     isn't shoved up-screen. Focus alone isn't enough — on desktop there's no keyboard, so the
     bar must stay put. --}}
<nav x-data="{
        keyboardUp: false,
        init() {
            const vv = window.visualViewport;
            if (! vv) { return; }
            const check = () => { this.keyboardUp = (window.innerHeight - vv.height) > 150; };
            vv.addEventListener('resize', check);
        },
     }"
     :class="keyboardUp ? 'translate-y-full' : 'translate-y-0'"
     class="lg:hidden fixed bottom-0 inset-x-0 z-40 mx-auto max-w-[440px] md:max-w-[600px] bg-surface shadow-[0_-3px_10px_rgba(101,101,101,0.1)] flex items-center {{ $miniApp ? 'justify-evenly px-2' : 'justify-between px-[24px]' }} pt-2.5 pb-5 transition-transform duration-200 [padding-bottom:max(1.25rem,env(safe-area-inset-bottom))]">
    @foreach ($items as $index => $item)
        {{-- Mini App: the app-bar's menu action, before Home. --}}
        @if ($miniApp && $index === 0)
            <button type="button" @click="$dispatch('open-app-menu')"
                    class="flex flex-col items-center gap-1.5 text-muted hover:text-primary" aria-label="Open menu">
                <i class="ri-menu-2-line text-[20px]"></i>
                <span class="font-sans text-[11px]">Menu</span>
            </button>
        @endif

        <a href="{{ route($item['route']) }}" wire:navigate
           class="flex flex-col items-center gap-1.5 transition-colors {{ request()->routeIs($item['route']) ? 'text-primary' : 'text-muted hover:text-primary' }}">
            <i class="{{ $item['icon'] }} text-[20px]"></i>
            <span class="font-sans text-[11px]">{{ $item['label'] }}</span>
        </a>

        {{-- Mini App: search is available to every onboarded dashboard user. --}}
        @if ($miniApp && $index === 0)
            <button type="button" @click="$dispatch('open-search')"
                    class="flex flex-col items-center gap-1.5 text-muted hover:text-primary" aria-label="Search">
                <i class="ri-search-line text-[20px]"></i>
                <span class="font-sans text-[11px]">Search</span>
            </button>
        @endif
    @endforeach
</nav>
