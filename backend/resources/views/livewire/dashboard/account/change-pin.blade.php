<x-app.settings-shell title="Change PIN" :back="route('profile')">

    @if ($done)
        <div class="flex flex-col items-center gap-3 text-center py-6">
            <span class="size-14 rounded-full bg-success/15 flex items-center justify-center text-success"><i class="ri-shield-check-fill text-[28px]"></i></span>
            <p class="font-heading font-medium text-text text-[18px]">PIN changed successfully</p>
            <p class="font-sans text-muted text-[14px] max-w-[320px]">Your transaction PIN has been updated. Use the new PIN for your next withdrawal.</p>
            <a href="{{ route('profile') }}" wire:navigate class="mt-2 h-[45px] px-6 rounded-[30px] bg-primary text-surface font-sans text-[14px] inline-flex items-center justify-center hover:opacity-95 transition">Back to Profile</a>
        </div>
    @else
        <form wire:submit="proceed" class="flex flex-col gap-2.5 w-full">
            <x-auth.input label="Current PIN" type="password" name="currentPin" wire:model="currentPin" inputmode="numeric" maxlength="4" placeholder="••••">
                <x-slot:leading>
                    <i class="ri-lock-line text-[14px]"></i>
                </x-slot:leading>
            </x-auth.input>

            <div class="flex flex-col sm:flex-row gap-2.5">
                <x-auth.input label="New PIN (4 digits)" type="password" name="newPin" wire:model="newPin" inputmode="numeric" maxlength="4" placeholder="••••" />
                <x-auth.input label="Confirm New PIN" type="password" name="confirmPin" wire:model="confirmPin" inputmode="numeric" maxlength="4" placeholder="••••" />
            </div>

            <button type="submit" wire:loading.attr="disabled"
                    class="mt-1 w-full h-[48px] rounded-[30px] bg-primary text-surface font-sans text-[14px] flex items-center justify-center gap-2 hover:opacity-95 transition disabled:opacity-60">
                <span wire:loading.remove wire:target="proceed">Change PIN</span>
                <span wire:loading wire:target="proceed" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Updating…</span>
            </button>

            <a href="{{ route('reset-pin') }}" wire:navigate class="text-center font-sans text-[12px] text-primary mt-1">Forgot your PIN? Reset it with a token →</a>
        </form>
    @endif
</x-app.settings-shell>
