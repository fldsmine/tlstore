<div>
    <x-auth.plain title="Transaction PIN" subtitle="Set a 4-digit Transaction PIN. You'll use it to authorise withdrawals and other secure actions." :back="route('activate')">
        <form wire:submit="setPin" class="flex flex-col gap-[50px] w-full">
            <div class="flex flex-col gap-2.5 w-full">
                <x-auth.input label="Transaction PIN (4 digits) *" type="password" name="transactionPin" wire:model="transactionPin" inputmode="numeric" maxlength="4" placeholder="••••" />

                <x-auth.input label="Confirm Transaction PIN *" type="password" name="confirmTransactionPin" wire:model="confirmTransactionPin" inputmode="numeric" maxlength="4" placeholder="••••" />
            </div>

            <x-auth.button target="setPin">Set PIN</x-auth.button>
        </form>
    </x-auth.plain>

    <x-auth.success-modal on="pin-created" action="continueToDashboard" title="PIN Created" message="Your Transaction PIN has been set successfully" />
</div>
