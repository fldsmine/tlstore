<x-layouts.plain title="Human verification preview">
    <main class="flex min-h-screen w-full items-center justify-center bg-white px-4 py-10 sm:px-6">
        <div class="w-full max-w-[680px]">
            <header class="mb-8 text-center">
                <div class="mx-auto flex w-fit items-center gap-2.5 text-primary">
                    <span class="flex size-10 items-center justify-center rounded-[13px] bg-primary text-white shadow-sm">
                        <i class="ri-shield-check-line text-[22px]" aria-hidden="true"></i>
                    </span>
                    <span class="font-heading text-[16px] font-bold tracking-[0.16em]">NEXTEL</span>
                </div>
                <p class="mt-6 font-sans text-[11px] font-semibold uppercase tracking-[0.16em] text-primary">Interface preview</p>
                <h1 class="mt-2 font-heading text-[25px] font-semibold text-[#171717] sm:text-[30px]">Human verification</h1>
                <p class="mx-auto mt-2 max-w-[480px] font-sans text-[13px] leading-6 text-[#666]">
                    A brand-neutral visual based on the supplied reference. This preview does not verify visitors or restrict access.
                </p>
            </header>

            <div class="space-y-8">
                <section aria-label="Unchecked verification state" class="overflow-hidden rounded-[5px] border border-[#d7d7d7] bg-[#fafafa] shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
                    <div class="flex min-h-[128px] items-center justify-between gap-4 px-4 py-5 sm:px-6">
                        <div class="flex min-w-0 items-center gap-4 sm:gap-5">
                            <span aria-hidden="true" class="size-12 shrink-0 rounded-[6px] border-[4px] border-[#565b59] bg-white"></span>
                            <span class="font-sans text-[20px] leading-tight text-[#171717] sm:text-[25px]">Verify you are human</span>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-2.5">
                            <div class="flex items-center gap-2 text-[#202523]">
                                <span class="flex size-8 items-center justify-center rounded-[10px] bg-primary/10 text-primary">
                                    <i class="ri-shield-check-line text-[20px]" aria-hidden="true"></i>
                                </span>
                                <span class="font-heading text-[12px] font-bold tracking-[0.16em] sm:text-[13px]">NEXTEL</span>
                            </div>
                            <nav aria-label="Verification links" class="flex items-center gap-1.5 font-sans text-[11px] text-[#333] sm:text-[12px]">
                                <a href="{{ route('privacy') }}" class="underline underline-offset-2">Privacy</a>
                                <span aria-hidden="true">·</span>
                                <a href="{{ route('contact') }}" class="underline underline-offset-2">Help</a>
                            </nav>
                        </div>
                    </div>
                </section>

                <section aria-label="Verification in progress state" class="overflow-hidden rounded-[5px] border border-[#d7d7d7] bg-[#fafafa] shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
                    <div class="flex min-h-[128px] items-center justify-between gap-4 px-4 py-5 sm:px-6">
                        <div class="flex min-w-0 items-center gap-4 sm:gap-5">
                            <span aria-hidden="true" class="relative size-12 shrink-0 animate-spin rounded-full border-[5px] border-[#d8e8dc] border-t-[#27884a]"></span>
                            <span class="font-sans text-[20px] leading-tight text-[#171717] sm:text-[25px]">Verifying…</span>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-2.5">
                            <div class="flex items-center gap-2 text-[#202523]">
                                <span class="flex size-8 items-center justify-center rounded-[10px] bg-primary/10 text-primary">
                                    <i class="ri-shield-check-line text-[20px]" aria-hidden="true"></i>
                                </span>
                                <span class="font-heading text-[12px] font-bold tracking-[0.16em] sm:text-[13px]">NEXTEL</span>
                            </div>
                            <nav aria-label="Verification links" class="flex items-center gap-1.5 font-sans text-[11px] text-[#333] sm:text-[12px]">
                                <a href="{{ route('privacy') }}" class="underline underline-offset-2">Privacy</a>
                                <span aria-hidden="true">·</span>
                                <a href="{{ route('contact') }}" class="underline underline-offset-2">Help</a>
                            </nav>
                        </div>
                    </div>
                </section>
            </div>

            <p class="mt-7 text-center font-sans text-[11px] leading-5 text-[#777]">
                Static design mockup only. No checkbox, challenge, data collection, or security decision is active on this page.
            </p>
        </div>
    </main>
</x-layouts.plain>
