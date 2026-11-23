<div>
    <x-backoffice.page title="Reward Targets" subtitle="Daily referral-bonus targets">
        @php $tone = ['active' => 'warning', 'achieved' => 'info', 'claimed' => 'success', 'missed' => 'danger']; @endphp

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[320px]">
                <i class="ri-search-line text-muted text-[17px]"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search user…"
                       class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
            </div>
            <div class="flex items-center gap-1.5 flex-wrap">
                <button type="button" wire:click="setStatus('all')" class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $status === 'all' ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">All</button>
                @foreach ($statuses as $s)
                    <button type="button" wire:click="setStatus('{{ $s->value }}')" class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $status === $s->value ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">{{ $s->label() }}</button>
                @endforeach
            </div>
        </div>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($targets->isEmpty())
                <div class="p-10 text-center font-sans text-muted text-[14px]">No reward targets here.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[760px]">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">User</th>
                                <th class="px-5 py-3 font-medium">Date</th>
                                <th class="px-5 py-3 font-medium text-right">Target</th>
                                <th class="px-5 py-3 font-medium text-right">Reward</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($targets as $t)
                                <tr wire:key="rt-{{ $t->id }}" wire:click="openDetail('{{ $t->id }}')" class="border-b border-muted/5 last:border-0 hover:bg-background/50 cursor-pointer transition-colors">
                                    <td class="px-5 py-3">
                                        @if ($t->user)
                                            <a href="{{ route('backoffice.users.show', $t->user) }}" wire:navigate @click.stop class="block hover:opacity-80 transition"><x-backoffice.user-cell :user="$t->user" /></a>
                                        @else
                                            <span class="font-sans text-muted text-[13px]">—</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $t->date?->format('j M Y, g:i A') }}</td>
                                    <td class="px-5 py-3 text-right font-heading text-text text-[13px] whitespace-nowrap">{{ money($t->target_amount) }}</td>
                                    <td class="px-5 py-3 text-right font-sans text-success text-[13px] whitespace-nowrap">{{ money($t->reward_amount) }} <span class="text-muted text-[11px]">({{ rtrim(rtrim(number_format((float) $t->reward_percent, 2), '0'), '.') }}%)</span></td>
                                    <td class="px-5 py-3"><x-backoffice.badge :tone="$tone[$t->status->value] ?? 'muted'">{{ $t->status->label() }}</x-backoffice.badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-backoffice.card>

        @if ($targets->hasPages())<div>{{ $targets->links('vendor.pagination.nextel') }}</div>@endif

        <x-backoffice.modal :show="$showDetail" title="Reward target" closeAction="$set('showDetail', false)">
            @if ($detailTarget)
                <div class="flex flex-col gap-5">
                    <div class="flex items-center justify-between gap-3">
                        @if ($detailTarget->user)
                            <a href="{{ route('backoffice.users.show', $detailTarget->user) }}" wire:navigate class="block min-w-0 hover:opacity-80 transition"><x-backoffice.user-cell :user="$detailTarget->user" /></a>
                        @else
                            <span class="font-sans text-muted text-[13px]">—</span>
                        @endif
                        <x-backoffice.badge :tone="$tone[$detailTarget->status->value] ?? 'muted'">{{ $detailTarget->status->label() }}</x-backoffice.badge>
                    </div>

                    <div class="bg-background rounded-[16px] divide-y divide-muted/10">
                        @foreach ([
                            ['Target day', $detailTarget->date?->format('j M Y, g:i A') ?? '—'],
                            ['Target amount', money($detailTarget->target_amount)],
                            ['Reward amount', money($detailTarget->reward_amount)],
                            ['Reward %', rtrim(rtrim(number_format((float) $detailTarget->reward_percent, 2), '0'), '.').'%'],
                            ['Created', $detailTarget->created_at?->format('j M Y, g:i A') ?? '—'],
                            ['Achieved at', $detailTarget->achieved_at?->format('j M Y, g:i A') ?? '—'],
                            ['Claimed at', $detailTarget->claimed_at?->format('j M Y, g:i A') ?? '—'],
                        ] as [$label, $value])
                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                <span class="font-sans text-muted text-[13px]">{{ $label }}</span>
                                <span class="font-sans text-text text-[13px] text-right truncate">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-backoffice.modal>
    </x-backoffice.page>
</div>
