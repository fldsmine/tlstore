<div>
    <x-auth.plain title="Verify Your Email" subtitle="Please enter the 6-digit code sent to your email." :back="route('auth.register')">
        <form wire:submit="verify" class="flex flex-col gap-[50px] w-full">
            <div class="flex flex-col gap-[15px] items-center w-full">
                @if (session('status'))
                    <p class="font-sans text-[13px] text-success text-center">{{ session('status') }}</p>
                @endif

                <div x-data="{ d: ['', '', '', '', '', ''], get code() { return this.d.join(''); } }"
                     x-init="$watch('code', value => $wire.set('code', value))"
                     class="flex gap-2 sm:gap-4 justify-center w-full">
                    @foreach (range(0, 5) as $i)
                        <input type="text" inputmode="numeric" maxlength="1" x-model="d[{{ $i }}]" x-ref="b{{ $i }}"
                               @input="if ($event.target.value && {{ $i }} < 5) $refs['b{{ $i + 1 }}'].focus()"
                               @keydown.backspace="if (!d[{{ $i }}] && {{ $i }} > 0) $refs['b{{ $i - 1 }}'].focus()"
                               class="size-[44px] sm:size-[52px] rounded-[12px] bg-surface text-center font-heading text-[22px] sm:text-[25px] text-text border border-transparent focus:border-primary focus:bg-primary/[0.16] outline-none transition">
                    @endforeach
                </div>

                <p class="font-sans text-[12px] text-muted text-center">
                    Didn't receive the code?
                    <button type="button" wire:click="resend" class="text-primary">Resend</button>
                </p>

                @error('code')<span class="font-sans text-[12px] text-danger text-center">{{ $message }}</span>@enderror
            </div>

            <x-auth.button target="verify">Verify</x-auth.button>
        </form>
    </x-auth.plain>

    <x-auth.success-modal on="email-verified" action="continueOnboarding" title="Verified" message="Your email has been verified" />
</div>
