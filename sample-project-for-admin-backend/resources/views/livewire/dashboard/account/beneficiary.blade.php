<div class="min-h-screen bg-background">
    <div class="mx-auto w-full max-w-[440px] md:max-w-[600px] lg:max-w-none px-4 md:px-8 lg:px-10 pb-[120px] lg:pb-12 flex flex-col gap-[22px]">

        <x-app.app-bar :title="'Beneficiaries'" />

        <p class="font-sans text-muted text-[14px] leading-snug">
            @if ($numbers->isNotEmpty())
                These are your assigned numbers. You can now start earning from calls.
            @else
                You don't have any assigned numbers yet. Activate a package to get your Call IDs.
            @endif
        </p>

        <div class="flex flex-col gap-3 lg:grid lg:grid-cols-2 xl:grid-cols-3 lg:gap-x-5 lg:gap-y-3">
            @foreach ($numbers as $number)
                <div class="bg-surface rounded-[29px] flex items-center gap-2.5 px-[15px] py-2.5 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
                    <img src="{{ $number->logo }}" alt="{{ $number->network }}" class="size-[45px] rounded-full object-cover shrink-0">

                    <div class="flex-1 min-w-0 flex flex-col gap-1.5">
                        <p class="font-sans text-text text-[14px] truncate">{{ $number->masked }}</p>
                        <div class="flex items-center gap-2">
                            <span class="font-sans text-muted text-[11px]">{{ $number->network }}</span>
                            <span class="flex items-center gap-[5px]">
                                <span class="size-2 rounded-full bg-success shrink-0"></span>
                                <span class="font-sans text-muted text-[12px]">Active</span>
                            </span>
                            @if ($number->source === 'additional')
                                <span class="font-sans text-[10px] text-primary bg-primary/10 rounded-full px-1.5 py-0.5">Bonus</span>
                            @endif
                        </div>
                    </div>
                    {{-- Call button disabled for now; restore when ready.
                    <a href="{{ route('call.preview', ['n' => $number->id]) }}" wire:navigate
                       class="size-10 rounded-full bg-background flex items-center justify-center text-primary shrink-0" aria-label="Call {{ $number->masked }}">
                        <i class="ri-phone-line text-[18px]"></i>
                    </a> --}}
                </div>
            @endforeach
        </div>

        {{-- Want more numbers (Figma 381:2903) --}}
        <div class="bg-surface rounded-[20px] overflow-hidden flex flex-col lg:flex-row items-center lg:justify-between gap-2.5 lg:gap-8 px-[15px] lg:px-9 py-5 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)]">
            <div class="order-1 lg:order-none flex flex-col items-center lg:items-start gap-2.5 lg:flex-1">
                <div class="flex flex-col items-center lg:items-start gap-[5px] text-center lg:text-left">
                    <p class="font-heading font-medium text-text text-[16px]">Want More Numbers?</p>
                    <p class="font-sans text-muted text-[12px] leading-snug lg:max-w-[320px]">Purchase a package to get more assigned numbers and earn more.</p>
                </div>
                <a href="{{ route('plans') }}" wire:navigate class="inline-flex items-center justify-center h-[35px] px-5 rounded-[35px] bg-primary text-surface font-sans text-[14px] hover:opacity-95 transition">
                    View Packages
                </a>
            </div>
            <img src="{{ asset('images/want-more-numbers.svg') }}" alt="" class="order-2 lg:order-none w-[179px] h-[95px] lg:w-[210px] lg:h-[111px] shrink-0">
        </div>
    </div>

    <x-app.bottom-nav />
</div>
