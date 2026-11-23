{{-- Prices shown in $ USD (base currency). Packages are loaded live from the DB. --}}
@php
    $packages = \App\Models\Package::query()->where('is_active', true)->orderBy('tier_level')->get();
@endphp

@if ($packages->isNotEmpty())
    <section id="packages" class="hidden md:block bg-background pt-[100px] pb-[80px] max-w-[1280px] mx-auto px-[60px]">

        <div class="flex flex-col gap-[30px] mb-[60px]">
            <h2 class="font-heading font-semibold text-primary text-[50px]">Choose Your Earning Package</h2>
            <p class="font-sans text-muted text-[24px] leading-normal">Higher package give you more minutes, more contacts, and higher earning potential. Start small or go big, the choice is yours!</p>
        </div>

        <div class="grid grid-cols-2 gap-10">
            @foreach ($packages as $package)
                @php $featured = (bool) $package->is_featured; @endphp
                <div wire:key="pkg-{{ $package->id }}"
                     class="bg-surface rounded-[60px] px-[40px] py-[60px] flex flex-col gap-[40px] border-[3px] {{ $featured ? 'border-[#48948a]' : 'border-[#E9E9E9]' }}"
                     @if ($featured) style="box-shadow: 0px 0px 24px 1px rgba(40,157,141,0.2);" @endif>
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex flex-col gap-2.5">
                            <p class="font-heading text-[24px] text-muted">{{ $package->name }}</p>
                            <p class="font-heading font-semibold text-[38px] text-text">{{ money((float) $package->price) }}</p>
                        </div>
                        @if ($featured)
                            <span class="bg-primary text-surface text-xs font-medium font-heading p-[10px] rounded-[35px] flex-shrink-0">Popular</span>
                        @endif
                    </div>

                    @if ($package->description)
                        <p class="font-sans text-muted text-[20px] leading-relaxed">{{ $package->description }}</p>
                    @endif

                    <a href="{{ route('auth.register') }}" wire:navigate
                       class="inline-flex items-center justify-center w-full font-heading font-medium text-[24px] rounded-[35px] px-[30px] py-[20px] text-center hover:opacity-90 transition-opacity {{ $featured ? 'text-surface bg-primary' : 'text-primary bg-background' }}">
                        {{ $featured ? 'Upgrade & Earn More' : 'Get Started' }}
                    </a>

                    @if (! empty($package->features))
                        <div class="flex flex-col gap-[30px]">
                            <p class="font-heading font-semibold text-[20px] text-muted">Key Features:</p>
                            <ul class="flex flex-col gap-5">
                                @foreach ($package->features as $feature)
                                    <li class="flex items-center gap-2.5">
                                        <svg class="w-[25px] h-[25px] flex-shrink-0 text-muted" fill="none" viewBox="0 0 24 24">
                                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/>
                                            <path d="M8 12.5l2.4 2.4 5.1-5.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <span class="font-sans text-[20px] text-muted">{{ $feature }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
@endif
