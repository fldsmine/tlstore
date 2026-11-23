<div>
    <x-backoffice.page title="Change password" subtitle="Rotate your backoffice credentials">
        <x-backoffice.card title="Change password">
            <form wire:submit="save" class="flex flex-col gap-4 max-w-[520px]">
                <div class="flex flex-col gap-1.5">
                    <label class="label" for="currentPassword">Current password</label>
                    <input id="currentPassword" type="password" wire:model="currentPassword" class="input" autocomplete="current-password" required>
                    @error('currentPassword')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="label" for="newPassword">New password</label>
                    <input id="newPassword" type="password" wire:model="newPassword" class="input" autocomplete="new-password" required>
                    <p class="font-sans text-[11px] text-muted">Minimum 12 characters. Changing your password revokes all backoffice API tokens and signs out remember-me sessions.</p>
                    @error('newPassword')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="label" for="newPasswordConfirmation">Confirm new password</label>
                    <input id="newPasswordConfirmation" type="password" wire:model="newPasswordConfirmation" class="input" autocomplete="new-password" required>
                    @error('newPasswordConfirmation')<span class="error">{{ $message }}</span>@enderror
                </div>
                <button type="submit" class="btn btn-primary btn-md self-start"><i class="ri-key-line"></i> Change password</button>
            </form>
        </x-backoffice.card>
    </x-backoffice.page>
</div>
