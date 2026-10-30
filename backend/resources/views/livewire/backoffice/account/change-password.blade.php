<div>
    <x-backoffice.page title="My Account" subtitle="Manage your profile and password">
        <x-backoffice.account-tabs active="password" />

        <div class="max-w-[820px] w-full">
            <x-backoffice.card>
                <form wire:submit="save" class="flex flex-col gap-5">
                    <div class="flex flex-col gap-1.5">
                        <label for="current_password" class="font-sans text-text text-[13px]">Current password</label>
                        <input id="current_password" type="password" wire:model="current_password" autocomplete="current-password"
                               class="w-full h-[48px] rounded-[14px] bg-background border border-muted/15 px-4 font-sans text-[14px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('current_password') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="password" class="font-sans text-text text-[13px]">New password</label>
                        <input id="password" type="password" wire:model="password" autocomplete="new-password"
                               class="w-full h-[48px] rounded-[14px] bg-background border border-muted/15 px-4 font-sans text-[14px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                        @error('password') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label for="password_confirmation" class="font-sans text-text text-[13px]">Confirm new password</label>
                        <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password"
                               class="w-full h-[48px] rounded-[14px] bg-background border border-muted/15 px-4 font-sans text-[14px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                    </div>

                    <div class="flex justify-end pt-1">
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="h-[46px] px-6 rounded-[14px] bg-primary text-white font-sans text-[14px] hover:opacity-95 transition disabled:opacity-60">
                            <span wire:loading.remove wire:target="save">Update password</span>
                            <span wire:loading wire:target="save" class="flex items-center justify-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Updating…</span>
                        </button>
                    </div>
                </form>
            </x-backoffice.card>
        </div>
    </x-backoffice.page>
</div>
