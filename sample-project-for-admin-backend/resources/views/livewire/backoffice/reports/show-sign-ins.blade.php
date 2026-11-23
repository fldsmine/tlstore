<div>
    <x-backoffice.page title="Sign-in detail"
                       :subtitle="$user->full_name.' · @'.$user->username"
                       :back="route('backoffice.reports.signins.index')">
        <x-slot:action>
            <div class="flex items-center gap-2">
                <a href="{{ route('backoffice.users.show', $user) }}" wire:navigate
                   class="h-[44px] px-4 rounded-[14px] bg-surface text-text font-sans text-[13px] flex items-center gap-1.5 shadow-sm hover:bg-surface/70 transition">
                    <i class="ri-user-line text-[16px]"></i> Full profile
                </a>
                <a href="{{ route('backoffice.reports.inbox-opens.show', $user) }}" wire:navigate
                   class="h-[44px] px-4 rounded-[14px] bg-primary text-white font-sans text-[13px] flex items-center gap-1.5 hover:opacity-95 transition">
                    <i class="ri-mail-open-line text-[16px]"></i> Cloud Inbox
                </a>
            </div>
        </x-slot:action>

        <x-backoffice.card>
            <div class="flex items-start gap-4 flex-wrap">
                <x-backoffice.user-cell :user="$user" :label="$user->package?->name ?? 'No package'" />
                <div class="flex items-center gap-2 flex-wrap ml-auto">
                    @if ($user->email)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-background px-3 h-[30px] font-sans text-muted text-[12px]"><i class="ri-mail-line"></i> {{ $user->email }}</span>
                    @endif
                    @if ($user->phone)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-background px-3 h-[30px] font-sans text-muted text-[12px]"><i class="ri-phone-line"></i> {{ $user->phone }}</span>
                    @endif
                    @if ($user->country)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-background px-3 h-[30px] font-sans text-muted text-[12px]"><i class="ri-map-pin-line"></i> {{ $user->country }}</span>
                    @endif
                    <x-backoffice.badge :tone="$user->status?->value === 'active' ? 'success' : 'danger'">{{ $user->status?->getName() ?? 'Unknown' }}</x-backoffice.badge>
                </div>
            </div>
        </x-backoffice.card>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
            <x-backoffice.stat label="Sign-ins" :value="$total" icon="ri-login-circle-line" />
            <x-backoffice.stat label="Today" :value="$today" icon="ri-calendar-check-line" tone="success" />
            <x-backoffice.stat label="Last 7 days" :value="$lastWeek" icon="ri-history-line" tone="warning" />
            <x-backoffice.stat label="Distinct IPs" :value="$distinctIps" icon="ri-earth-line" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">
            <x-backoffice.card title="Surface mix" class="lg:col-span-1">
                <div class="flex flex-col gap-3 pt-1">
                    @foreach ($channels as $case)
                        @php $count = $byChannel[$case->value] ?? 0; @endphp
                        <div class="flex items-center gap-3">
                            <span class="size-9 rounded-[10px] bg-primary/10 text-primary flex items-center justify-center shrink-0"><i class="{{ $case->getIcon() }}"></i></span>
                            <span class="flex-1 min-w-0 font-sans text-text text-[13px] truncate">{{ $case->getName() }}</span>
                            <span class="font-heading font-semibold text-text text-[15px]">{{ number_format($count) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 pt-4 border-t border-muted/10 flex flex-col gap-1.5">
                    <p class="font-sans text-muted text-[12px]">Last sign-in <span class="text-text font-medium">{{ $user->last_login_at?->format('j M Y, g:i A') ?? 'never' }}</span></p>
                    <p class="font-sans text-muted text-[12px]">First recorded <span class="text-text font-medium">{{ $firstEvent ? \Illuminate\Support\Carbon::parse($firstEvent)->format('j M Y, g:i A') : '—' }}</span></p>
                </div>
            </x-backoffice.card>

            <x-backoffice.card title="Open sessions" subtitle="Sign-ins still holding a session on this account" class="lg:col-span-2">
                @if (empty($sessions))
                    <div class="py-6 flex flex-col items-center gap-2 text-center">
                        <span class="size-11 rounded-full bg-background flex items-center justify-center text-muted"><i class="ri-device-line text-[20px]"></i></span>
                        <p class="font-sans text-muted text-[13px]">No sessions to show.</p>
                    </div>
                @else
                    <div class="overflow-x-auto -mx-5">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                    <th class="px-5 py-2.5 font-medium">Device</th>
                                    <th class="px-5 py-2.5 font-medium">IP</th>
                                    <th class="px-5 py-2.5 font-medium">Last seen</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($sessions as $session)
                                    <tr wire:key="session-{{ $loop->index }}" class="border-b border-muted/5 last:border-0">
                                        <td class="px-5 py-2.5">
                                            <p class="font-sans text-text text-[13px] flex items-center gap-1.5">
                                                <i class="{{ $session['mobile'] ? 'ri-smartphone-line' : 'ri-computer-line' }} text-muted"></i>
                                                {{ $session['device'] }}
                                            </p>
                                            <p class="font-sans text-muted text-[11px]">{{ $session['id'] }}…</p>
                                        </td>
                                        <td class="px-5 py-2.5 font-sans text-text text-[13px]">{{ $session['ip'] }}</td>
                                        <td class="px-5 py-2.5 font-sans text-muted text-[12px] whitespace-nowrap">{{ $session['lastActivity']?->diffForHumans() ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-backoffice.card>
        </div>

        <div class="flex items-center gap-1.5 flex-wrap">
            <button type="button" wire:click="setChannel('all')"
                    class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $channel === 'all' ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">All surfaces</button>
            @foreach ($channels as $case)
                <button type="button" wire:click="setChannel('{{ $case->value }}')"
                        class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $channel === $case->value ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">{{ $case->getName() }}</button>
            @endforeach
        </div>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($events->isEmpty())
                <div class="p-10 flex flex-col items-center gap-2.5 text-center">
                    <span class="size-12 rounded-full bg-background flex items-center justify-center text-muted"><i class="ri-login-circle-line text-[22px]"></i></span>
                    <p class="font-sans text-muted text-[14px]">No sign-ins recorded{{ $channel !== 'all' ? ' on this surface' : '' }} yet.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">When</th>
                                <th class="px-5 py-3 font-medium">Surface</th>
                                <th class="px-5 py-3 font-medium">IP</th>
                                <th class="px-5 py-3 font-medium">Device</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($events as $event)
                                <tr wire:key="event-{{ $event->id }}" class="border-b border-muted/5 last:border-0">
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        <p class="font-sans text-text text-[13px]">{{ $event->created_at?->format('j M Y, g:i A') }}</p>
                                        <p class="font-sans text-muted text-[12px]">{{ $event->created_at?->diffForHumans() }}</p>
                                    </td>
                                    <td class="px-5 py-3">
                                        <x-backoffice.badge :tone="$event->channelTone()"><i class="{{ $event->channelIcon() }}"></i> {{ $event->channelLabel() }}</x-backoffice.badge>
                                    </td>
                                    <td class="px-5 py-3 font-sans text-text text-[13px] whitespace-nowrap">{{ $event->ip_address ?: '—' }}</td>
                                    <td class="px-5 py-3 font-sans text-muted text-[12px] max-w-[320px] truncate" title="{{ $event->user_agent }}">{{ $event->device() }}</td>
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
    </x-backoffice.page>
</div>
