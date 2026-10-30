<div>
    <x-backoffice.page title="Live Chat" subtitle="Chat availability & retention">

        <x-backoffice.settings-tabs active="chat" />

        @php $input = 'h-[42px] w-full rounded-[12px] bg-background border border-muted/15 px-3.5 font-sans text-[14px] text-text outline-none focus:border-primary focus:ring-1 focus:ring-primary'; @endphp

        <form wire:submit="save" class="flex flex-col gap-5">

            <x-backoffice.card title="Master switch">
                <x-backoffice.toggle wire:model="enabled" label="Live chat enabled"
                                     description="Turn LiveChat (customers messaging distributors, and the vendor Live Chats inbox) on or off across the app." />
            </x-backoffice.card>

            <x-backoffice.card title="History">
                <div class="flex flex-col gap-1.5 sm:max-w-[240px]">
                    <label class="font-sans text-[12px] text-muted">Retention (days)</label>
                    <input type="number" min="1" step="1" wire:model="historyRetention" class="{{ $input }}">
                    <p class="font-sans text-muted text-[11px]">Conversations with no new messages for this many days are permanently removed by the daily prune.</p>
                    @error('historyRetention')<span class="font-sans text-[12px] text-danger">{{ $message }}</span>@enderror
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
