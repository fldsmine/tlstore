{{-- One definition, two presentations: persistent lg+ rail and mobile drawer toggled by `open-app-menu`/`close-app-menu` window events. --}}
@auth
    @php
        $user = auth()->user();
        $user->loadMissing('package');

        // Unread inbox + claimable bonuses → live badge on the "Nextel Cloud" entry.
        $cloudBadge = $user->cloud_line_number ? cloud_badge_count($user) : null;

        // Unread inbound (customer) messages → badge on the agent's "Live Chats" entry.
        $chatBadge = ($user->is_agent && config('settings.chat.enabled'))
            ? \App\Models\Chat::query()->where('agent_id', $user->id)
                ->whereHas('messages', fn ($q) => $q->where('sender', 'customer')
                    ->where(fn ($w) => $w->whereNull('chats.agent_read_at')
                        ->orWhereColumn('chat_messages.created_at', '>', 'chats.agent_read_at')))
                ->count()
            : null;

        $sections = [
            'Main' => [
                ['label' => 'Dashboard', 'icon' => 'ri-home-5-line', 'route' => 'dashboard'],
                ['label' => 'My Website', 'icon' => 'ri-global-line', 'route' => 'my-website'],
                ['label' => 'Beneficiaries', 'icon' => 'ri-phone-line', 'route' => 'beneficiaries'],
                ['label' => 'Transactions', 'icon' => 'ri-exchange-dollar-line', 'route' => 'transactions'],
            ],
            'Earnings' => array_values(array_filter([
                $user->package_id ? ['label' => 'Earnings', 'icon' => 'ri-money-dollar-circle-line', 'route' => 'earnings'] : null,
                ['label' => 'Shareholder Portal', 'icon' => 'ri-pie-chart-2-line', 'route' => 'shareholder'],
                // Cloud is entered through My Website (which hosts the Cloud card). `match` is a
                ['label' => 'Nextel Cloud', 'icon' => 'ri-cloud-line', 'route' => 'cloud', 'match' => 'cloud'] + ($cloudBadge !== null ? ['count' => $cloudBadge] : []),
                // Ads system being phased out — hidden for now (revert by uncommenting):
                // ['label' => 'Ads', 'icon' => 'ri-advertisement-line', 'route' => 'ads'],
                ['label' => 'Rewards', 'icon' => 'ri-gift-line', 'route' => 'rewards'],
            ])),
            'Services' => [
                ['label' => 'Shares', 'icon' => 'ri-add-circle-line', 'route' => 'plans'],
                ['label' => 'Pay Later', 'icon' => 'ri-hand-coin-line', 'route' => 'paylater'],
                ['label' => 'Loan', 'icon' => 'ri-bank-line', 'route' => 'loan'],
                ['label' => 'Debit Card', 'icon' => 'ri-bank-card-2-line', 'route' => 'debit-card'],
                ['label' => 'VAS', 'icon' => 'ri-apps-2-line', 'route' => 'vas'],
            ],
            'Account' => [
                ['label' => 'My Team', 'icon' => 'ri-team-line', 'route' => 'invite'],
                ['label' => 'Social follows', 'icon' => 'ri-user-follow-line', 'route' => 'social-follows'],
                ['label' => 'Profile & Settings', 'icon' => 'ri-user-3-line', 'route' => 'profile'],
            ],
        ];

        if ($user->is_agent) {
            $sections['Vendor'] = array_values(array_filter([
                ['label' => 'Vendor Dashboard', 'icon' => 'ri-store-2-line', 'route' => 'agent.dashboard'],
                config('settings.chat.enabled')
                    ? ['label' => 'Live Chats', 'icon' => 'ri-chat-smile-2-line', 'route' => 'agent.chats', 'badge_event' => 'live-chats-badge', 'count' => (int) $chatBadge]
                    : null,
            ]));
        }

        $name = $user->first_name ? trim($user->first_name.' '.$user->last_name) : $user->username;
        $initials = strtoupper(substr($user->first_name ?: $user->username, 0, 2));
        $bg = 'background: radial-gradient(125% 110% at 78% 78%, #2E726699 0%, #18443E 52%, #123A34 100%); background-color: #18443E;';
    @endphp

    {{-- Desktop persistent rail --}}
    <aside class="hidden lg:flex lg:flex-col lg:w-[264px] lg:shrink-0 lg:sticky lg:top-0 lg:h-screen text-white" style="{{ $bg }}">
        <div class="flex items-center h-[72px] px-6 shrink-0">
            <a href="{{ route('home') }}" wire:navigate aria-label="Nextel Connect home">
                <img src="{{ asset('images/public/logo-coloured.png') }}" alt="Nextel Connect" class="h-[30px] w-auto object-contain">
            </a>
        </div>

        <a href="{{ route('profile') }}" wire:navigate
           class="mx-4 mb-3 flex items-center gap-3 rounded-[14px] bg-white/10 hover:bg-white/15 transition-colors px-3 py-3 shrink-0">
            <span class="size-11 rounded-full bg-white/15 overflow-hidden flex items-center justify-center shrink-0">
                @if ($user->avatar)
                    <img src="{{ $user->avatar }}" alt="" class="size-full object-cover">
                @else
                    <span class="font-heading font-semibold text-white text-[14px]">{{ $initials }}</span>
                @endif
            </span>
            <span class="flex-1 min-w-0">
                <span class="block font-heading font-medium text-white text-[14px] leading-tight truncate">{{ $name }}</span>
                <span class="block font-sans text-white/55 text-[12px] truncate">{{ $user->package?->name ?? 'Share plan not active' }}</span>
            </span>
            <i class="ri-arrow-right-s-line text-white/55 shrink-0"></i>
        </a>

        <div class="flex-1 overflow-y-auto px-4 pb-4 [scrollbar-width:thin]">
            <x-app.nav-list :sections="$sections" />
        </div>

        <div class="px-4 py-4 border-t border-white/10 shrink-0">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 rounded-[12px] px-3 py-2.5 text-white/80 hover:text-white hover:bg-white/10 transition-colors">
                    <i class="ri-logout-box-r-line text-[19px]"></i>
                    <span class="font-sans text-[14px]">Logout</span>
                </button>
            </form>
        </div>
    </aside>

    {{-- Mobile off-canvas drawer --}}
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
                <a href="{{ route('home') }}" wire:navigate @click="open = false" aria-label="Nextel Connect home">
                    <img src="{{ asset('images/public/logo-coloured.png') }}" alt="Nextel Connect" class="h-[28px] w-auto object-contain">
                </a>
                <button type="button" @click="open = false"
                        class="size-9 rounded-full bg-white flex items-center justify-center text-text" aria-label="Close menu">
                    <i class="ri-close-line text-[20px]"></i>
                </button>
            </div>

            <a href="{{ route('profile') }}" wire:navigate @click="open = false"
               class="mx-4 mb-3 flex items-center gap-3 rounded-[14px] bg-white/10 px-3 py-3 shrink-0">
                <span class="size-11 rounded-full bg-white/15 overflow-hidden flex items-center justify-center shrink-0">
                    @if ($user->avatar)
                        <img src="{{ $user->avatar }}" alt="" class="size-full object-cover">
                    @else
                        <span class="font-heading font-semibold text-white text-[14px]">{{ $initials }}</span>
                    @endif
                </span>
                <span class="flex-1 min-w-0">
                    <span class="block font-heading font-medium text-white text-[14px] leading-tight truncate">{{ $name }}</span>
                    <span class="block font-sans text-white/55 text-[12px] truncate">{{ $user->package?->name ?? 'Share plan not active' }}</span>
                </span>
                <i class="ri-arrow-right-s-line text-white/55 shrink-0"></i>
            </a>

            <div class="flex-1 overflow-y-auto px-4 pb-4">
                <x-app.nav-list :sections="$sections" />
            </div>

            <div class="px-4 py-4 border-t border-white/10 shrink-0">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full flex items-center gap-3 rounded-[12px] px-3 py-2.5 text-white/80 hover:text-white hover:bg-white/10 transition-colors">
                        <i class="ri-logout-box-r-line text-[19px]"></i>
                        <span class="font-sans text-[14px]">Logout</span>
                    </button>
                </form>
            </div>
        </aside>
    </div>
@endauth
