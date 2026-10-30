<x-app.settings-shell title="Change Password" :back="route('profile')">

    <div class="flex flex-col gap-[22px]">

        @if ($saved)
            <div class="flex items-center gap-2 bg-success/10 text-success rounded-[14px] px-4 py-3 font-sans text-[13px]">
                <i class="ri-checkbox-circle-fill text-[18px]"></i> Your password has been changed.
            </div>
        @endif

        <div class="flex flex-col gap-2.5">
            @foreach ([
                ['key' => 'current_password', 'label' => 'Current Password', 'placeholder' => 'Enter current password'],
                ['key' => 'password', 'label' => 'New Password', 'placeholder' => 'At least 8 characters'],
                ['key' => 'password_confirmation', 'label' => 'Confirm New Password', 'placeholder' => 'Re-enter new password'],
            ] as $field)
                <div x-data="{ show: false }">
                    <x-auth.input :label="$field['label']" :name="$field['key']" wire:model="{{ $field['key'] }}"
                                  x-bind:type="show ? 'text' : 'password'" :placeholder="$field['placeholder']">
                        <x-slot:leading>
                            <i class="ri-lock-line text-[14px]"></i>
                        </x-slot:leading>
                        <x-slot:trailing>
                            <button type="button" @click="show = !show" class="text-muted" aria-label="Toggle password">
                                <i class="ri-eye-line text-[16px]" x-show="!show"></i>
                                <i class="ri-eye-off-line text-[16px]" x-show="show" x-cloak></i>
                            </button>
                        </x-slot:trailing>
                    </x-auth.input>
                </div>
            @endforeach
        </div>

        <button type="button" wire:click="save" wire:loading.attr="disabled"
                class="w-full h-[48px] rounded-[30px] bg-primary text-surface font-sans text-[14px] flex items-center justify-center gap-2 hover:opacity-95 transition disabled:opacity-60">
            <span wire:loading.remove wire:target="save">Update Password</span>
            <span wire:loading wire:target="save" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Updating…</span>
        </button>
    </div>
</x-app.settings-shell>
