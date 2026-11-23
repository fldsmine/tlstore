{{-- The package doesn't include Nextel Cloud at all. Only $canAccess and $currentPlan exist here. --}}

<div class="w-full">
    <div class="{{ $card }} relative overflow-hidden p-7 lg:p-9">
        <div class="pointer-events-none absolute -top-24 -left-16 size-64 rounded-full opacity-70"
             style="background: radial-gradient(circle, rgba(220,235,106,0.4), rgba(220,235,106,0) 68%);"></div>

        {{-- Mobile: one centred column. Desktop: full-width hero — pitch + CTAs left, features stacked right. --}}
        <div class="relative flex flex-col items-center text-center gap-5 lg:grid lg:grid-cols-5 lg:gap-x-10 lg:gap-y-6 lg:items-start lg:text-left">
            <div class="lg:col-span-3 flex flex-col items-center lg:items-start gap-3.5 max-w-[420px] lg:max-w-none">
                <span class="size-16 rounded-[20px] bg-primary/10 flex items-center justify-center text-primary"><i class="ri-cloud-line text-[30px]"></i></span>

                <p class="font-heading font-semibold text-text text-[21px] lg:text-[27px] lg:leading-tight">Weekend Nextel Cloud isn't on your {{ $currentPlan ?? 'current' }} plan</p>

                <div class="flex flex-col gap-2.5 font-sans text-muted text-[13px] lg:text-[14px] leading-relaxed">
                    <p>Don't let weekends stop your earnings.</p>
                    <p>Your {{ $currentPlan ?? 'current' }} plan doesn't include Weekend Nextel Cloud, meaning you're missing out on potential earnings from sponsored calls and messages.</p>
                    <p>Upgrade to the Royal eSIM plan today and keep earning throughout the weekend — without missing a single opportunity.</p>
                </div>

                <p class="font-heading font-semibold text-text text-[14px] lg:text-[15px]">Upgrade now. Earn more. Make every day count.</p>
            </div>

            <div class="w-full lg:col-span-2 lg:row-span-2 grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-1 gap-2.5">
                @foreach ([
                    ['ri-sim-card-2-line', 'Your own eSIM', 'A virtual Nextel line, issued instantly.'],
                    ['ri-phone-line', 'Sponsored calls', 'Call a sponsor, hear them out, get paid.'],
                    ['ri-mail-line', 'Sponsored inbox', 'Messages that pay you to read them.'],
                ] as [$icon, $title, $copy])
                    <div class="rounded-[16px] bg-background px-4 py-3.5 text-left">
                        <i class="{{ $icon }} text-primary text-[19px]"></i>
                        <p class="font-heading text-text text-[13.5px] mt-1.5">{{ $title }}</p>
                        <p class="font-sans text-muted text-[11.5px] mt-1 leading-relaxed">{{ $copy }}</p>
                    </div>
                @endforeach
            </div>

            <div class="w-full lg:col-span-3 flex flex-col sm:flex-row justify-center lg:justify-start gap-2.5 mt-1 lg:mt-0">
                <button type="button" x-data @click="Livewire.dispatch('open-activation')"
                        class="h-[50px] px-6 rounded-[30px] bg-primary text-surface font-sans font-semibold text-[15px] inline-flex items-center justify-center gap-2 hover:opacity-95 transition">
                    <i class="ri-vip-crown-line text-[18px]"></i> Upgrade your package
                </button>
                <a href="{{ route('agents') }}"
                   class="h-[50px] px-6 rounded-[30px] bg-background text-text font-sans font-semibold text-[15px] inline-flex items-center justify-center gap-2 hover:bg-muted/15 transition">
                    <i class="ri-store-2-line text-[18px]"></i> Find a vendor
                </a>
            </div>
        </div>
    </div>
</div>
