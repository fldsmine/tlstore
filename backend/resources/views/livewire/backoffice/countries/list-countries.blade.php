<div>
    <x-backoffice.page title="Countries" subtitle="Supported countries — each maps to a currency (rates are managed under Currencies)">
        @php $canWrite = $this->canWrite(); @endphp

        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 h-[44px] px-3.5 rounded-[14px] bg-surface shadow-sm w-full lg:max-w-[340px]">
                <i class="ri-search-line text-muted text-[17px]"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search countries…"
                       class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text placeholder:text-muted">
            </div>
            @if ($canWrite)
                <button type="button" wire:click="openCreate" aria-label="Add country" class="shrink-0 inline-flex items-center justify-center gap-1.5 h-[44px] w-[44px] sm:w-auto sm:px-4 rounded-[14px] bg-primary text-white font-sans text-[13px]"><i class="ri-add-line text-[22px] sm:text-[18px]"></i><span class="hidden sm:inline">Add country</span></button>
            @endif
        </div>

        <x-backoffice.card class="!p-0 overflow-hidden">
            @if ($countries->isEmpty())
                <div class="p-10 text-center font-sans text-muted text-[14px]">No countries yet.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[680px]">
                        <thead>
                            <tr class="border-b border-muted/10 font-sans text-[12px] text-muted">
                                <th class="px-5 py-3 font-medium">Country</th>
                                <th class="px-5 py-3 font-medium">Code</th>
                                <th class="px-5 py-3 font-medium">Currency</th>
                                <th class="px-5 py-3 font-medium">Rate / $1</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($countries as $country)
                                <tr wire:key="country-{{ $country->id }}" @if ($canWrite) wire:click="edit('{{ $country->id }}')" @endif class="border-b border-muted/5 last:border-0 @if ($canWrite) hover:bg-background/50 cursor-pointer @endif transition-colors">
                                    <td class="px-5 py-3 font-sans text-text text-[13px]"><span class="mr-1.5">{{ $country->flag }}</span>{{ $country->name }}</td>
                                    <td class="px-5 py-3 font-sans text-muted text-[13px]">{{ $country->code }}</td>
                                    <td class="px-5 py-3 font-sans text-muted text-[13px]">{{ $country->currency_code }}</td>
                                    <td class="px-5 py-3 font-sans text-text text-[13px] tabular-nums">{{ rtrim(rtrim(number_format((float) ($country->currency?->usd_exchange_rate ?? 0), 4), '0'), '.') }}</td>
                                    <td class="px-5 py-3">
                                        @if ($country->is_active)<x-backoffice.badge tone="success">Active</x-backoffice.badge>@else<x-backoffice.badge tone="muted">Inactive</x-backoffice.badge>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-backoffice.card>

        @if ($countries->hasPages())<div>{{ $countries->links('vendor.pagination.nextel') }}</div>@endif

        <x-backoffice.modal :show="$showModal" :title="$editingId ? 'Edit country' : 'Add country'">
            <form wire:submit="save" class="flex flex-col gap-4">
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2 flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Country name</label>
                        <input type="text" wire:model="name" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('name') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Flag</label>
                        <input type="text" wire:model="flag" maxlength="8" placeholder="🇳🇬" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[18px] text-text text-center focus:outline-none focus:ring-2 focus:ring-primary/40">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">ISO code</label>
                        <input type="text" wire:model="code" maxlength="2" placeholder="NG" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] uppercase text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('code') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Currency</label>
                        <select wire:model="currencyCode" class="h-[44px] rounded-[12px] bg-background border border-muted/15 px-3 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                            <option value="">Select currency…</option>
                            @foreach ($currencies as $cur)
                                <option value="{{ $cur->code }}">{{ $cur->code }} — {{ $cur->name }}</option>
                            @endforeach
                        </select>
                        @error('currencyCode') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
                <p class="font-sans text-muted text-[11px] -mt-1">Exchange rate &amp; symbol are managed per currency under <span class="text-text">Currencies</span>.</p>
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
