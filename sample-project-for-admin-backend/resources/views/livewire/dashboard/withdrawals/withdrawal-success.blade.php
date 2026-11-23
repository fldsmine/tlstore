<div class="min-h-screen bg-background">
    <div class="mx-auto w-full max-w-[400px] lg:max-w-[560px] px-5 pt-6 pb-12 flex flex-col">

        @if (! $withdrawal)
            <div class="flex flex-col items-center text-center gap-3 mt-16">
                <span class="size-16 rounded-full bg-muted/15 flex items-center justify-center"><i class="ri-search-line text-muted text-[30px]"></i></span>
                <p class="font-heading font-medium text-text text-[20px]">Withdrawal not found</p>
                <p class="font-sans text-muted text-[14px] max-w-[280px]">We couldn't find that withdrawal. Check your transactions for the latest status.</p>
                <a href="{{ route('transactions') }}" wire:navigate class="mt-3 h-[48px] px-6 rounded-full bg-primary text-surface flex items-center justify-center gap-2 font-sans font-semibold text-[14px] hover:opacity-95 transition"><i class="ri-file-list-3-line"></i> View Transactions</a>
            </div>
        @else
            @php
                $headerBg = 'background: radial-gradient(125% 110% at 78% 78%, #2E726699 0%, #18443E 52%, #123A34 100%); background-color: #18443E;';
                $acct = $withdrawal->account_number;
                $maskedAcct = $acct ? '•••• '.substr((string) $acct, -4) : null;
                $narration = data_get($withdrawal->meta, 'narration');
                $rows = array_values(array_filter([
                    ['Method', 'Bank Transfer'],
                    ['Earned via', $withdrawal->wallet_label],
                    ['Account name', $withdrawal->account_name],
                    ['Beneficiary bank', $withdrawal->bank_name],
                    ['Account number', $maskedAcct],
                    ['Date & time', $withdrawal->created_at->format('M jS Y · g:i A')],
                    $fee > 0 ? ['Processing fee', money($fee)] : null,
                    filled($narration) ? ['Narration', $narration] : null,
                ], fn ($r) => $r !== null && filled($r[1])));
                // Temporary signed links: the Telegram Mini App's downloader carries no session
                // cookie, so the signature is what authorises the file for it. ?download=1 is part
                // of the SIGNED parameters — appending anything after signing would invalidate it.
                $receiptUrl = \App\Support\DownloadLinks::signed('withdrawal.receipt', ['reference' => $withdrawal->reference]);
                $receiptDownloadUrl = \App\Support\DownloadLinks::signed('withdrawal.receipt', ['reference' => $withdrawal->reference, 'download' => 1]);
                $receiptName = 'Nextel-Receipt-'.$withdrawal->reference.'.pdf';
            @endphp

            {{-- ===== Receipt ticket ===== --}}
            <div class="relative bg-surface rounded-[26px] shadow-[0_22px_60px_-20px_rgba(18,58,52,0.35)]">

                {{-- brand-green header band --}}
                <div class="rounded-t-[26px] px-6 pt-5 pb-12 text-white" style="{{ $headerBg }}">
                    <div class="flex items-center justify-between">
                        <img src="{{ asset('images/public/logo-coloured.png') }}" alt="{{ config('app.name') }}" class="h-[22px] w-auto object-contain">
                        <span class="rounded-full font-sans font-semibold text-[9px] uppercase tracking-[0.18em] px-2.5 py-1" style="color:#E6B34A; border:1px solid rgba(230,179,74,0.5);">Withdrawal Receipt</span>
                    </div>
                </div>

                {{-- seam: notches + dashed perforation + straddling success check --}}
                <div class="relative h-0 z-10" aria-hidden="true">
                    <div class="absolute left-7 right-7 top-0 -translate-y-1/2 border-t border-dashed border-primary/20"></div>
                    <span class="absolute -left-[11px] top-0 -translate-y-1/2 size-[22px] rounded-full bg-background"></span>
                    <span class="absolute -right-[11px] top-0 -translate-y-1/2 size-[22px] rounded-full bg-background"></span>
                    <span class="absolute left-1/2 top-0 -translate-x-1/2 -translate-y-1/2 size-14 rounded-full bg-success text-white flex items-center justify-center ring-4 ring-surface shadow-[0_12px_26px_-6px_rgba(52,199,89,0.55)]">
                        <i class="ri-check-line text-[30px]"></i>
                    </span>
                </div>

                {{-- body --}}
                <div class="px-6 pt-11 pb-6">

                    {{-- headline --}}
                    <div class="text-center">
                        <h1 class="font-heading font-semibold text-text text-[20px] leading-tight">Withdrawal Complete</h1>
                        <p class="font-sans text-muted text-[12.5px] mt-1">Transaction processed successfully</p>
                        <span class="mx-auto mt-3 block h-px w-10" style="background:linear-gradient(90deg,transparent,#E6B34A,transparent);"></span>
                    </div>

                    {{-- prominent amount --}}
                    <div class="text-center mt-4">
                        <p class="font-sans text-muted text-[10px] font-semibold uppercase tracking-[0.2em]">Amount Received</p>
                        <x-money :usd="$net" :abbreviate="false" class="block font-heading font-semibold text-primary text-[32px] leading-none tracking-tight mt-1.5" />
                    </div>

                    {{-- reference with copy --}}
                    <div x-data="{ copied: false }" class="mt-5 flex items-center justify-between gap-3 rounded-[16px] bg-primary/[0.05] border border-primary/10 pl-4 pr-2.5 py-3 text-left">
                        <div class="min-w-0">
                            <p class="font-sans text-primary/55 text-[9px] font-semibold uppercase tracking-[0.2em]">Reference</p>
                            <p class="font-heading font-semibold text-primary text-[15px] tracking-[0.08em] break-all leading-tight mt-0.5">{{ $withdrawal->reference }}</p>
                        </div>
                        <button type="button"
                                @click="navigator.clipboard.writeText(@js($withdrawal->reference)); copied = true; setTimeout(() => copied = false, 1400)"
                                class="shrink-0 inline-flex items-center gap-1.5 h-8 px-3 rounded-full bg-primary/10 text-primary font-sans font-semibold text-[11.5px] hover:bg-primary/15 transition" aria-label="Copy reference">
                            <i class="text-[13px]" :class="copied ? 'ri-check-line' : 'ri-file-copy-line'"></i>
                            <span x-text="copied ? 'Copied' : 'Copy'"></span>
                        </button>
                    </div>

                    {{-- detail rows --}}
                    <div class="mt-5 divide-y divide-primary/[0.07] text-left">
                        @foreach ($rows as [$label, $value])
                            <div class="flex items-start justify-between gap-4 py-[10px]">
                                <span class="font-sans text-muted text-[12.5px] shrink-0">{{ $label }}</span>
                                <span class="font-heading font-medium text-text text-[13px] text-right leading-snug break-words">{{ $value }}</span>
                            </div>
                        @endforeach
                        <div class="flex items-center justify-between gap-4 py-[10px]">
                            <span class="font-sans text-muted text-[12.5px] shrink-0">Status</span>
                            <span class="font-heading font-medium text-success text-[13px] inline-flex items-center gap-1.5"><span class="size-1.5 rounded-full bg-success"></span>Completed</span>
                        </div>
                    </div>

                    {{-- delivery note --}}
                    <div class="mt-5 flex items-center justify-center gap-1.5 rounded-full bg-success/[0.08] py-2 text-success">
                        <i class="ri-time-line text-[14px]"></i>
                        <span class="font-sans font-medium text-[11.5px]">Funds arrive within 24–48 hours</span>
                    </div>

                    {{-- secured footer --}}
                    <div class="mt-6 pt-4 border-t border-primary/[0.08] text-center">
                        <p class="font-sans font-semibold text-primary text-[11.5px] inline-flex items-center gap-1.5"><i class="ri-shield-check-line text-[14px]" style="color:#E6B34A;"></i> Secured transaction</p>
                        <p class="font-sans text-muted text-[10.5px] mt-1">Keep this receipt for your records · {{ config('app.name') }}</p>
                    </div>
                </div>
            </div>

            {{-- actions --}}
            <div class="flex items-center gap-3 mt-5">
                <x-app.download-link :href="$receiptDownloadUrl"
                                     :filename="$receiptName"
                                     mime="application/pdf"
                                     class="flex-1 h-[48px] rounded-full bg-primary text-surface flex items-center justify-center gap-2 font-sans font-semibold text-[14px] hover:opacity-95 transition shadow-[0_10px_22px_-8px_rgba(24,68,62,0.45)]">
                    <i class="ri-download-2-line text-[17px]"></i> Download
                </x-app.download-link>
                <button type="button" x-data
                        @click="(async () => {
                            try {
                                const url = @js($receiptUrl);
                                const res = await fetch(url);
                                const blob = await res.blob();
                                const file = new File([blob], @js('Nextel-Receipt-'.$withdrawal->reference.'.pdf'), { type: 'application/pdf' });
                                if (navigator.canShare && navigator.canShare({ files: [file] })) {
                                    await navigator.share({ files: [file], title: @js(config('app.name').' receipt') });
                                } else {
                                    window.open(url, '_blank');
                                }
                            } catch (e) { window.open(@js($receiptDownloadUrl), '_blank'); }
                        })()"
                        class="flex-1 h-[48px] rounded-full bg-surface border border-primary/20 text-primary flex items-center justify-center gap-2 font-sans font-semibold text-[14px] hover:bg-primary/5 transition">
                    <i class="ri-share-forward-line text-[17px]"></i> Share
                </button>
            </div>
            <a href="{{ route('transactions') }}" wire:navigate
               class="mt-3 h-[44px] flex items-center justify-center gap-2 font-sans font-medium text-muted text-[13.5px] hover:text-text transition">
                <i class="ri-file-list-3-line text-[16px]"></i> View Transactions
            </a>
        @endif
    </div>
</div>