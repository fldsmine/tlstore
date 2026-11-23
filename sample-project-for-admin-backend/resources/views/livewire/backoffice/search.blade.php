<div x-data="{ open: false }"
     @open-search.window="open = true; $nextTick(() => $refs.q?.focus())"
     @keydown.escape.window="open = false"
     @keydown.window.meta.k.prevent="open = true; $nextTick(() => $refs.q?.focus())"
     @keydown.window.ctrl.k.prevent="open = true; $nextTick(() => $refs.q?.focus())"
     x-cloak>

    <div x-show="open" x-transition.opacity.duration.200ms @click="open = false"
         class="fixed inset-0 z-[85] bg-black/40 backdrop-blur-sm"></div>

    <div x-show="open" class="fixed inset-x-0 top-0 z-[90] flex justify-center px-4 pt-[11vh] pointer-events-none">
        <div x-show="open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             @click.outside="open = false"
             class="pointer-events-auto w-full max-w-[560px] bg-surface rounded-[20px] shadow-[0_24px_70px_rgba(0,0,0,0.28)] overflow-hidden flex flex-col max-h-[74vh]">

            <div class="flex items-center gap-3 px-4 h-[56px] border-b border-muted/10 shrink-0">
                <i class="ri-search-line text-muted text-[20px]"></i>
                <input x-ref="q" type="text" wire:model.live.debounce.250ms="q"
                       placeholder="Search users, tokens, withdrawals, pages…"
                       class="flex-1 bg-transparent outline-none font-sans text-[15px] text-text placeholder:text-muted">
                <button type="button" @click="open = false" class="font-sans text-muted text-[10px] border border-muted/25 rounded px-1.5 py-0.5">ESC</button>
            </div>

            <div class="overflow-y-auto p-2 flex flex-col gap-0.5">
                @if (trim($q) === '')
                    <p class="px-3 py-8 text-center font-sans text-muted text-[13px]">Search across the backoffice — users, token batches, coupons, withdrawals and pages.</p>
                @elseif ($empty)
                    <p class="px-3 py-8 text-center font-sans text-muted text-[13px]">No results for "{{ $q }}".</p>
                @endif

                @if ($users->isNotEmpty())
                    <p class="px-3 pt-2 pb-1 font-sans text-[11px] uppercase tracking-wide text-muted/60">Users</p>
                    @foreach ($users as $u)
                        <a href="{{ route('backoffice.users.show', $u) }}" wire:navigate @click="open = false"
                           class="flex items-center gap-3 rounded-[12px] px-3 py-2.5 hover:bg-background transition-colors">
                            <span class="size-9 rounded-full bg-primary/10 flex items-center justify-center font-heading text-primary text-[12px] shrink-0">{{ strtoupper(substr($u->first_name ?: $u->username, 0, 2)) }}</span>
                            <span class="flex flex-col min-w-0">
                                <span class="font-sans text-text text-[14px] truncate">{{ $u->full_name }} @if ($u->is_agent)<i class="ri-store-2-line text-primary text-[12px]"></i>@endif</span>
                                <span class="font-sans text-muted text-[11px] truncate">{{ $u->email }} · {{ '@'.$u->username }}</span>
                            </span>
                        </a>
                    @endforeach
                @endif

                @if ($telegramUsers->isNotEmpty())
                    <p class="px-3 pt-2 pb-1 font-sans text-[11px] uppercase tracking-wide text-muted/60">Mini App users</p>
                    @foreach ($telegramUsers as $identity)
                        <a href="{{ route('backoffice.telegram-users.show', $identity) }}" wire:navigate @click="open = false"
                           class="flex items-center gap-3 rounded-[12px] px-3 py-2.5 hover:bg-background transition-colors">
                            <span class="size-9 rounded-full bg-[#2AABEE]/12 flex items-center justify-center text-[#2AABEE] shrink-0"><i class="ri-telegram-line text-[16px]"></i></span>
                            <span class="flex flex-col min-w-0">
                                <span class="font-sans text-text text-[14px] truncate">{{ $identity->displayName() }}</span>
                                <span class="font-sans text-muted text-[11px] truncate">{{ $identity->handle() ?? 'Telegram' }}@if ($identity->user) · {{ '@'.$identity->user->username }}@endif</span>
                            </span>
                        </a>
                    @endforeach
                @endif

                @if ($batches->isNotEmpty())
                    <p class="px-3 pt-2 pb-1 font-sans text-[11px] uppercase tracking-wide text-muted/60">Token Batches</p>
                    @foreach ($batches as $b)
                        <a href="{{ route('backoffice.batches.show', $b) }}" wire:navigate @click="open = false"
                           class="flex items-center gap-3 rounded-[12px] px-3 py-2.5 hover:bg-background transition-colors">
                            <span class="size-9 rounded-full bg-background flex items-center justify-center text-muted shrink-0"><i class="ri-stack-line text-[16px]"></i></span>
                            <span class="flex flex-col min-w-0">
                                <span class="font-sans text-text text-[14px] truncate">{{ $b->reference }}</span>
                                <span class="font-sans text-muted text-[11px] truncate">{{ $b->type->getName() }} · {{ $b->agent?->username ? '@'.$b->agent->username : '—' }}</span>
                            </span>
                        </a>
                    @endforeach
                @endif

                @if ($coupons->isNotEmpty())
                    <p class="px-3 pt-2 pb-1 font-sans text-[11px] uppercase tracking-wide text-muted/60">Coupons</p>
                    @foreach ($coupons as $c)
                        <a href="{{ route('backoffice.coupons.index', ['search' => $c->code]) }}" wire:navigate @click="open = false"
                           class="flex items-center gap-3 rounded-[12px] px-3 py-2.5 hover:bg-background transition-colors">
                            <span class="size-9 rounded-full bg-background flex items-center justify-center text-muted shrink-0"><i class="ri-coupon-line text-[16px]"></i></span>
                            <span class="flex flex-col min-w-0">
                                <span class="font-mono text-text text-[13px] tracking-wide truncate">{{ $c->code }}</span>
                                <span class="font-sans text-muted text-[11px] truncate">{{ $c->type->getName() }} · {{ $c->is_valid ? 'Active' : 'Used' }}</span>
                            </span>
                        </a>
                    @endforeach
                @endif

                @if ($withdrawals->isNotEmpty())
                    <p class="px-3 pt-2 pb-1 font-sans text-[11px] uppercase tracking-wide text-muted/60">Withdrawals</p>
                    @foreach ($withdrawals as $w)
                        @php $exported = $w->exported_at && $w->status === \App\Enums\WithdrawalStatus::PROCESSING; @endphp
                        <a href="{{ route('backoffice.withdrawals.index', ['search' => $w->reference]) }}" wire:navigate @click="open = false"
                           class="flex items-center gap-3 rounded-[12px] px-3 py-2.5 hover:bg-background transition-colors">
                            <span class="size-9 rounded-full bg-background flex items-center justify-center text-muted shrink-0"><i class="ri-money-dollar-circle-line text-[16px]"></i></span>
                            <span class="flex flex-col min-w-0">
                                <span class="font-sans text-text text-[14px] truncate">{{ money($w->amount) }} · {{ $w->reference }}</span>
                                <span class="font-sans text-muted text-[11px] truncate">{{ $w->user?->username ? '@'.$w->user->username : '—' }} · {{ $w->created_at?->format('j M Y, g:i A') }}</span>
                            </span>
                            <x-backoffice.badge :tone="$exported ? 'purple' : ($w->status === \App\Enums\WithdrawalStatus::PROCESSING ? 'blue' : $w->status->color())" class="ml-auto shrink-0">{{ $exported ? 'Exported' : $w->status->getName() }}</x-backoffice.badge>
                        </a>
                    @endforeach
                @endif

                @if ($pages->isNotEmpty())
                    <p class="px-3 pt-2 pb-1 font-sans text-[11px] uppercase tracking-wide text-muted/60">Pages</p>
                    @foreach ($pages as $p)
                        <a href="{{ route($p['route'], $p['params'] ?? []) }}" wire:navigate @click="open = false"
                           class="flex items-center gap-3 rounded-[12px] px-3 py-2.5 hover:bg-background transition-colors">
                            <span class="size-9 rounded-full bg-background flex items-center justify-center text-primary shrink-0"><i class="{{ $p['icon'] }} text-[17px]"></i></span>
                            <span class="font-sans text-text text-[14px]">{{ $p['label'] }}</span>
                            <i class="ri-corner-down-left-line text-muted/50 text-[14px] ml-auto"></i>
                        </a>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>
