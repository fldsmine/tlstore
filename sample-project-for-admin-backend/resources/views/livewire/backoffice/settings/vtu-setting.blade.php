<div>
    <x-backoffice.page title="Bill Payments" subtitle="VTU purchase rules (VTpass)">

        <x-backoffice.settings-tabs active="vtu" />

        @php $input = 'h-[42px] w-full rounded-[12px] bg-background border border-muted/15 px-3.5 font-sans text-[14px] text-text outline-none focus:border-primary focus:ring-1 focus:ring-primary'; @endphp

        <form wire:submit="save" class="flex flex-col gap-5">

            <x-backoffice.card title="Master switch">
                <x-backoffice.toggle wire:model="enabled" label="Bill payments enabled"
                                     description="Turn all VTU purchases (airtime, data, TV, electricity, education) on or off." />
            </x-backoffice.card>

            <x-backoffice.card title="Fulfilment provider">
                <p class="font-sans text-muted text-[13px] mb-3">Which provider fulfils bill payments. The provider is pinned on each order, so switching never affects orders already in flight.</p>
                <div class="flex flex-col gap-1.5 max-w-[320px]">
                    <select wire:model="provider" class="{{ $input }}">
                        @foreach ($providers as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('provider')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                </div>
            </x-backoffice.card>

            <x-backoffice.card title="Per-wallet rules">
                <p class="font-sans text-muted text-[13px] mb-4">Choose which wallets can pay for bills, the min &amp; max spend per purchase (in ₦, VTpass’s currency — min 0 = no floor, max 0 = no cap), and how many successful purchases a user may ever make from that wallet (0 = unlimited).</p>

                <div class="flex flex-col gap-3">
                    @foreach (array_keys($wallets) as $key)
                        <div wire:key="vtu-{{ $key }}" class="rounded-[16px] border border-muted/12 p-4 flex flex-col gap-3.5">
                            <x-backoffice.toggle wire:model="wallets.{{ $key }}.enabled" :label="$walletLabels[$key] ?? $key" />

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div class="flex flex-col gap-1.5">
                                    <label class="font-sans text-[12px] text-muted">Min per purchase (₦)</label>
                                    <input type="number" min="0" step="1" wire:model="wallets.{{ $key }}.min_ngn" class="{{ $input }}">
                                    @error("wallets.{$key}.min_ngn")<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <label class="font-sans text-[12px] text-muted">Max per purchase (₦)</label>
                                    <input type="number" min="0" step="1" wire:model="wallets.{{ $key }}.max_ngn" class="{{ $input }}">
                                    @error("wallets.{$key}.max_ngn")<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <label class="font-sans text-[12px] text-muted">Max lifetime purchases</label>
                                    <input type="number" min="0" step="1" wire:model="wallets.{{ $key }}.max_purchases" class="{{ $input }}">
                                    @error("wallets.{$key}.max_purchases")<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-backoffice.card>

            <div class="flex justify-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="save"
                        class="h-[46px] px-6 rounded-full bg-primary text-white font-sans font-semibold text-[14px] hover:opacity-95 transition disabled:opacity-60">
                    <span wire:loading.remove wire:target="save">Save changes</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
            </div>
        </form>
    </x-backoffice.page>
</div>
