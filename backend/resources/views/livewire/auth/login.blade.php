<div>
    <x-auth.shell title="Welcome Back">
        <form wire:submit="authenticate" class="flex flex-col gap-[30px] w-full">
            @if (session('status'))
                <p class="font-sans text-[13px] text-success text-center">{{ session('status') }}</p>
            @endif
            <div class="flex flex-col gap-2.5 w-full">
                <x-auth.input label="Email or username" type="text" name="login" wire:model="login" placeholder="Email or username" autocomplete="username">
                    <x-slot:leading><x-auth.icon name="user" /></x-slot:leading>
                    <x-slot:trailing>
                        <span class="opacity-0 transition group-has-[input:not(:placeholder-shown)]:opacity-100"><x-auth.icon name="check" /></span>
                    </x-slot:trailing>
                </x-auth.input>

                <div x-data="{ show: false }">
                    <x-auth.input label="Password" type="password" name="password" wire:model="password" placeholder="***********" autocomplete="current-password" x-bind:type="show ? 'text' : 'password'">
                        <x-slot:leading><x-auth.icon name="lock" /></x-slot:leading>
                        <x-slot:trailing>
                            <button type="button" @click="show = !show" class="text-muted" aria-label="Toggle password visibility">
                                <svg x-show="!show" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/><path fill-rule="evenodd" d="M.664 10.59a1.65 1.65 0 010-1.186A10.004 10.004 0 0110 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0110 17c-4.257 0-7.893-2.66-9.336-6.41zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                                <svg x-show="show" x-cloak viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path d="M3.28 2.22a.75.75 0 00-1.06 1.06l14.5 14.5a.75.75 0 101.06-1.06l-1.745-1.745a10.029 10.029 0 003.3-4.38 1.651 1.651 0 000-1.185A10.004 10.004 0 009.999 3a9.956 9.956 0 00-4.744 1.194L3.28 2.22zM7.752 6.69l1.092 1.092a2.5 2.5 0 013.374 3.373l1.091 1.092a4 4 0 00-5.557-5.557z"/><path d="M10.748 13.93l2.523 2.523a9.987 9.987 0 01-3.27.547c-4.258 0-7.894-2.66-9.337-6.41a1.651 1.651 0 010-1.186A10.007 10.007 0 012.839 6.02L6.07 9.252a4 4 0 004.678 4.678z"/></svg>
                            </button>
                        </x-slot:trailing>
                    </x-auth.input>
                </div>

                <div class="flex items-center justify-between w-full">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" wire:model="remember" class="size-4 rounded-[4px] border-muted text-primary focus:ring-1 focus:ring-primary focus:ring-offset-0">
                        <span class="font-sans text-[12px] text-muted">Remember Me</span>
                    </label>
                    <a href="https://t.me/NextelHQ2" target="_blank" rel="noopener noreferrer" class="font-sans text-[12px] text-primary">Forgot Password ?</a>
                </div>
            </div>

            <div class="flex flex-col gap-[30px] w-full">
                <x-auth.button target="authenticate">Login</x-auth.button>

                <div class="flex flex-wrap items-center justify-center gap-2.5 font-sans text-[14px] w-full">
                    <span class="text-muted">Don't have an account?</span>
                    <a href="{{ route('auth.register') }}" wire:navigate class="text-primary">Sign up</a>
                </div>
            </div>
        </form>
    </x-auth.shell>
</div>
