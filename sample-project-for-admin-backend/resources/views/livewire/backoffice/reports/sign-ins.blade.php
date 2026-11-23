<div>
    <x-backoffice.page title="Sign-ins" subtitle="Everyone who signed in, with the surface they used">
        <x-slot:action>
            <div class="flex items-center gap-2">
                <button type="button" wire:click="shiftDay(-1)" aria-label="Previous day"
                        class="size-[44px] rounded-[14px] bg-surface text-text shadow-sm flex items-center justify-center hover:bg-surface/70 transition">
                    <i class="ri-arrow-left-s-line text-[20px]"></i>
                </button>

                {{-- Pick any day directly; the arrows walk one day at a time. --}}
                <label class="h-[44px] px-3 rounded-[14px] bg-surface shadow-sm flex items-center gap-2">
                    <i class="ri-calendar-line text-muted text-[16px]"></i>
                    <span class="sr-only">Report day</span>
                    <input type="date" value="{{ $day->toDateString() }}" max="{{ today()->toDateString() }}"
                           wire:change="selectDay($event.target.value)"
                           class="bg-transparent border-0 p-0 font-sans font-medium text-text text-[13px] focus:outline-none focus:ring-0">
                </label>

                <button type="button" wire:click="shiftDay(1)" @disabled($isToday) aria-label="Next day"
                        class="size-[44px] rounded-[14px] bg-surface text-text shadow-sm flex items-center justify-center hover:bg-surface/70 transition disabled:opacity-35 disabled:cursor-not-allowed">
                    <i class="ri-arrow-right-s-line text-[20px]"></i>
                </button>
            </div>
        </x-slot:action>

        @php
            $input = 'h-[46px] rounded-[12px] bg-background border border-muted/15 px-3.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40';
        @endphp

        @unless ($isToday)
            <div class="flex items-center gap-3 rounded-[16px] bg-surface px-4 py-3">
                <i class="ri-history-line text-primary text-[18px]"></i>
                <p class="flex-1 min-w-0 font-sans text-muted text-[13px]">You are viewing <span class="text-text font-medium">{{ $day->format('j M Y') }}</span>, not today.</p>
                <button type="button" wire:click="today" class="shrink-0 font-sans font-medium text-primary text-[13px] hover:underline">Back to today</button>
            </div>
        @endunless

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
            @php $surfaceName = $channel === 'all' ? null : \App\Enums\LoginChannel::from($channel)->getName(); @endphp
            <x-backoffice.stat label="Sign-ins" :value="$total" icon="ri-login-circle-line" hint="{{ $surfaceName ?? ($isToday ? 'today' : $day->format('j M')) }}" />
            <x-backoffice.stat label="Unique users" :value="$uniqueUsers" icon="ri-user-line" tone="success" />
            {{-- Clock times, not figures: x-figure casts its value to a number (9:14 AM would render 0). --}}
            <div class="bg-surface rounded-[20px] p-5 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)] flex flex-col gap-3">
                <span class="size-10 rounded-[12px] bg-[#d79937]/15 text-[#d79937] flex items-center justify-center"><i class="ri-sun-line text-[20px]"></i></span>
                <div class="flex flex-col gap-0.5">
                    <p class="font-heading font-semibold text-text text-[24px] leading-none">{{ $firstSignIn?->format('g:i A') ?? '—' }}</p>
                    <p class="font-sans text-muted text-[13px]">First sign-in{{ $firstSignIn ? ' · '.$firstSignIn->diffForHumans() : '' }}</p>
                </div>
            </div>
            <div class="bg-surface rounded-[20px] p-5 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)] flex flex-col gap-3">
                <span class="size-10 rounded-[12px] bg-primary/10 text-primary flex items-center justify-center"><i class="ri-moon-line text-[20px]"></i></span>
                <div class="flex flex-col gap-0.5">
                    <p class="font-heading font-semibold text-text text-[24px] leading-none">{{ $lastSignIn?->format('g:i A') ?? '—' }}</p>
                    <p class="font-sans text-muted text-[13px]">Latest sign-in{{ $lastSignIn ? ' · '.$lastSignIn->diffForHumans() : '' }}</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
            <x-backoffice.card title="Sign-ins · last 7 days">
                <x-backoffice.line-chart :total="abbreviate_number(array_sum(array_column($points, 'value')))" :points="$points" color="#18443E" :height="130" />
            </x-backoffice.card>

            <x-backoffice.card title="By surface">
                <div class="flex flex-col gap-3 pt-1">
                    @foreach ($channels as $case)
                        @php $count = $byChannel[$case->value] ?? 0; @endphp
                        <button type="button" wire:click="setChannel('{{ $case->value }}')"
                                class="flex items-center gap-3 text-left">
                            <span class="size-9 rounded-[10px] bg-primary/10 text-primary flex items-center justify-center shrink-0"><i class="{{ $case->getIcon() }}"></i></span>
                            <span class="flex-1 min-w-0 font-sans text-text text-[13px] truncate">{{ $case->getName() }}</span>
                            <span class="font-heading font-semibold text-text text-[15px]">{{ number_format($count) }}</span>
                            <span class="font-sans text-muted text-[12px] w-12 text-right">{{ $dayTotal > 0 ? round($count / $dayTotal * 100) : 0 }}%</span>
                        </button>
                        <div class="h-1.5 rounded-full bg-background overflow-hidden -mt-1.5">
                            <div class="h-full rounded-full bg-primary" style="width: {{ $dayTotal > 0 ? max(round($count / $dayTotal * 100), $count > 0 ? 2 : 0) : 0 }}%"></div>
                        </div>
                    @endforeach
                </div>
            </x-backoffice.card>
        </div>

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[380px]">
                <i class="ri-search-line text-muted text-[17px]"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search name, username, email or phone…" class="{{ $input }} !h-auto !bg-transparent !border-0 !px-0 flex-1 min-w-0">
                @if ($search !== '')
                    <button type="button" wire:click="$set('search', '')" class="text-muted hover:text-text"><i class="ri-close-line text-[16px]"></i></button>
                @endif
            </div>

            <div class="flex items-center gap-1.5 flex-wrap">
                <button type="button" wire:click="setChannel('all')"
                        class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $channel === 'all' ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">All surfaces</button>
                @foreach ($channels as $case)
                    <button type="button" wire:click="setChannel('{{ $case->value }}')"
                            class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $channel === $case->value ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">{{ $case->getName() }}</button>
                @endforeach
            </div>
        </div>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($events->isEmpty())
                <div class="p-10 flex flex-col items-center gap-2.5 text-center">
                    <span class="size-12 rounded-full bg-background flex items-center justify-center text-muted"><i class="ri-login-circle-line text-[22px]"></i></span>
                    <p class="font-sans text-muted text-[14px]">No sign-ins recorded {{ $isToday ? 'today' : 'on '.$day->format('j M Y') }}{{ $channel !== 'all' || trim($search) !== '' ? ' for these filters' : '' }}.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">User</th>
                                <th class="px-5 py-3 font-medium">Surface</th>
                                <th class="px-5 py-3 font-medium">Signed in</th>
                                <th class="px-5 py-3 font-medium">IP</th>
                                <th class="px-5 py-3 font-medium">Device</th>
                                <th class="px-5 py-3 font-medium text-right"> </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($events as $event)
                                <tr wire:key="login-{{ $event->id }}" class="border-b border-muted/5 last:border-0 hover:bg-background/60 transition-colors">
                                    <td class="px-5 py-3">
                                        @if ($event->user)
                                            <x-backoffice.user-cell :user="$event->user" :label="$event->user->package?->name ?? 'No package'" />
                                        @else
                                            <p class="font-sans text-muted text-[13px]">Deleted account</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        <x-backoffice.badge :tone="$event->channelTone()">
                                            <i class="{{ $event->channelIcon() }}"></i> {{ $event->channelLabel() }}
                                        </x-backoffice.badge>
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        <p class="font-sans text-text text-[13px]">{{ $event->created_at?->format('g:i A') }}</p>
                                        <p class="font-sans text-muted text-[12px]">{{ $event->created_at?->diffForHumans() }}</p>
                                    </td>
                                    <td class="px-5 py-3 font-sans text-text text-[13px] whitespace-nowrap">{{ $event->ip_address ?: '—' }}</td>
                                    <td class="px-5 py-3 font-sans text-muted text-[12px] max-w-[220px] truncate" title="{{ $event->user_agent }}">{{ $event->device() }}</td>
                                    <td class="px-5 py-3 text-right">
                                        @if ($event->user)
                                            <a href="{{ route('backoffice.reports.signins.show', $event->user) }}" wire:navigate
                                               class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-3 h-[34px] font-sans font-medium text-primary text-[12px] hover:bg-primary/15">
                                                Details <i class="ri-arrow-right-line"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-backoffice.card>

        @if ($events->hasPages())
            <div>{{ $events->links('vendor.pagination.nextel') }}</div>
        @endif

        @if ($untracked->isNotEmpty())
            {{-- Users whose most recent sign-in is this day but who have no event row: they signed
                 in before the sign-in log existed (the log starts recording from its deploy). --}}
            <x-backoffice.card title="Latest sign-in earlier than the log">
                <p class="font-sans text-muted text-[13px] -mt-2 mb-4">These accounts last signed in on this day, before per-sign-in history was recorded.</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">User</th>
                                <th class="px-5 py-3 font-medium">Last sign-in</th>
                                <th class="px-5 py-3 font-medium text-right"> </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($untracked as $user)
                                <tr wire:key="untracked-{{ $user->id }}" class="border-b border-muted/5 last:border-0">
                                    <td class="px-5 py-3"><x-backoffice.user-cell :user="$user" :label="$user->package?->name ?? 'No package'" /></td>
                                    <td class="px-5 py-3 font-sans text-text text-[13px] whitespace-nowrap">{{ $user->last_login_at?->format('g:i A') }}</td>
                                    <td class="px-5 py-3 text-right">
                                        <a href="{{ route('backoffice.reports.signins.show', $user) }}" wire:navigate
                                           class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-3 h-[34px] font-sans font-medium text-primary text-[12px] hover:bg-primary/15">
                                            Details <i class="ri-arrow-right-line"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-backoffice.card>
        @endif
    </x-backoffice.page>
</div>
