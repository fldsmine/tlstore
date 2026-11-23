<div>
    <x-backoffice.page title="My profile" subtitle="Backoffice account details">
        <x-backoffice.card title="Account">
            <form wire:submit="save" class="flex flex-col gap-4 max-w-[520px]">
                <div class="flex items-center gap-4 pb-4 border-b border-muted/10">
                    <span class="size-16 rounded-full bg-primary/12 text-primary font-heading font-semibold text-[20px] flex items-center justify-center select-none">{{ $admin->initials() }}</span>
                    <div>
                        <p class="font-heading font-semibold text-text text-[16px] leading-tight">{{ $admin->full_name }}</p>
                        <p class="font-sans text-muted text-[12px] mt-0.5">{{ $admin->roleLabel() }}</p>
                    </div>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="label" for="fullName">Full name</label>
                    <input id="fullName" type="text" wire:model="fullName" class="input" required maxlength="180">
                    @error('fullName')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="label" for="email">Email (used to sign in)</label>
                    <input id="email" type="email" wire:model="email" class="input" required maxlength="191">
                    @error('email')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="flex items-center gap-2">
                    <button type="submit" class="btn btn-primary btn-md"><i class="ri-save-line"></i> Save profile</button>
                    <a href="{{ route('backoffice.account.password') }}" class="btn btn-quiet btn-md"><i class="ri-key-line"></i> Change password</a>
                </div>
            </form>
        </x-backoffice.card>
        <p class="font-sans text-[12px] text-muted">Two-factor authentication for the backoffice is not enabled; password change, session invalidation and token revocation are the available safeguards. Set a strong password and keep it private.</p>
    </x-backoffice.page>
</div>
