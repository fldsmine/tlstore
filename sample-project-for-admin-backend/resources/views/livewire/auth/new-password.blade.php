<div>
    <x-auth.plain title="New Password" subtitle="Enter the code sent to your email and choose a new password." :back="route('auth.forgot-password')">
        <form wire:submit="updatePassword" class="flex flex-col gap-[40px] w-full">
            <div class="flex flex-col gap-2.5 w-full">
                @if (session('status'))
                    <p class="font-sans text-[13px] text-success">{{ session('status') }}</p>
                @endif

                <x-auth.input label="Email" type="email" name="email" wire:model="email" placeholder="Johndee@gmail.com" autocomplete="email" :readonly="$emailLocked">
                    <x-slot:leading><x-auth.icon name="mail" /></x-slot:leading>
                </x-auth.input>

                <x-auth.input label="Verification Code" name="code" wire:model="code" inputmode="numeric" maxlength="6" placeholder="6-digit code">
                    <x-slot:leading><i class="ri-shield-keyhole-line text-[15px] leading-none"></i></x-slot:leading>
                </x-auth.input>

                <div x-data="{ show: false }">
                    <x-auth.input label="New Password" type="password" name="password" wire:model="password" placeholder="***********" autocomplete="new-password" x-bind:type="show ? 'text' : 'password'">
                        <x-slot:leading><x-auth.icon name="lock" /></x-slot:leading>
                        <x-slot:trailing>
                            <button type="button" @click="show = !show" class="text-muted leading-none" aria-label="Toggle password visibility">
                                <i class="ri-eye-line text-[16px]" x-show="!show"></i>
                                <i class="ri-eye-off-line text-[16px]" x-show="show" x-cloak></i>
                            </button>
                        </x-slot:trailing>
                    </x-auth.input>
                </div>
            </div>

            <x-auth.button target="updatePassword">Reset Password</x-auth.button>
        </form>
    </x-auth.plain>
</div>
