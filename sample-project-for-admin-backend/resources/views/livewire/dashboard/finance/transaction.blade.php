<div class="min-h-screen lg:h-screen lg:overflow-hidden bg-background">
    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-6 lg:h-full flex flex-col gap-[22px] lg:gap-5">

        <x-app.app-bar :title="'Transactions'" />

        <div class="flex flex-col gap-6 lg:flex-row lg:gap-6 lg:flex-1 lg:min-h-0">

            {{-- Fixed on desktop while the history scrolls. --}}
            <div class="flex flex-col gap-4 lg:gap-5 lg:w-[38%] lg:max-w-[400px] lg:shrink-0 lg:self-start lg:bg-surface lg:rounded-[24px] lg:p-6 lg:shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                <x-app.wallet-switcher :balances="$user->walletBalances(withLifetime: false)" size="28px" :withdraw="true" />

                {{-- Tap a stat to filter. --}}
                <div class="grid grid-cols-3 gap-2 mt-1 lg:mt-0 lg:gap-1.5 lg:bg-background lg:rounded-[18px] lg:p-1.5">
                    @foreach ([['earned', 'Total Earned', $totalEarned, 'text-success'], ['pending', 'Pending', $pending, 'text-[#d79937]'], ['withdrawn', 'Withdrawn', $withdrawn, 'text-primary']] as [$key, $label, $val, $color])
                        <button type="button" wire:click="setFilter('{{ $filter === $key ? 'all' : $key }}')"
                                class="flex flex-col items-center gap-1.5 rounded-[16px] lg:rounded-[14px] py-3 transition-colors {{ $filter === $key ? 'bg-surface shadow-[0_4px_9px_rgba(194,194,194,0.12)] ring-1 ring-primary/15' : 'hover:bg-surface/60' }}">
                            <x-money :usd="$val" :decimals="0" class="font-heading font-medium {{ $color }} text-[20px] leading-none" />
                            <p class="font-sans text-muted text-[12px] text-center">{{ $label }}</p>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Header + pagination stay put; only the rows scroll on desktop. --}}
            <div class="flex flex-col gap-[15px] lg:flex-1 lg:min-w-0 lg:min-h-0 lg:h-full">
                <div class="flex items-center justify-between lg:shrink-0">
                    <p class="font-heading font-medium text-text text-[18px]">Transaction History</p>
                    @if ($filter !== 'all')
                        <button type="button" wire:click="setFilter('all')" class="font-sans text-[13px] text-primary">Show all</button>
                    @endif
                </div>

                <div class="flex items-center gap-2 h-[42px] px-3.5 rounded-[14px] bg-surface shadow-sm lg:shrink-0">
                    <i class="ri-search-line text-muted text-[17px]"></i>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search reference or description…"
                           class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
                    @if ($search !== '')
                        <button type="button" wire:click="$set('search', '')" class="text-muted hover:text-text"><i class="ri-close-line text-[16px]"></i></button>
                    @endif
                </div>

                @if ($transactions->isEmpty())
                    <div class="bg-surface rounded-[20px] p-8 flex flex-col items-center gap-2.5 text-center shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                        <span class="size-12 rounded-full bg-background flex items-center justify-center text-muted"><i class="ri-exchange-dollar-line text-[22px]"></i></span>
                        <p class="font-sans text-muted text-[14px]">No transactions{{ $filter !== 'all' ? ' in this filter' : '' }}.</p>
                    </div>
                @else
                    <div class="flex flex-col gap-2.5 lg:flex-1 lg:min-h-0 lg:overflow-y-auto lg:overflow-x-hidden lg:px-1.5 lg:-mx-1.5 lg:py-0.5">
                        @foreach ($transactions as $tx)
                            <x-app.transaction-row :tx="$tx" wire:click="showDetails('{{ $tx->id }}')"
                                class="bg-surface rounded-[40px] pl-2 pr-4 cursor-pointer hover:shadow-[0_6px_16px_rgba(194,194,194,0.25)] transition-shadow shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]" />
                        @endforeach
                    </div>

                    @if ($transactions->hasPages())
                        <div class="pt-1 lg:shrink-0">{{ $transactions->links('vendor.pagination.nextel') }}</div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <x-app.bottom-nav />

    <x-app.transaction-details :tx="$selectedTx" />
</div>
