<div>
    <x-backoffice.page title="Nextel Cloud" subtitle="Master switch & live-call timing">

        <x-backoffice.settings-tabs active="cloud" />

        @php $input = 'h-[42px] w-full rounded-[12px] bg-background border border-muted/15 px-3.5 font-sans text-[14px] text-text outline-none focus:border-primary focus:ring-1 focus:ring-primary'; @endphp

        <form wire:submit="save" class="flex flex-col gap-5">

            <x-backoffice.card title="Master switch">
                <x-backoffice.toggle wire:model="enabled" label="Nextel Cloud enabled"
                                     description="Turn Cloud earning (sponsored calls & SMS) on or off across the app." />
            </x-backoffice.card>

            <x-backoffice.card title="Referral bonus">
                <div class="flex flex-col gap-1.5 sm:max-w-[240px]">
                    <label class="font-sans text-[12px] text-muted">First-call bonus (USD)</label>
                    <input type="number" min="0" step="any" wire:model="referralBonus" class="{{ $input }}">
                    <p class="font-sans text-muted text-[11px]">Paid once to the upline when a referee makes their first sponsored call (0 = no bonus). ₦1 ≈ $0.001.</p>
                    @error('referralBonus')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                </div>
            </x-backoffice.card>

            <x-backoffice.card title="Live calls">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-[12px] text-muted">Online window (seconds)</label>
                        <input type="number" min="5" step="1" wire:model="onlineWindowSeconds" class="{{ $input }}">
                        <p class="font-sans text-muted text-[11px]">A user is "online" (can receive live broadcasts) if seen within this many seconds.</p>
                        @error('onlineWindowSeconds')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="font-sans text-[12px] text-muted">Broadcast expiry (minutes)</label>
                        <input type="number" min="1" step="1" wire:model="broadcastExpiryMinutes" class="{{ $input }}">
                        <p class="font-sans text-muted text-[11px]">How long an incoming broadcast stays answerable before it expires.</p>
                        @error('broadcastExpiryMinutes')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
            </x-backoffice.card>

            <div class="flex justify-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="h-[44px] px-6 rounded-full bg-primary text-white font-sans text-[13px] inline-flex items-center gap-2 disabled:opacity-60">
                    <span wire:loading.remove wire:target="save">Save changes</span>
                    <span wire:loading wire:target="save" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Saving…</span>
                </button>
            </div>
        </form>
    </x-backoffice.page>
</div>
