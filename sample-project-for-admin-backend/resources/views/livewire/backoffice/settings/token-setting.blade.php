<div>
    <x-backoffice.page title="Settings" subtitle="Platform configuration">
        <x-backoffice.settings-tabs active="tokens" />

        @php $input = 'h-[46px] rounded-[12px] bg-background border border-muted/15 px-3.5 font-sans text-[13px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40'; @endphp

        <form wire:submit="save" class="flex flex-col gap-5">
            <x-backoffice.card title="Availability">
                <x-backoffice.toggle wire:model="pinTokenEnabled" label="PIN reset token purchase enabled"
                                     description="When off, vendors cannot generate PIN reset tokens. Tokens already issued can still be redeemed." />
            </x-backoffice.card>

            <x-backoffice.card title="PIN-reset pricing">
                <p class="font-sans text-muted text-[13px] mb-4">Each price is a pair: the Nigerian value applies to Nigerian vendors and users, the foreign value to everyone else. A vendor mints codes for their own region at that region's fee, and a user is shown their own region's price.</p>

                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">User price — Nigeria ($)</label>
                        <input type="number" step="any" wire:model="pinResetTokenPrice" class="{{ $input }}">
                        <span class="font-sans text-[11px] text-muted">What a Nigerian user pays to reset their PIN.</span>
                        @error('pinResetTokenPrice') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">User price — Foreign ($)</label>
                        <input type="number" step="any" wire:model="pinResetTokenForeignPrice" class="{{ $input }}">
                        <span class="font-sans text-[11px] text-muted">What a non-Nigerian user pays to reset their PIN.</span>
                        @error('pinResetTokenForeignPrice') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Agent fee — Nigeria ($)</label>
                        <input type="number" step="any" wire:model="pinResetTokenAgentPrice" class="{{ $input }}">
                        <span class="font-sans text-[11px] text-muted">What a Nigerian vendor pays to generate one.</span>
                        @error('pinResetTokenAgentPrice') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-muted text-[12px]">Agent fee — Foreign ($)</label>
                        <input type="number" step="any" wire:model="pinResetTokenForeignAgentPrice" class="{{ $input }}">
                        <span class="font-sans text-[11px] text-muted">What a non-Nigerian vendor pays to generate one.</span>
                        @error('pinResetTokenForeignAgentPrice') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>
                </div>
            </x-backoffice.card>

            <div class="flex items-start gap-3 rounded-[16px] bg-primary/5 border border-primary/15 px-4 py-3.5">
                <i class="ri-information-line text-primary text-[18px] shrink-0 mt-0.5"></i>
                <p class="font-sans text-muted text-[12px]">Package, Additional-Minute-Plan and Cloud-Plan prices (user price &amp; vendor price) are managed per item in the
                    <a href="{{ route('backoffice.packages.index') }}" wire:navigate class="text-primary underline">Packages</a>,
                    <a href="{{ route('backoffice.minute-plans.index') }}" wire:navigate class="text-primary underline">Minute Plans</a> and
                    <a href="{{ route('backoffice.cloud.storage-plans.index') }}" wire:navigate class="text-primary underline">Cloud Storage Plans</a> catalog screens.</p>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="h-[46px] px-6 rounded-[14px] bg-primary text-white font-sans text-[14px]">
                    <span wire:loading.remove wire:target="save">Save changes</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
            </div>
        </form>
    </x-backoffice.page>
</div>
