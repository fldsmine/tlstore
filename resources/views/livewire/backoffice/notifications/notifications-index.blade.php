<div>
    <x-backoffice.page title="Notification delivery" subtitle="Per-device delivery outcomes — read-only">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <x-backoffice.stat label="Queued" :value="number_format((int) ($counts['queued'] ?? 0))" icon="ri-time-line" tone="warning" />
            <x-backoffice.stat label="Sent" :value="number_format((int) ($counts['sent'] ?? 0))" icon="ri-mail-check-line" tone="success" />
            <x-backoffice.stat label="Failed" :value="number_format((int) ($counts['failed'] ?? 0))" icon="ri-mail-close-line" tone="danger" />
            <x-backoffice.stat label="Push" :value="number_format((int) ($channels['push'] ?? 0))" icon="ri-smartphone-line" tone="muted" />
            <x-backoffice.stat label="Mail" :value="number_format((int) ($channels['mail'] ?? 0))" icon="ri-mail-line" tone="muted" />
            <x-backoffice.stat label="In-app" :value="number_format((int) ($channels['database'] ?? $channels['inbox'] ?? 0))" icon="ri-inbox-line" tone="muted" />
        </div>

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[380px]">
                <i class="ri-search-line text-muted text-[17px]"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search title, type, user or provider message ID…"
                       class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
                @if ($search !== '')
                    <button type="button" wire:click="$set('search', '')" class="text-muted hover:text-text"><i class="ri-close-line text-[16px]"></i></button>
                @endif
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <select wire:model.live="status" class="input !w-[130px] !h-[40px] text-[12px]" aria-label="Status">
                    <option value="all">All statuses</option>
                    @foreach (['queued', 'sent', 'failed'] as $s)
                        <option value="{{ $s }}">{{ \Illuminate\Support\Str::headline($s) }}</option>
                    @endforeach
                </select>
                <select wire:model.live="channel" class="input !w-[130px] !h-[40px] text-[12px]" aria-label="Channel">
                    <option value="all">All channels</option>
                    @foreach ($channels->keys()->sort() as $c)
                        <option value="{{ $c }}">{{ \Illuminate\Support\Str::headline($c) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($deliveries->isEmpty())
                <div class="p-10 flex flex-col items-center gap-2.5 text-center">
                    <span class="size-12 rounded-full bg-background flex items-center justify-center text-muted"><i class="ri-notification-off-line text-[22px]"></i></span>
                    <p class="font-sans text-muted text-[14px]">No delivery records match these filters.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-muted/10">
                                <th class="th">When</th>
                                <th class="th">Type</th>
                                <th class="th">User</th>
                                <th class="th">Channel</th>
                                <th class="th">Provider</th>
                                <th class="th">Status</th>
                                <th class="th">Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($deliveries as $d)
                                <tr wire:key="dlv-{{ $d->id }}" class="tr">
                                    <td class="td text-muted whitespace-nowrap">{{ $d->created_at?->format('j M, H:i') }}</td>
                                    <td class="td">{{ $d->notification?->type ?? '—' }}</td>
                                    <td class="td">{{ $d->notification?->user?->username ? '@'.$d->notification->user->username : '—' }}</td>
                                    <td class="td text-muted">{{ \Illuminate\Support\Str::headline($d->channel) }}</td>
                                    <td class="td text-muted">{{ $d->provider ?: '—' }}</td>
                                    <td class="td"><x-backoffice.badge :tone="match ($d->status->value) { 'sent' => 'success', 'queued' => 'warning', 'failed' => 'danger', default => 'muted' }">{{ \Illuminate\Support\Str::headline($d->status->value) }}</x-backoffice.badge></td>
                                    <td class="td max-w-[280px]"><span class="font-sans text-[11px] text-muted break-words">{{ \Illuminate\Support\Str::limit($d->failure_reason ?: ($d->provider_message_id ?: '—'), 120) }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-4">
                    {{ $deliveries->links('vendor.pagination.backoffice') }}
                </div>
            @endif
        </x-backoffice.card>
        <p class="font-sans text-[12px] text-muted">Deliveries are written by the notification observers; this page never triggers sends or retries.</p>
    </x-backoffice.page>
</div>
