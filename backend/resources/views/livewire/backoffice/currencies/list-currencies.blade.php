<div>
    <x-backoffice.page title="Currencies" subtitle="Manage currencies & USD exchange rates — set a rate once and every country on it follows">
        @php $canWrite = $this->canWrite(); @endphp

        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[340px]">
                <i class="ri-search-line text-muted text-[17px]"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search currencies…"
                       class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
            </div>
            @if ($canWrite)
                <button type="button" wire:click="openCreate" aria-label="Add currency" class="shrink-0 inline-flex items-center justify-center gap-1.5 h-[44px] w-[44px] sm:w-auto sm:px-4 rounded-[14px] bg-primary text-white font-sans text-[13px]"><i class="ri-add-line text-[22px] sm:text-[18px]"></i><span class="hidden sm:inline">Add currency</span></button>
            @endif
        </div>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($currencies->isEmpty())
                <div class="p-10 text-center font-sans text-muted text-[14px]">No currencies yet.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[640px]">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">Currency</th>
                                <th class="px-5 py-3 font-medium">Code</th>
                                <th class="px-5 py-3 font-medium">Symbol</th>
                                <th class="px-5 py-3 font-medium">Rate / $1</th>
                                <th class="px-5 py-3 font-medium">Countries</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($currencies as $currency)
                                <tr wire:key="currency-{{ $currency->id }}" @if ($canWrite) wire:click="edit('{{ $currency->id }}')" @endif class="border-b border-muted/5 last:border-0 @if ($canWrite) hover:bg-background/50 cursor-pointer @endif transition-colors">
                                    <td class="px-5 py-3 font-sans text-text text-[13px]">{{ $currency->name }}</td>
                                    <td class="px-5 py-3 font-sans text-muted text-[13px]">{{ $currency->code }}</td>
                                    <td class="px-5 py-3 font-sans text-muted text-[13px]">{{ $currency->symbol }}</td>
                                    <td class="px-5 py-3 font-sans text-text text-[13px] tabular-nums">{{ rtrim(rtrim(number_format((float) $currency->usd_exchange_rate, 4), '0'), '.') }}</td>
                                    <td class="px-5 py-3 font-sans text-muted text-[13px] tabular-nums">{{ $currency->countries_count }}</td>
                                    <td class="px-5 py-3">
                                        @if ($currency->is_active)<x-backoffice.badge tone="success">Active</x-backoffice.badge>@else<x-backoffice.badge tone="muted">Inactive</x-backoffice.badge>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-backoffice.card>

        @if ($currencies->hasPages())<div>{{ $currencies->links('vendor.pagination.nextel') }}</div>@endif

        <x-backoffice.modal :show="$showModal" :title="$editingId ? 'Edit currency' : 'Add currency'">
            <form wire:submit="save" class="flex flex-col gap-4">
                <div class="grid grid-cols-3 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Code</label>
                        <input type="text" wire:model="code" maxlength="3" placeholder="NGN" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] uppercase text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('code') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-span-2 flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Currency name</label>
                        <input type="text" wire:model="name" placeholder="Nigerian Naira" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('name') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Symbol</label>
                        <input type="text" wire:model="symbol" maxlength="8" placeholder="₦" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('symbol') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Exchange rate (local per $1)</label>
                        <input type="number" step="0.0001" min="0" wire:model="usdExchangeRate" placeholder="1000" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('usdExchangeRate') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
                <x-backoffice.toggle wire:model="isActive" label="Active" />
                <div class="flex justify-end gap-2 mt-1">
                    <button type="button" wire:click="$set('showModal', false)" class="h-[42px] px-4 rounded-full bg-background text-muted font-sans text-[13px]">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save" class="h-[42px] px-5 rounded-full bg-primary text-white font-sans text-[13px] disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">Save</span>
                        <span wire:loading wire:target="save" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Saving…</span>
                    </button>
                </div>
            </form>
        </x-backoffice.modal>
    </x-backoffice.page>
</div>
