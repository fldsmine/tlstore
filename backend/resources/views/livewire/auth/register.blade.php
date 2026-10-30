<div>
    <x-auth.shell title="Create Account">
        <form wire:submit="register" class="flex flex-col gap-5 w-full">
            <div class="flex flex-col gap-2.5 w-full">
                <x-auth.input label="Full Name" name="fullName" wire:model="fullName" placeholder="Your Name" autocomplete="name">
                    <x-slot:leading><x-auth.icon name="user" /></x-slot:leading>
                </x-auth.input>

                <x-auth.input label="Email" type="email" name="email" wire:model="email" placeholder="Enter your email address" autocomplete="email">
                    <x-slot:leading><x-auth.icon name="mail" /></x-slot:leading>
                    <x-slot:trailing>
                        <span class="opacity-0 transition group-has-[input:not(:placeholder-shown)]:opacity-100"><x-auth.icon name="check" /></span>
                    </x-slot:trailing>
                </x-auth.input>

                <x-auth.input label="Username" name="username" wire:model="username" placeholder="Choose your preferred username" autocomplete="username">
                    <x-slot:leading><i class="ri-at-line text-[15px] leading-none"></i></x-slot:leading>
                </x-auth.input>

                <x-auth.input label="Phone Number" type="tel" name="phone" wire:model="phone" placeholder="Enter your phone number" autocomplete="tel">
                    <x-slot:leading><x-auth.icon name="phone" /></x-slot:leading>
                </x-auth.input>

                {{-- Country drives currency, code-class & onboarding eligibility. --}}
                <div class="flex flex-col gap-1.5 w-full">
                    <label class="font-sans text-[14px] text-text">Country</label>
                    <div class="group flex items-center gap-2.5 h-[45px] px-[15px] rounded-[30px] border-[0.5px] border-primary focus-within:ring-1 focus-within:ring-primary transition">
                        <span class="shrink-0 text-muted flex items-center"><i class="ri-global-line text-[15px] leading-none"></i></span>
                        <select wire:model="country" class="flex-1 min-w-0 bg-transparent outline-none border-0 p-0 font-sans text-[13px] text-text focus:ring-0 appearance-none cursor-pointer">
                            <option value="">Select your country</option>
                            @foreach ($countries as $c)
                                <option value="{{ $c->code }}">{{ $c->flag }} {{ $c->name }}</option>
                            @endforeach
                        </select>
                        <span class="shrink-0 text-muted flex items-center"><i class="ri-arrow-down-s-line text-[16px] leading-none"></i></span>
                    </div>
                    @error('country')<span class="font-sans text-[12px] text-danger px-2">{{ $message }}</span>@enderror
                </div>

                <x-auth.input label="Promo code" name="referralCode" wire:model="referralCode" placeholder="Enter promo code (optional)" :readonly="$referralLocked">
                    <x-slot:leading><x-auth.icon name="ticket" /></x-slot:leading>
                </x-auth.input>

                <div x-data="{ show: false }">
                    <x-auth.input label="Password" type="password" name="password" wire:model="password" placeholder="***********" autocomplete="new-password" x-bind:type="show ? 'text' : 'password'">
                        <x-slot:leading><x-auth.icon name="lock" /></x-slot:leading>
                        <x-slot:trailing>
                            <button type="button" @click="show = !show" class="text-muted" aria-label="Toggle password visibility">
                                <svg x-show="!show" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/><path fill-rule="evenodd" d="M.664 10.59a1.65 1.65 0 010-1.186A10.004 10.004 0 0110 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0110 17c-4.257 0-7.893-2.66-9.336-6.41zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                                <svg x-show="show" x-cloak viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path d="M3.28 2.22a.75.75 0 00-1.06 1.06l14.5 14.5a.75.75 0 101.06-1.06l-1.745-1.745a10.029 10.029 0 003.3-4.38 1.651 1.651 0 000-1.185A10.004 10.004 0 009.999 3a9.956 9.956 0 00-4.744 1.194L3.28 2.22zM7.752 6.69l1.092 1.092a2.5 2.5 0 013.374 3.373l1.091 1.092a4 4 0 00-5.557-5.557z"/><path d="M10.748 13.93l2.523 2.523a9.987 9.987 0 01-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 010-1.186A10.007 10.007 0 012.839 6.02L6.07 9.252a4 4 0 004.678 4.678z"/></svg>
                            </button>
                        </x-slot:trailing>
                    </x-auth.input>
                </div>

            </div>

            <label class="flex items-start gap-2.5 font-sans text-[13px] text-muted w-full">
                <input type="checkbox" wire:model="agreeTerms" class="mt-0.5 size-4 shrink-0 rounded border-primary/40 text-primary focus:ring-primary/30">
                <span>I agree to the <a href="{{ route('terms') }}" target="_blank" class="text-primary">Terms &amp; Conditions</a> and <a href="{{ route('privacy') }}" target="_blank" class="text-primary">Privacy Policy</a>.</span>
            </label>
            @error('agreeTerms')<span class="font-sans text-[12px] text-danger px-2 -mt-2">{{ $message }}</span>@enderror

            <x-auth.button target="register">Sign up</x-auth.button>
        </form>

        <div class="flex flex-wrap items-center justify-center gap-2.5 font-sans text-[14px] w-full">
            <span class="text-muted">Already have an account?</span>
            <a href="{{ route('auth.login') }}" wire:navigate class="text-primary">Sign in</a>
        </div>
    </x-auth.shell>
</div>
