<div>
    <x-auth.plain title="Forgot Password" subtitle="Enter your email or phone number to reset your password." :back="route('auth.login')">
        <form wire:submit="sendReset" class="flex flex-col gap-[50px] w-full">
            <x-auth.input label="Email" type="email" name="email" wire:model="email" placeholder="Johndee@gmail.com" autocomplete="email">
                <x-slot:leading><x-auth.icon name="mail" /></x-slot:leading>
                <x-slot:trailing>
                    <span class="opacity-0 transition group-has-[input:not(:placeholder-shown)]:opacity-100"><x-auth.icon name="check" /></span>
                </x-slot:trailing>
            </x-auth.input>

            <x-auth.button target="sendReset">Continue</x-auth.button>
        </form>
    </x-auth.plain>
</div>
