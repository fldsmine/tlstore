<div>
    <x-backoffice.page title="Referrals" subtitle="Who referred whom">
        @php $tone = ['pending' => 'warning', 'active' => 'success']; @endphp

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[320px]">
                <i class="ri-search-line text-muted text-[17px]"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Reference or username…"
                       class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
            </div>
            <div class="flex items-center gap-1.5 flex-wrap">
                @foreach (['direct' => ['Direct', $directCount], 'indirect' => ['Indirect', $indirectCount]] as $lvl => [$lvlLabel, $lvlCount])
                    <button type="button" wire:click="setLevel('{{ $lvl }}')"
                            class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $level === $lvl ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">
                        {{ $lvlLabel }} <span class="opacity-70">({{ number_format($lvlCount) }})</span>
                    </button>
                @endforeach
                <span class="w-px h-5 bg-muted/15 mx-1"></span>
                <button type="button" wire:click="setStatus('all')" class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $status === 'all' ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">All</button>
                @foreach ($statuses as $s)
                    <button type="button" wire:click="setStatus('{{ $s->value }}')" class="px-3 h-[34px] rounded-full font-sans text-[12px] transition {{ $status === $s->value ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-text' }}">{{ $s->getName() }}</button>
                @endforeach
            </div>
        </div>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($referrals->isEmpty())
                <div class="p-10 text-center font-sans text-muted text-[14px]">No {{ $level }} referrals{{ ($status !== 'all' || trim($search) !== '') ? ' match these filters' : '' }}.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[820px]">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                @if ($level === 'indirect')
                                    <th class="px-5 py-3 font-medium">Upline</th>
                                    <th class="px-5 py-3 font-medium">Referee</th>
                                    <th class="px-5 py-3 font-medium">Via</th>
                                @else
                                    <th class="px-5 py-3 font-medium">Referrer</th>
                                    <th class="px-5 py-3 font-medium">Referee</th>
                                @endif
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="px-5 py-3 font-medium">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($referrals as $r)
                                <tr wire:key="ref-{{ $r->id }}" wire:click="show('{{ $r->id }}')" class="border-b border-muted/5 last:border-0 hover:bg-background/60 cursor-pointer transition-colors">
                                    @if ($level === 'indirect')
                                        <td class="px-5 py-3"><x-backoffice.user-cell :user="$r->referrer?->referrer" /></td>
                                        <td class="px-5 py-3"><x-backoffice.user-cell :user="$r->referee" /></td>
                                        <td class="px-5 py-3"><x-backoffice.user-cell :user="$r->referrer" /></td>
                                    @else
                                        <td class="px-5 py-3"><x-backoffice.user-cell :user="$r->referrer" /></td>
                                        <td class="px-5 py-3"><x-backoffice.user-cell :user="$r->referee" /></td>
                                    @endif
                                    <td class="px-5 py-3">
                                        <x-backoffice.badge :tone="$tone[$r->status->value] ?? 'muted'">{{ $r->status->getName() }}</x-backoffice.badge>
                                    </td>
                                    <td class="px-5 py-3 font-sans text-muted text-[12px] whitespace-nowrap">{{ $r->created_at?->format('j M Y, g:i A') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-backoffice.card>

        @if ($referrals->hasPages())<div>{{ $referrals->links('vendor.pagination.nextel') }}</div>@endif

        {{-- Indirect adds the Upline above the Referrer → Referee chain. --}}
        @if ($selected)
            @php
                $rr = $selected;
                $isActive = $rr->status === \App\Enums\ReferralStatus::ACTIVE;
                $chain = $level === 'indirect'
                    ? [['Upline', $rr->referrer?->referrer], ['Referrer', $rr->referrer], ['Referee', $rr->referee]]
                    : [['Referrer', $rr->referrer], ['Referee', $rr->referee]];
            @endphp
            <div class="fixed inset-0 z-[80] flex items-end sm:items-center justify-center sm:px-4" data-lock-scroll
                 x-data="{ shown: false }" x-init="$nextTick(() => shown = true)">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" x-show="shown" x-transition.opacity wire:click="close"></div>
                <div class="relative w-full sm:max-w-[440px] bg-surface rounded-t-[26px] sm:rounded-[26px] px-6 pt-6 pb-7 flex flex-col gap-5 max-h-[88vh] overflow-y-auto"
                     x-show="shown"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-6 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2 flex-wrap">
                            <p class="font-heading font-medium text-text text-[16px]">Referral Details</p>
                            <x-backoffice.badge tone="muted">{{ ucfirst($level) }}</x-backoffice.badge>
                            <x-backoffice.badge :tone="$tone[$rr->status->value] ?? 'muted'">{{ $rr->status->getName() }}</x-backoffice.badge>
                        </div>
                        <button type="button" wire:click="close" class="size-9 rounded-full bg-background flex items-center justify-center text-text shrink-0" aria-label="Close"><i class="ri-close-line text-[20px]"></i></button>
                    </div>

                    <div class="flex flex-col gap-2">
                        @foreach ($chain as [$role, $u])
                            @unless ($loop->first)
                                <div class="flex items-center gap-1.5 pl-1 text-muted text-[12px]"><i class="ri-arrow-down-line"></i> referred</div>
                            @endunless
                            @if ($u)
                                <a href="{{ route('backoffice.users.show', $u) }}" wire:navigate class="flex items-center gap-3 rounded-[16px] bg-background px-4 py-3 hover:bg-background/70 transition">
                                    <span class="size-11 rounded-full bg-primary/10 overflow-hidden flex items-center justify-center font-heading text-primary text-[14px] shrink-0">
                                        @if ($u->avatar)<img src="{{ $u->avatar }}" alt="" class="size-full object-cover">@else{{ strtoupper(substr($u->first_name ?: $u->username, 0, 2)) }}@endif
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-sans text-muted text-[11px] uppercase tracking-wide">{{ $role }}</p>
                                        <p class="font-heading font-medium text-text text-[14px] leading-tight truncate">{{ $u->full_name }}</p>
                                        <p class="font-sans text-muted text-[12px] truncate">{{ '@'.$u->username }} · {{ $u->email }}</p>
                                    </div>
                                    <i class="ri-arrow-right-up-line text-muted text-[15px] shrink-0"></i>
                                </a>
                            @else
                                <div class="rounded-[16px] bg-background px-4 py-3 font-sans text-muted text-[13px]">{{ $role }}: —</div>
                            @endif
                        @endforeach
                    </div>

                    <div class="bg-background rounded-[16px] divide-y divide-muted/10">
                        @foreach (array_filter([
                            $isActive ? ['Package', $rr->referee?->package?->name ?? '—'] : null,
                            ['Reference', $rr->reference],
                            ['Date', $rr->created_at?->format('j M Y, g:i A')],
                        ]) as [$label, $value])
                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                <span class="font-sans text-muted text-[13px]">{{ $label }}</span>
                                <span class="font-sans text-text text-[13px] text-right truncate">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </x-backoffice.page>
</div>
