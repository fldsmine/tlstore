{{-- Role-aware backoffice sidebar. Full navigation, grouped by section; only routes
     that exist are linked. Settings/Administrators are gated to super admins, and
     logout posts to the backoffice logout route (web middleware → CSRF). --}}
@php
    $admin = auth('admin')->user();

    $sections = [
        'Overview' => [
            ['label' => 'Dashboard', 'icon' => 'ri-dashboard-line', 'route' => 'backoffice.dashboard', 'match' => 'backoffice.dashboard'],
            ['label' => 'Reports & exports', 'icon' => 'ri-file-chart-line', 'route' => 'backoffice.reports.index', 'match' => 'backoffice.reports.*'],
        ],
        'People' => [
            ['label' => 'Users', 'icon' => 'ri-group-line', 'route' => 'backoffice.users.index', 'match' => ['backoffice.users.index', 'backoffice.users.show']],
        ],
        'Moderation' => [
            ['label' => 'Review queue', 'icon' => 'ri-star-smile-line', 'route' => 'backoffice.moderation.reviews', 'match' => 'backoffice.moderation.reviews'],
            ['label' => 'Share queue', 'icon' => 'ri-share-line', 'route' => 'backoffice.moderation.shares', 'match' => 'backoffice.moderation.shares'],
            ['label' => 'Manual deposits', 'icon' => 'ri-bank-line', 'route' => 'backoffice.moderation.deposits', 'match' => 'backoffice.moderation.deposits'],
            ['label' => 'Withdrawals', 'icon' => 'ri-money-dollar-circle-line', 'route' => 'backoffice.moderation.withdrawals', 'match' => 'backoffice.moderation.withdrawals'],
        ],
        'Finance' => [
            ['label' => 'Transactions', 'icon' => 'ri-exchange-dollar-line', 'route' => 'backoffice.transactions.index', 'match' => 'backoffice.transactions.index'],
            ['label' => 'Payment providers', 'icon' => 'ri-secure-payment-line', 'route' => 'backoffice.payments.providers', 'match' => 'backoffice.payments.providers'],
            ['label' => 'Notifications', 'icon' => 'ri-notification-3-line', 'route' => 'backoffice.notifications.index', 'match' => 'backoffice.notifications.index'],
        ],
        'System' => [
            ['label' => 'Audit log', 'icon' => 'ri-file-list-3-line', 'route' => 'backoffice.audit-log.index', 'match' => 'backoffice.audit-log.index'],
            ['label' => 'Settings', 'icon' => 'ri-settings-3-line', 'route' => 'backoffice.settings.index', 'match' => 'backoffice.settings.index'],
        ],
    ];

    if ($admin->isSuperAdmin()) {
        $sections['System'][] = ['label' => 'Administrators', 'icon' => 'ri-shield-user-line', 'route' => 'backoffice.admins.index', 'match' => 'backoffice.admins.*'];
    }

    // Never link a route that is not registered.
    $sections = collect($sections)
        ->map(fn (array $items) => array_values(array_filter($items, fn (array $i) => \Illuminate\Support\Facades\Route::has($i['route']))))
        ->filter(fn (array $items) => $items !== [])
        ->all();

    $initials = $admin->initials();
    $bg = 'background: radial-gradient(125% 110% at 78% 78%, #2E726699 0%, #18443E 52%, #123A34 100%); background-color: #18443E;';
@endphp

<aside class="hidden lg:flex lg:flex-col lg:w-[264px] lg:shrink-0 lg:sticky lg:top-0 lg:h-screen text-white" style="{{ $bg }}">
    <div class="flex items-center h-[72px] px-6 shrink-0">
        <a href="{{ route('backoffice.dashboard') }}" wire:navigate class="flex items-center gap-2.5" aria-label="Admin home">
            <span class="inline-flex items-center bg-surface rounded-xl px-2.5 py-1.5">
                <img src="{{ asset('images/logo-long.png') }}" alt="TrustLens Store" class="h-[22px] w-auto object-contain">
            </span>
            <span class="font-heading font-medium text-secondary text-[12px] uppercase tracking-[0.14em]">Backoffice</span>
        </a>
    </div>

    <a href="{{ route('backoffice.account.profile') }}" wire:navigate
       class="mx-4 mb-3 flex items-center gap-3 rounded-[14px] bg-white/10 hover:bg-white/15 transition-colors px-3 py-3 shrink-0">
        <span class="size-11 rounded-full bg-white/15 overflow-hidden flex items-center justify-center shrink-0">
            <span class="font-heading font-semibold text-white text-[14px]">{{ $initials }}</span>
        </span>
        <span class="flex-1 min-w-0">
            <span class="block font-heading font-medium text-white text-[14px] leading-tight truncate">{{ $admin->full_name }}</span>
            <span class="block font-sans text-white/55 text-[12px] truncate">{{ $admin->roleLabel() }}</span>
        </span>
        <i class="ri-arrow-right-s-line text-white/55 shrink-0"></i>
    </a>

    <div class="flex-1 overflow-y-auto px-4 pb-4 [scrollbar-width:thin]">
        <x-backoffice.nav-list :sections="$sections" />
    </div>

    <div class="px-4 py-4 border-t border-white/10 shrink-0">
        <form method="POST" action="{{ route('backoffice.logout') }}">
            @csrf
            <button type="submit" class="w-full flex items-center gap-3 rounded-[12px] px-3 py-2.5 text-white/80 hover:text-white hover:bg-white/10 transition-colors">
                <i class="ri-logout-box-r-line text-[19px]"></i>
                <span class="font-sans text-[14px]">Logout</span>
            </button>
        </form>
    </div>
</aside>

{{-- Mobile drawer --}}
<div x-data="{ open: false }"
     @open-app-menu.window="open = true"
     @close-app-menu.window="open = false"
     @keydown.escape.window="open = false"
     :data-lock-scroll="open ? '' : null"
     x-cloak class="lg:hidden">

    <div x-show="open" x-transition.opacity.duration.250ms
         @click="open = false"
         class="fixed inset-0 z-[60] bg-black/50 backdrop-blur-sm"></div>

    <aside x-show="open"
           x-transition:enter="transition ease-out duration-300"
           x-transition:enter-start="-translate-x-full"
           x-transition:enter-end="translate-x-0"
           x-transition:leave="transition ease-in duration-200"
           x-transition:leave-start="translate-x-0"
           x-transition:leave-end="-translate-x-full"
           class="fixed inset-y-0 left-0 z-[70] w-[86%] max-w-[300px] flex flex-col text-white shadow-[8px_0_40px_rgba(0,0,0,0.3)] overflow-y-auto"
           style="{{ $bg }}">

        <div class="flex items-center justify-between h-[72px] px-5 shrink-0">
            <a href="{{ route('backoffice.dashboard') }}" wire:navigate @click="open = false" class="flex items-center gap-2.5" aria-label="Admin home">
                <span class="inline-flex items-center bg-surface rounded-lg px-2 py-1">
                    <img src="{{ asset('images/logo-long.png') }}" alt="TrustLens Store" class="h-[20px] w-auto object-contain">
                </span>
                <span class="font-heading font-medium text-secondary text-[11px] uppercase tracking-[0.14em]">Backoffice</span>
            </a>
            <button type="button" @click="open = false"
                    class="size-9 rounded-full bg-white flex items-center justify-center text-text" aria-label="Close menu">
                <i class="ri-close-line text-[20px]"></i>
            </button>
        </div>

        <a href="{{ route('backoffice.account.profile') }}" wire:navigate @click="open = false"
           class="mx-4 mb-3 flex items-center gap-3 rounded-[14px] bg-white/10 px-3 py-3 shrink-0">
            <span class="size-11 rounded-full bg-white/15 overflow-hidden flex items-center justify-center shrink-0">
                <span class="font-heading font-semibold text-white text-[14px]">{{ $initials }}</span>
            </span>
            <span class="flex-1 min-w-0">
                <span class="block font-heading font-medium text-white text-[14px] leading-tight truncate">{{ $admin->full_name }}</span>
                <span class="block font-sans text-white/55 text-[12px] truncate">{{ $admin->roleLabel() }}</span>
            </span>
            <i class="ri-arrow-right-s-line text-white/55 shrink-0"></i>
        </a>

        <div class="flex-1 overflow-y-auto px-4 pb-4">
            <x-backoffice.nav-list :sections="$sections" />
        </div>

        <div class="px-4 py-4 border-t border-white/10 shrink-0">
            <form method="POST" action="{{ route('backoffice.logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 rounded-[12px] px-3 py-2.5 text-white/80 hover:text-white hover:bg-white/10 transition-colors">
                    <i class="ri-logout-box-r-line text-[19px]"></i>
                    <span class="font-sans text-[14px]">Logout</span>
                </button>
            </form>
        </div>
    </aside>
</div>
