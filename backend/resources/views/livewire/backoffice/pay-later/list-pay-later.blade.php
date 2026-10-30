<div>
    <x-backoffice.page title="Pay Later" subtitle="Minutes bought on credit">
        @php $canWrite = $this->canWrite(); $tone = ['active' => 'info', 'repaid' => 'success', 'defaulted' => 'danger']; @endphp

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[320px]">
                <i class="ri-search-line text-muted text-[17px]"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search user…"
                       class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
            </div>
            <div class="flex items-center gap-1.5 flex-wrap">
                @foreach (['all' => 'All', 'active' => 'Active', 'overdue' => 'Overdue', 'repaid' => 'Repaid', 'defaulted' => 'Defaulted'] as $key => $label)
                    <button type="button" wire:click="setStatus('{{ $key }}')" class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $status === $key ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($credits->isEmpty())
                <div class="p-10 text-center font-sans text-muted text-[14px]">No pay-later credits here.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[820px]">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">User</th>
                                <th class="px-5 py-3 font-medium">Plan</th>
                                <th class="px-5 py-3 font-medium text-right">Owed</th>
                                <th class="px-5 py-3 font-medium">Due</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($credits as $c)
                                <tr wire:key="pl-{{ $c->id }}" wire:click="openDetail('{{ $c->id }}')" class="border-b border-muted/5 last:border-0 hover:bg-background/50 cursor-pointer transition-colors">
                                    <td class="px-5 py-3">
                                        @if ($c->user)
                                            <a href="{{ route('backoffice.users.show', $c->user) }}" wire:navigate @click.stop class="block hover:opacity-80 transition"><x-backoffice.user-cell :user="$c->user" /></a>
                                        @else
                                            <span class="font-sans text-muted text-[13px]">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 font-sans text-muted text-[13px]">{{ $c->plan_name ?? $c->plan?->name ?? '—' }}</td>
                                    <td class="px-5 py-3 text-right font-heading text-text text-[13px] whitespace-nowrap">{{ money($c->amount) }}</td>
                                    <td class="px-5 py-3 font-sans text-[12px] whitespace-nowrap {{ $c->is_overdue ? 'text-danger' : 'text-muted' }}">{{ $c->due_date?->format('j M Y, g:i A') ?? '—' }}{{ $c->is_overdue ? ' · overdue' : '' }}</td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-1.5">
                                            <x-backoffice.badge :tone="$tone[$c->status->value] ?? 'muted'">{{ $c->status->label() }}</x-backoffice.badge>
                                            @if ($c->status === \App\Enums\PayLaterStatus::ACTIVE && $c->is_overdue)<x-backoffice.badge tone="warning">Overdue</x-backoffice.badge>@endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-backoffice.card>

        @if ($credits->hasPages())<div>{{ $credits->links('vendor.pagination.nextel') }}</div>@endif

        <x-backoffice.modal :show="$showDetail" title="Manage pay later" closeAction="$set('showDetail', false)">
            @if ($detailCredit)
                <div class="flex flex-col gap-5">
                    <div class="flex items-center justify-between gap-3">
                        @if ($detailCredit->user)
                            <a href="{{ route('backoffice.users.show', $detailCredit->user) }}" wire:navigate class="block min-w-0 hover:opacity-80 transition"><x-backoffice.user-cell :user="$detailCredit->user" /></a>
                        @else
                            <span class="font-sans text-muted text-[13px]">—</span>
                        @endif
                        <div class="flex items-center gap-1.5 shrink-0">
                            <x-backoffice.badge :tone="$tone[$detailCredit->status->value] ?? 'muted'">{{ $detailCredit->status->label() }}</x-backoffice.badge>
                            @if ($detailCredit->status === \App\Enums\PayLaterStatus::ACTIVE && $detailCredit->is_overdue)<x-backoffice.badge tone="warning">Overdue</x-backoffice.badge>@endif
                        </div>
                    </div>

                    <div class="bg-background rounded-[16px] divide-y divide-muted/10">
                        @foreach ([
                            ['Plan', $detailCredit->plan_name ?? $detailCredit->plan?->name ?? '—'],
                            ['Amount owed', money($detailCredit->amount)],
                            ['Minutes', (string) $detailCredit->minutes],
                            ['Created', $detailCredit->created_at?->format('j M Y, g:i A') ?? '—'],
                            ['Due date', $detailCredit->due_date?->format('j M Y, g:i A') ?? '—'],
                        ] as [$label, $value])
                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                <span class="font-sans text-muted text-[13px]">{{ $label }}</span>
                                <span class="font-sans text-text text-[13px] text-right truncate">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>

                    @if ($canWrite && $detailCredit->status === \App\Enums\PayLaterStatus::ACTIVE)
                        <div class="flex flex-col gap-2">
                            <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'markRepaid', params: ['{{ $detailCredit->id }}'], title: 'Mark as paid', message: 'This Pay Later will be marked as repaid.', confirmText: 'Mark as paid', tone: 'primary' })"
                                    class="flex items-center gap-3 rounded-[14px] bg-background px-4 py-3 hover:bg-background/70 transition text-left">
                                <i class="ri-check-line text-primary text-[18px]"></i>
                                <span class="flex-1 font-sans text-text text-[13px]">Mark as paid</span>
                                <i class="ri-arrow-right-s-line text-muted"></i>
                            </button>
                            <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'extendDue', params: ['{{ $detailCredit->id }}'], title: 'Extend due date', message: 'The due date, granted minutes, and Call IDs will be extended by 14 days.', confirmText: 'Extend', tone: 'primary' })"
                                    class="flex items-center gap-3 rounded-[14px] bg-background px-4 py-3 hover:bg-background/70 transition text-left">
                                <i class="ri-calendar-line text-primary text-[18px]"></i>
                                <span class="flex-1 font-sans text-text text-[13px]">Extend due date +14 days</span>
                                <i class="ri-arrow-right-s-line text-muted"></i>
                            </button>
                            <button type="button" x-on:click="$dispatch('confirm-action', { id: $wire.id, method: 'markDefaulted', params: ['{{ $detailCredit->id }}'], title: 'Mark as defaulted', message: 'Minutes and earnings will be forfeited.', confirmText: 'Mark as defaulted', tone: 'danger' })"
                                    class="flex items-center gap-3 rounded-[14px] bg-danger/10 px-4 py-3 hover:bg-danger/15 transition text-left">
                                <i class="ri-close-circle-line text-danger text-[18px]"></i>
                                <span class="flex-1 font-sans text-danger text-[13px]">Mark as defaulted</span>
                                <i class="ri-arrow-right-s-line text-danger/60"></i>
                            </button>
                        </div>
                    @endif
                </div>
            @endif
        </x-backoffice.modal>
    </x-backoffice.page>
</div>
