<div class="w-full max-w-[420px]">
    <div class="flex flex-col items-center gap-3 mb-7">
        <span class="inline-flex items-center bg-surface rounded-2xl px-4 py-2.5">
            <img src="{{ asset('images/logo-long.png') }}" alt="TrustLens Store" class="h-[30px] w-auto object-contain">
        </span>
        <span class="font-heading font-medium text-secondary text-[12px] uppercase tracking-[0.18em]">Backoffice</span>
    </div>

    <div class="bg-surface rounded-[24px] px-6 md:px-8 py-8 shadow-[0_20px_60px_rgba(0,0,0,0.3)]">
        <div class="flex flex-col gap-1.5 mb-6">
            <h1 class="font-heading font-semibold text-text text-[22px] leading-tight">Welcome back</h1>
            <p class="font-sans text-muted text-[13px]">Sign in to continue to the {{ config('backoffice.name') }}.</p>
        </div>

        <form wire:submit="login" class="flex flex-col gap-4">
            <div class="flex flex-col gap-1.5">
                <label for="email" class="label">Email address</label>
                <input id="email" type="email" wire:model="email" autocomplete="username" autofocus
                       class="w-full h-[50px] rounded-[14px] bg-background border border-muted/15 px-4 font-sans text-[14px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                @error('email')
                    <span class="error">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="password" class="label">Password</label>
                <input id="password" type="password" wire:model="password" autocomplete="current-password"
                       class="w-full h-[50px] rounded-[14px] bg-background border border-muted/15 px-4 font-sans text-[14px] text-text focus:outline-none focus:ring-2 focus:ring-primary/40">
                @error('password')
                    <span class="error">{{ $message }}</span>
                @enderror
            </div>

            <label class="flex items-center gap-2 font-sans text-[13px] text-muted">
                <input type="checkbox" wire:model="remember" class="rounded border-muted/30 text-primary focus:ring-primary/40">
                Keep me signed in
            </label>

            <button type="submit"
                    class="h-[50px] rounded-[14px] bg-primary text-white font-sans font-medium text-[15px] hover:opacity-95 transition disabled:opacity-70">
                <span wire:loading.remove wire:target="login">Sign in</span>
                <span wire:loading wire:target="login">Signing in…</span>
            </button>
        </form>
    </div>

    <p class="text-center font-sans text-white/50 text-[12px] mt-6">{{ config('backoffice.name') }} · Authorized personnel only</p>
</div>
