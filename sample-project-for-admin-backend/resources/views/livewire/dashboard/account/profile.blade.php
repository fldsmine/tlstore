<div class="min-h-screen bg-background">
    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-12 flex flex-col gap-[22px]">

        <x-app.app-bar :title="'Profile'" />

        @php $initials = strtoupper(substr($user->first_name ?: $user->username, 0, 2)); @endphp

        <div class="bg-surface rounded-[20px] p-5 flex items-center gap-4 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
            <span class="size-[68px] rounded-full bg-primary/10 overflow-hidden flex items-center justify-center shrink-0">
                @if ($user->avatar)
                    <img src="{{ $user->avatar }}" alt="" class="size-full object-cover">
                @else
                    <span class="font-heading font-semibold text-primary text-[22px]">{{ $initials }}</span>
                @endif
            </span>
            <div class="flex-1 min-w-0 flex flex-col gap-1">
                <p class="font-heading font-medium text-text text-[18px] leading-tight truncate">{{ $user->full_name }}</p>
                <p class="font-sans text-muted text-[13px] truncate">&#64;{{ $user->username }} · {{ $user->email }}</p>
                <span class="self-start mt-1 inline-flex items-center gap-1.5 bg-primary/10 text-primary font-sans text-[12px] rounded-full px-2.5 py-1">
                    <span class="size-1.5 rounded-full bg-primary"></span>{{ $user->package?->name ?? 'No active plan' }}
                </span>
            </div>
        </div>

        @if ($user->referrer)
            <div class="bg-surface rounded-[20px] p-4 flex items-center gap-3 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                <span class="size-10 rounded-full bg-primary/10 flex items-center justify-center text-primary shrink-0"><i class="ri-user-shared-line text-[20px]"></i></span>
                <div class="flex-1 min-w-0">
                    <p class="font-sans text-muted text-[12px] leading-tight">Upline</p>
                    <p class="font-heading font-medium text-text text-[15px] truncate">{{ $user->referrer->full_name ?: $user->referrer->username }}</p>
                    <p class="font-sans text-muted text-[13px] truncate">&#64;{{ $user->referrer->username }} · {{ $user->referrer->email }}</p>
                </div>
            </div>
        @endif

        @if ($canUpgrade || ! $hasPackage)
            <button type="button" x-data @click="Livewire.dispatch('open-activation')"
               class="relative overflow-hidden rounded-[20px] p-5 flex items-center gap-4 text-white text-left hover:opacity-95 transition"
               style="background: radial-gradient(120% 140% at 88% 10%, #2f7468 0%, #18443E 58%); background-color: #18443E;">
                <span class="size-12 rounded-full bg-white/12 flex items-center justify-center text-secondary shrink-0"><i class="ri-vip-crown-2-line text-[24px]"></i></span>
                <div class="flex-1 min-w-0">
                    <p class="font-heading font-medium text-white text-[16px]">{{ $hasPackage ? 'Upgrade your plan' : 'Activate a plan' }}</p>
                    <p class="font-sans text-white/70 text-[13px] leading-snug">{{ $hasPackage ? 'Move to a higher tier and earn more per call.' : 'Activate a package to start earning from calls.' }}</p>
                </div>
                <i class="ri-arrow-right-line text-white/80 text-[20px] shrink-0"></i>
            </button>
        @endif

        <div class="flex flex-col gap-[22px] lg:grid lg:grid-cols-2 lg:gap-6 lg:items-start">

            <div class="flex flex-col gap-[15px]">
                <p class="font-heading font-medium text-text text-[18px]">Account Settings</p>
                <div class="bg-surface rounded-[20px] p-2 flex flex-col divide-y divide-muted/10 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                    @foreach ([
                        ['label' => 'Edit Profile', 'icon' => 'ri-user-line', 'route' => 'profile.edit'],
                        ['label' => 'Payment Settings', 'icon' => 'ri-bank-card-line', 'route' => 'payment-settings'],
                        ['label' => 'Change Password', 'icon' => 'ri-lock-2-line', 'route' => 'change-password'],
                        ['label' => 'Change PIN', 'icon' => 'ri-shield-keyhole-line', 'route' => 'change-pin'],
                        ['label' => 'Reset PIN', 'icon' => 'ri-lock-password-line', 'route' => 'reset-pin'],
                        ['label' => 'Invite Friends', 'icon' => 'ri-user-add-line', 'route' => 'invite'],
                        ['label' => 'Help & Support', 'icon' => 'ri-customer-service-2-line', 'route' => 'support.tickets'],
                    ] as $item)
                        <a href="{{ route($item['route']) }}" wire:navigate
                           @if ($item['route'] === 'support.tickets')
                               x-data="{ nativeSupportAvailable: !!(window.Android && typeof window.Android.openSupportTickets === 'function') }"
                               @click="if (nativeSupportAvailable) { $event.preventDefault(); window.Android.openSupportTickets(); }"
                           @endif
                           class="flex items-center gap-3 px-2.5 py-3 hover:bg-background/60 rounded-[14px] transition-colors">
                            <span class="size-10 rounded-full bg-background flex items-center justify-center text-text shrink-0">
                                <i class="{{ $item['icon'] }} text-[20px]"></i>
                            </span>
                            <span class="flex-1 min-w-0 font-sans text-text text-[14px]">{{ $item['label'] }}</span>
                            <i class="ri-arrow-right-s-line text-[18px] text-muted shrink-0"></i>
                        </a>
                    @endforeach

                    @if ($telegram)
                        @php
                            $telegramLabel = $telegram->username
                                ? '@'.$telegram->username
                                : (trim($telegram->first_name.' '.(string) $telegram->last_name) ?: 'your Telegram account');
                        @endphp
                        <div class="px-2.5 py-3">
                            <div class="flex items-center gap-3">
                                <span class="size-10 rounded-full bg-[#229ED9]/10 flex items-center justify-center text-[#229ED9] shrink-0">
                                    <i class="ri-telegram-2-fill text-[20px]"></i>
                                </span>
                                <div class="flex-1 min-w-0">
                                    <p class="font-sans text-text text-[14px] truncate">Telegram sign-in</p>
                                    <p class="font-sans text-muted text-[12px] truncate">Linked as {{ $telegramLabel }} · {{ $telegram->created_at?->diffForHumans() }}</p>
                                </div>
                                @if (! $confirmingTelegramUnlink)
                                    <button type="button" wire:click="confirmTelegramUnlink" aria-label="Unlink Telegram"
                                            class="shrink-0 h-[34px] px-3.5 rounded-full bg-danger/10 text-danger font-sans text-[12px] inline-flex items-center hover:bg-danger/15 transition-colors">
                                        Unlink
                                    </button>
                                @endif
                            </div>

                            @if ($confirmingTelegramUnlink)
                                <form wire:submit="unlinkTelegram" class="mt-3 flex flex-col gap-2.5">
                                    <p class="font-sans text-muted text-[12px] leading-snug">
                                        Unlinking turns off instant sign-in from the Telegram Mini App and signs out any Telegram sessions. Your password and the rest of your account stay as they are.
                                    </p>

                                    <x-auth.input label="Confirm your password" type="password" name="telegramUnlinkPassword"
                                                  wire:model="telegramUnlinkPassword" autocomplete="current-password"
                                                  placeholder="Enter account password">
                                        <x-slot:leading>
                                            <i class="ri-lock-line text-[14px]"></i>
                                        </x-slot:leading>
                                    </x-auth.input>

                                    <div class="flex items-center gap-2">
                                        <button type="button" wire:click="cancelTelegramUnlink"
                                                class="flex-1 h-[42px] rounded-[14px] bg-background text-text font-sans text-[13px] hover:bg-background/70 transition-colors">
                                            Cancel
                                        </button>
                                        <button type="submit" wire:loading.attr="disabled"
                                                class="flex-1 h-[42px] rounded-[14px] bg-danger text-white font-sans text-[13px] flex items-center justify-center gap-2 hover:opacity-95 transition disabled:opacity-60">
                                            <span wire:loading.remove wire:target="unlinkTelegram">Unlink Telegram</span>
                                            <span wire:loading wire:target="unlinkTelegram" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Unlinking…</span>
                                        </button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    @endif

                    @if (session('mini_app'))
                        <a href="#" x-data="{ nativeSettingsAvailable: !!(window.Android && typeof window.Android.openAppSettings === 'function') }"
                           x-show="nativeSettingsAvailable" x-cloak @click.prevent="window.Android.openAppSettings()"
                           class="flex items-center gap-3 px-2.5 py-3 hover:bg-background/60 rounded-[14px] transition-colors">
                            <span class="size-10 rounded-full bg-background flex items-center justify-center text-text shrink-0">
                                <i class="ri-settings-3-line text-[20px]"></i>
                            </span>
                            <span class="flex-1 min-w-0 font-sans text-text text-[14px]">Setting</span>
                            <i class="ri-arrow-right-s-line text-[18px] text-muted shrink-0"></i>
                        </a>

                        <a href="#" x-data="{ nativeSettingsAvailable: !!(window.Android && typeof window.Android.showMenu === 'function') }"
                           x-show="nativeSettingsAvailable" x-cloak @click.prevent="window.Android.showMenu()"
                           class="flex items-center gap-3 px-2.5 py-3 hover:bg-background/60 rounded-[14px] transition-colors">
                            <i class="ri-arrow-right-s-line text-[18px] text-muted shrink-0"></i>
                            <span class="flex-1 min-w-0 font-sans text-text text-[14px]">Slide menu</span>
                            <span class="size-10 rounded-full bg-background flex items-center justify-center text-text shrink-0">
                                <i class="ri-menu-3-line text-[20px]"></i>
                            </span>
                        </a>
                    @endif
                </div>
            </div>

            <div class="flex flex-col gap-[15px]">
                <p class="font-heading font-medium text-text text-[18px]">Others</p>
                <div class="bg-surface rounded-[20px] p-2 flex flex-col divide-y divide-muted/10 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                    @foreach ([
                        ['label' => 'Privacy Policy', 'icon' => 'ri-book-open-line', 'route' => 'privacy'],
                        ['label' => 'Terms & Conditions', 'icon' => 'ri-file-list-3-line', 'route' => 'terms'],
                    ] as $item)
                        <a href="{{ route($item['route']) }}" wire:navigate class="flex items-center gap-3 px-2.5 py-3 hover:bg-background/60 rounded-[14px] transition-colors">
                            <span class="size-10 rounded-full bg-background flex items-center justify-center text-text shrink-0">
                                <i class="{{ $item['icon'] }} text-[20px]"></i>
                            </span>
                            <span class="flex-1 min-w-0 font-sans text-text text-[14px]">{{ $item['label'] }}</span>
                            <i class="ri-arrow-right-s-line text-[18px] text-muted shrink-0"></i>
                        </a>
                    @endforeach
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex items-center gap-3 px-2.5 py-3 w-full hover:bg-background/60 rounded-[14px] transition-colors">
                            <span class="size-10 rounded-full bg-danger/10 flex items-center justify-center text-danger shrink-0">
                                <i class="ri-logout-box-r-line text-[20px]"></i>
                            </span>
                            <span class="flex-1 min-w-0 text-left font-sans text-danger text-[14px]">Logout</span>
                            <i class="ri-arrow-right-s-line text-[18px] text-muted shrink-0"></i>
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <x-app.bottom-nav />
</div>
