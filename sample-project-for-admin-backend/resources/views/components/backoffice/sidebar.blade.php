{{-- Role-gated System section: Settings hidden from Support, Administrators only to Super admin. --}}
@php
    $admin = auth('admin')->user();

    // Which payment manager the backoffice manages (Settings → Services → Active payment manager).
    // Only this manager's wallet pages are offered, so the menu can never drift from the keys in use.
    // Send money moves money, so it is offered to super admins only (the route and the component
    // re-check that role as well).
    $managerPages = match (strtolower((string) config('settings.payment.active_manager'))) {
        'korapay' => [
            ['label' => 'Korapay balance', 'icon' => 'ri-wallet-3-line', 'route' => 'backoffice.korapay.balance', 'match' => 'backoffice.korapay.balance'],
            ['label' => 'Korapay history', 'icon' => 'ri-history-line', 'route' => 'backoffice.korapay.history'],
            ...($admin->isSuper() ? [['label' => 'Send money', 'icon' => 'ri-send-plane-line', 'route' => 'backoffice.korapay.send-money']] : []),
            ['label' => 'Verify payout', 'icon' => 'ri-shield-check-line', 'route' => 'backoffice.korapay.verify', 'match' => 'backoffice.korapay.verify'],
        ],
        'paystack' => [
            ['label' => 'Paystack balance', 'icon' => 'ri-wallet-3-line', 'route' => 'backoffice.paystack.balance', 'match' => 'backoffice.paystack.balance'],
            ['label' => 'Paystack history', 'icon' => 'ri-history-line', 'route' => 'backoffice.paystack.history'],
            ...($admin->isSuper() ? [['label' => 'Send money', 'icon' => 'ri-send-plane-line', 'route' => 'backoffice.paystack.send-money']] : []),
            ['label' => 'Verify payout', 'icon' => 'ri-shield-check-line', 'route' => 'backoffice.paystack.verify', 'match' => 'backoffice.paystack.verify'],
        ],
        'flutterwave' => [
            ['label' => 'Flutterwave balance', 'icon' => 'ri-wallet-3-line', 'route' => 'backoffice.flutterwave.balance', 'match' => 'backoffice.flutterwave.balance'],
            ['label' => 'Flutterwave history', 'icon' => 'ri-history-line', 'route' => 'backoffice.flutterwave.history'],
            ...($admin->isSuper() ? [['label' => 'Send money', 'icon' => 'ri-send-plane-line', 'route' => 'backoffice.flutterwave.send-money']] : []),
            ['label' => 'Verify payout', 'icon' => 'ri-shield-check-line', 'route' => 'backoffice.flutterwave.verify', 'match' => 'backoffice.flutterwave.verify'],
        ],
        'bachs' => [
            ['label' => 'Bachs balance', 'icon' => 'ri-wallet-3-line', 'route' => 'backoffice.bachs.balance', 'match' => 'backoffice.bachs.balance'],
            ['label' => 'Bachs history', 'icon' => 'ri-history-line', 'route' => 'backoffice.bachs.history'],
            ...($admin->isSuper() ? [['label' => 'Send money', 'icon' => 'ri-send-plane-line', 'route' => 'backoffice.bachs.send-money']] : []),
            ['label' => 'Verify payout', 'icon' => 'ri-shield-check-line', 'route' => 'backoffice.bachs.verify', 'match' => 'backoffice.bachs.verify'],
        ],
        default => [],
    };

    $sections = [
        'Overview' => [
            ['label' => 'Dashboard', 'icon' => 'ri-dashboard-line', 'route' => 'backoffice.dashboard'],
            // `match` pinned: the derived pattern for a .index route would also cover the
            // focused reports below (reports.signins.*, reports.inbox-opens.*), lighting up two items.
            ['label' => 'Reports', 'icon' => 'ri-line-chart-line', 'route' => 'backoffice.reports.index', 'match' => 'backoffice.reports.index'],
            ['label' => 'Sign-ins today', 'icon' => 'ri-login-circle-line', 'route' => 'backoffice.reports.signins.index', 'match' => 'backoffice.reports.signins.*'],
            ['label' => 'SMS opens', 'icon' => 'ri-mail-open-line', 'route' => 'backoffice.reports.inbox-opens.index', 'match' => 'backoffice.reports.inbox-opens.*'],
        ],
        'People' => [
            ['label' => 'Users', 'icon' => 'ri-group-line', 'route' => 'backoffice.users.index'],
            ['label' => 'Mini App users', 'icon' => 'ri-telegram-line', 'route' => 'backoffice.telegram-users.index', 'match' => 'backoffice.telegram-users.*'],
            ['label' => 'Support Tickets', 'icon' => 'ri-customer-service-2-line', 'route' => 'backoffice.support-tickets.index'],
            ['label' => 'Referrals', 'icon' => 'ri-share-forward-line', 'route' => 'backoffice.referrals.index'],
        ],
        'Payouts' => [
            // Wallet pages for the payment manager the admin picked under Settings → Services,
            // followed by the shared payment-reference ledger and the operational links below.
            ...$managerPages,
            ['label' => 'Payment requests', 'icon' => 'ri-shield-keyhole-line', 'route' => 'backoffice.payment-requests.index'],
            ['label' => 'Payment references', 'icon' => 'ri-link-m', 'route' => 'backoffice.payment-references'],
            ['label' => 'Withdrawals', 'icon' => 'ri-money-dollar-circle-line', 'route' => 'backoffice.withdrawals.index', 'match' => 'backoffice.withdrawals.index'],
            ['label' => 'Payment Sheets', 'icon' => 'ri-file-list-3-line', 'route' => 'backoffice.withdrawals.payment-sheets'],
            ['label' => 'Banks', 'icon' => 'ri-building-line', 'route' => 'backoffice.banks.index'],
            ['label' => 'Countries', 'icon' => 'ri-global-line', 'route' => 'backoffice.countries.index'],
            ['label' => 'Currencies', 'icon' => 'ri-exchange-dollar-line', 'route' => 'backoffice.currencies.index'],
        ],
        'Lending' => [
            ['label' => 'Loans', 'icon' => 'ri-bank-line', 'route' => 'backoffice.loans.index'],
            ['label' => 'Pay Later', 'icon' => 'ri-calendar-schedule-line', 'route' => 'backoffice.pay-later.index'],
        ],
        'Finance' => [
            ['label' => 'Transactions', 'icon' => 'ri-exchange-dollar-line', 'route' => 'backoffice.transactions.index'],
            ['label' => 'Bill Payments', 'icon' => 'ri-shopping-bag-line', 'route' => 'backoffice.vas.orders.index'],
            ['label' => 'Reward Targets', 'icon' => 'ri-gift-line', 'route' => 'backoffice.reward-targets.index'],
        ],
        'Calls' => [
            ['label' => 'Call Logs', 'icon' => 'ri-phone-line', 'route' => 'backoffice.call-records.index'],
            ['label' => 'Call IDs', 'icon' => 'ri-cellphone-line', 'route' => 'backoffice.call-ids.index'],
            ['label' => 'Call Claims', 'icon' => 'ri-hand-coin-line', 'route' => 'backoffice.call-claims.index'],
        ],
        'Catalog' => [
            // The product pages: what they sell (Catalog Products), and the guest sales they took
            // (Product Orders — paid orders waiting to be emailed out).
            ['label' => 'Catalog Products', 'icon' => 'ri-archive-2-line', 'route' => 'backoffice.products.catalog'],
            ['label' => 'Product Orders', 'icon' => 'ri-shopping-bag-3-line', 'route' => 'backoffice.products.orders', 'match' => 'backoffice.products.orders*'],
            ['label' => 'Packages', 'icon' => 'ri-vip-crown-line', 'route' => 'backoffice.packages.index'],
            ['label' => 'Minute Plans', 'icon' => 'ri-add-circle-line', 'route' => 'backoffice.minute-plans.index'],
            ['label' => 'Call Types', 'icon' => 'ri-customer-service-2-line', 'route' => 'backoffice.call-types.index'],
        ],
        'Nextel Cloud' => [
            ['label' => 'Storage Plans', 'icon' => 'ri-cloud-line', 'route' => 'backoffice.cloud.storage-plans.index'],
            ['label' => 'Sponsor Numbers', 'icon' => 'ri-phone-line', 'route' => 'backoffice.cloud.numbers.index', 'match' => 'backoffice.cloud.numbers.*'],
            ['label' => 'Broadcasts', 'icon' => 'ri-broadcast-line', 'route' => 'backoffice.cloud.broadcasts.index'],
            ['label' => 'Distributions', 'icon' => 'ri-gift-line', 'route' => 'backoffice.cloud.distributions.index'],
        ],
        'Tokens' => [
            ['label' => 'Batches', 'icon' => 'ri-stack-line', 'route' => 'backoffice.batches.index'],
            ['label' => 'Coupons', 'icon' => 'ri-coupon-line', 'route' => 'backoffice.coupons.index'],
        ],
        'Marketing' => [
            ['label' => 'Ads', 'icon' => 'ri-advertisement-line', 'route' => 'backoffice.ads.index'],
            ['label' => 'Announcements', 'icon' => 'ri-megaphone-line', 'route' => 'backoffice.announcements.index'],
            ['label' => 'Mailing campaigns', 'icon' => 'ri-mail-send-line', 'route' => 'backoffice.mailing.index'],
            ['label' => 'Popups', 'icon' => 'ri-window-line', 'route' => 'backoffice.popups.index'],
            ['label' => 'Social follows', 'icon' => 'ri-user-follow-line', 'route' => 'backoffice.social-follows.index'],
        ],
    ];

    $system = [];
    if ($admin->canWrite()) {
        $system[] = ['label' => 'Settings', 'icon' => 'ri-settings-3-line', 'route' => 'backoffice.settings.general', 'match' => 'backoffice.settings.*'];
    }
    if ($admin->isSuper()) {
        $system[] = ['label' => 'Administrators', 'icon' => 'ri-shield-user-line', 'route' => 'backoffice.admins.index'];
    }
    if ($system !== []) {
        $sections['System'] = $system;
    }

    // Only surface nav items whose route is registered.
    $sections = collect($sections)
        ->map(fn (array $items) => array_values(array_filter($items, fn (array $i) => \Illuminate\Support\Facades\Route::has($i['route']))))
        ->filter(fn (array $items) => $items !== [])
        ->all();

    $initials = strtoupper(substr($admin->name, 0, 2));
    $bg = 'background: radial-gradient(125% 110% at 78% 78%, #2E726699 0%, #18443E 52%, #123A34 100%); background-color: #18443E;';
@endphp

<aside class="hidden lg:flex lg:flex-col lg:w-[264px] lg:shrink-0 lg:sticky lg:top-0 lg:h-screen text-white" style="{{ $bg }}">
    <div class="flex items-center h-[72px] px-6 shrink-0">
        <a href="{{ route('backoffice.dashboard') }}" wire:navigate class="flex items-center gap-2.5" aria-label="Admin home">
            <img src="{{ asset('images/public/logo-coloured.png') }}" alt="Nextel Connect" class="h-[28px] w-auto object-contain">
            <span class="font-heading font-medium text-secondary text-[13px] uppercase tracking-[0.14em]">Backoffice</span>
        </a>
    </div>

    <a href="{{ route('backoffice.account.profile') }}" wire:navigate
       class="mx-4 mb-3 flex items-center gap-3 rounded-[14px] bg-white/10 hover:bg-white/15 transition-colors px-3 py-3 shrink-0">
        <span class="size-11 rounded-full bg-white/15 overflow-hidden flex items-center justify-center shrink-0">
            @if ($admin->avatar)
                <img src="{{ $admin->avatar }}" alt="" class="size-full object-cover">
            @else
                <span class="font-heading font-semibold text-white text-[14px]">{{ $initials }}</span>
            @endif
        </span>
        <span class="flex-1 min-w-0">
            <span class="block font-heading font-medium text-white text-[14px] leading-tight truncate">{{ $admin->name }}</span>
            <span class="block font-sans text-white/55 text-[12px] truncate">{{ $admin->role->getName() }}</span>
        </span>
        <i class="ri-arrow-right-s-line text-white/55 shrink-0"></i>
    </a>

    <div class="flex-1 overflow-y-auto px-4 pb-4 [scrollbar-width:thin]">
        <x-app.nav-list :sections="$sections" />
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
           class="fixed inset-y-0 left-0 z-[70] w-[86%] max-w-[300px] flex flex-col text-white shadow-[8px_0_40px_rgba(0,0,0,0.3)]"
           style="{{ $bg }}">

        <div class="flex items-center justify-between h-[72px] px-5 shrink-0">
            <a href="{{ route('backoffice.dashboard') }}" wire:navigate @click="open = false" class="flex items-center gap-2.5" aria-label="Admin home">
                <img src="{{ asset('images/public/logo-coloured.png') }}" alt="Nextel Connect" class="h-[26px] w-auto object-contain">
                <span class="font-heading font-medium text-secondary text-[12px] uppercase tracking-[0.14em]">Backoffice</span>
            </a>
            <button type="button" @click="open = false"
                    class="size-9 rounded-full bg-white flex items-center justify-center text-text" aria-label="Close menu">
                <i class="ri-close-line text-[20px]"></i>
            </button>
        </div>

        <a href="{{ route('backoffice.account.profile') }}" wire:navigate @click="open = false"
           class="mx-4 mb-3 flex items-center gap-3 rounded-[14px] bg-white/10 px-3 py-3 shrink-0">
            <span class="size-11 rounded-full bg-white/15 overflow-hidden flex items-center justify-center shrink-0">
                @if ($admin->avatar)
                    <img src="{{ $admin->avatar }}" alt="" class="size-full object-cover">
                @else
                    <span class="font-heading font-semibold text-white text-[14px]">{{ $initials }}</span>
                @endif
            </span>
            <span class="flex-1 min-w-0">
                <span class="block font-heading font-medium text-white text-[14px] leading-tight truncate">{{ $admin->name }}</span>
                <span class="block font-sans text-white/55 text-[12px] truncate">{{ $admin->role->getName() }}</span>
            </span>
            <i class="ri-arrow-right-s-line text-white/55 shrink-0"></i>
        </a>

        <div class="flex-1 overflow-y-auto px-4 pb-4">
            <x-app.nav-list :sections="$sections" />
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
