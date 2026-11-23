<div>
    <x-backoffice.page title="Settings" subtitle="Platform configuration">
        <x-backoffice.settings-tabs active="withdrawal" />

        @php $input = 'h-[46px] rounded-[12px] bg-background border border-muted/15 px-3.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40'; @endphp

        <form wire:submit="save" class="flex flex-col gap-5">
            <x-backoffice.card title="Per-wallet withdrawals">
                <p class="font-sans text-muted text-[12px] -mt-2 mb-4 leading-relaxed">Each wallet is configured on its own — open/close it, set its minimum, maximum (0 = no cap) and flat fee, whether requests auto-approve, and when the wallet is debited. Closing every wallet pauses withdrawals.</p>
                <div class="flex flex-col gap-3">
                    @foreach ($walletLabels as $key => $label)
                        <div wire:key="wd-{{ $key }}"
                             x-data="{ autoApprove: @js((bool) ($wallets[$key]['auto_approve'] ?? false)), priorDeductAt: @js((string) ($wallets[$key]['deduct_at'] ?? 'request')) }"
                             x-effect="
                                 $wire.$set('wallets.{{ $key }}.auto_approve', autoApprove, false);
                                 $wire.$set('wallets.{{ $key }}.deduct_at', autoApprove ? 'request' : priorDeductAt, false)
                             "
                             class="rounded-[14px] border border-muted/10 p-3 sm:p-3.5 flex flex-col gap-3">
                            <x-backoffice.toggle wire:model="wallets.{{ $key }}.enabled" :label="$label" />
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div class="flex flex-col gap-1.5">
                                    <label class="font-sans text-muted text-[12px]">Minimum ($)</label>
                                    <input type="number" inputmode="decimal" step="any" wire:model="wallets.{{ $key }}.min" class="{{ $input }}">
                                    @error("wallets.{$key}.min") <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <label class="font-sans text-muted text-[12px]">Maximum ($, 0 = none)</label>
                                    <input type="number" inputmode="decimal" step="any" wire:model="wallets.{{ $key }}.max" class="{{ $input }}">
                                    @error("wallets.{$key}.max") <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <label class="font-sans text-muted text-[12px]">Flat fee ($, 0 = none)</label>
                                    <input type="number" inputmode="decimal" step="any" wire:model="wallets.{{ $key }}.fee" class="{{ $input }}">
                                    @error("wallets.{$key}.fee") <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:items-start">
                                <x-backoffice.toggle x-model="autoApprove" label="Auto-approve" description="Skip manual review — go straight to payout-ready." />
                                <div class="flex flex-col gap-1.5">
                                    <label class="font-sans text-muted text-[12px]">Debit wallet</label>
                                    <select wire:model="wallets.{{ $key }}.deduct_at" x-on:change="priorDeductAt = $event.target.value" :disabled="autoApprove" class="{{ $input }}" :class="autoApprove && 'opacity-60 cursor-not-allowed'">
                                        <option value="request">On request</option>
                                        <option value="approval">On approval</option>
                                    </select>
                                    <span class="font-sans text-[11px] text-muted" x-show="autoApprove" x-cloak>Auto-approve always debits on request.</span>
                                    @error("wallets.{$key}.deduct_at") <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <label class="font-sans text-muted text-[12px]">Who can withdraw</label>
                                    <select wire:model="wallets.{{ $key }}.country_access" class="{{ $input }}">
                                        <option value="all">Everybody</option>
                                        <option value="nigerian">Nigerians</option>
                                        <option value="non_nigerian">Non Nigerians</option>
                                    </select>
                                    @error("wallets.{$key}.country_access") <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-backoffice.card>

            <x-backoffice.card title="Bank verification and payout">
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Verification provider</label>
                        <select wire:model="bankVerificationProvider" class="{{ $input }}">
                            <option value="paystack">Paystack</option>
                            <option value="flutterwave">Flutterwave</option>
                        </select>
                        @error('bankVerificationProvider') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Payout narration</label>
                        <input type="text" wire:model="payoutNarration" placeholder="{{ config('app.name') }} Payout" class="{{ $input }}">
                        <span class="font-sans text-[11px] text-muted">Statement descriptor on bank-transfer payout sheets. Leave blank for the default.</span>
                        @error('payoutNarration') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
            </x-backoffice.card>

            <div class="flex justify-end">
                <button type="submit" class="h-[46px] px-6 rounded-[14px] bg-primary text-white font-sans text-[14px]">
                    <span wire:loading.remove wire:target="save">Save changes</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
            </div>
        </form>
    </x-backoffice.page>
</div>
