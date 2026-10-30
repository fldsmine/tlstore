<div>
    <section class="max-w-[1120px] mx-auto px-5 md:px-8 py-8 md:py-12">
        @php
            $input = 'h-[50px] rounded-[14px] bg-background border border-muted/20 px-4 font-sans text-[14px] text-text placeholder:text-muted/60 focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary/40 w-full';
            $label = 'font-sans text-[12px] text-muted';
        @endphp

        <nav class="flex items-center gap-2.5 font-sans text-[13px] mb-6" aria-label="Breadcrumb">
            <a href="{{ product_index_url() }}" wire:navigate class="text-muted hover:text-primary transition-colors">eSIM Store</a>
            <span class="w-4 h-px bg-muted/40"></span>
            <span class="text-text">{{ $product['name'] }}</span>
        </nav>

        <div class="grid lg:grid-cols-[1.1fr_1fr] gap-6 lg:gap-8 items-start">
            {{-- ── What is being bought ─────────────────────────────────────────────── --}}
            <div class="rounded-[20px] md:rounded-[24px] border border-muted/15 bg-surface/40 p-6 md:p-7 flex flex-col gap-5">
                @if (filled($product['image']))
                    <div class="rounded-[16px] overflow-hidden border border-muted/15 bg-background aspect-[16/9]">
                        <img src="{{ $product['image'] }}" alt="{{ $product['image_alt'] }}" class="size-full object-cover">
                    </div>
                @endif

                <div class="flex flex-col gap-2">
                    <span class="font-sans text-[11px] uppercase tracking-[0.14em] text-muted">{{ $product['kind'] }}</span>
                    <h1 class="font-heading font-semibold text-primary text-[26px] md:text-[32px] leading-tight">{{ $product['name'] }}</h1>
                    @if ($product['description'])
                        <p class="font-sans text-muted text-[13px] leading-relaxed">{{ $product['description'] }}</p>
                    @endif
                </div>

                @if ($product['features'] !== [])
                    <ul class="flex flex-col gap-2">
                        @foreach ($product['features'] as $feature)
                            <li class="flex items-start gap-2 font-sans text-text text-[13px] leading-snug">
                                <i class="ri-check-line text-primary text-[16px] mt-0.5 shrink-0"></i>
                                <span>{{ $feature }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($product['specs'] !== [])
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-2.5 pt-5 border-t border-muted/10">
                        @foreach ($product['specs'] as $spec)
                            <div>
                                <dt class="font-sans text-muted text-[11px]">{{ $spec['label'] }}</dt>
                                <dd class="font-sans text-text text-[13px] tabular-nums">{{ $spec['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>

            {{-- ── Cost details + the three things we need ─────────────────────────── --}}
            <div class="flex flex-col gap-5">
                <div class="rounded-[20px] md:rounded-[24px] border border-primary/25 bg-background p-6 md:p-7">
                    <h2 class="font-heading font-medium text-text text-[16px]">Cost details</h2>

                    <dl class="mt-4 flex flex-col gap-3">
                        <div class="flex items-center justify-between gap-4 font-sans text-[13px]">
                            <dt class="text-muted">{{ $product['name'] }}</dt>
                            <dd class="text-text tabular-nums">{{ $product['formatted_price'] }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 font-sans text-[13px]">
                            <dt class="text-muted">Delivery</dt>
                            <dd class="text-primary">Email — free</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 pt-3 border-t border-muted/15">
                            <dt class="font-heading font-medium text-text text-[15px]">Total to pay</dt>
                            <dd class="font-heading font-semibold text-primary text-[20px] tabular-nums">{{ $product['formatted_price'] }}</dd>
                        </div>
                    </dl>

                    <p class="font-sans text-muted text-[12px] leading-relaxed mt-4">
                        You will be taken to a secure {{ ucfirst($provider) }} payment page to pay this amount by card.
                        Nothing is charged until you complete the payment there.
                    </p>

                    <p class="font-sans text-muted text-[12px] leading-relaxed mt-2">
                        The amount above is what your card will be charged — it is locked the moment you click pay, so a
                        later price change cannot move it.
                    </p>
                </div>

                <div class="rounded-[20px] md:rounded-[24px] border border-muted/15 bg-surface/40 p-6 md:p-7">
                    <h2 class="font-heading font-medium text-text text-[16px]">Your details</h2>
                    <p class="font-sans text-muted text-[12px] leading-relaxed mt-1">
                        We send the {{ $product['name'] }} to the email you enter here — please check it carefully.
                        It is the only place we will send it, and the only way we can reach you about this order.
                    </p>

                    <form method="POST" action="{{ route('product.start', $product['slug']) }}" class="mt-5 flex flex-col gap-4">
                        @csrf

                        <div class="flex flex-col gap-1.5">
                            <label for="full_name" class="{{ $label }}">Full name</label>
                            <input id="full_name" name="full_name" type="text" required autocomplete="name"
                                   value="{{ old('full_name') }}" placeholder="e.g. Chinedu Okeke" class="{{ $input }}">
                            @error('full_name') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label for="phone" class="{{ $label }}">Phone number</label>
                            <input id="phone" name="phone" type="tel" required autocomplete="tel" inputmode="tel"
                                   value="{{ old('phone') }}" placeholder="e.g. 0803 123 4567" class="{{ $input }}">
                            @error('phone') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label for="email" class="{{ $label }}">Email address</label>
                            <input id="email" name="email" type="email" required autocomplete="email" inputmode="email"
                                   value="{{ old('email') }}" placeholder="you@example.com" class="{{ $input }}">
                            @error('email') <span class="font-sans text-[12px] text-danger">{{ $message }}</span> @enderror
                        </div>

                        <button type="submit"
                                class="mt-1 w-full h-[52px] rounded-full bg-primary text-white font-sans font-semibold text-[15px] inline-flex items-center justify-center gap-2 hover:bg-primary/90 transition-colors">
                            <i class="ri-lock-2-line text-[16px]"></i>
                            Pay {{ $product['formatted_price'] }} securely
                        </button>

                        <p class="font-sans text-muted text-[11.5px] leading-relaxed text-center">
                            By continuing you agree to our
                            <a href="{{ product_main_url('terms') }}" class="text-primary hover:underline">terms</a>
                            and
                            <a href="{{ product_main_url('privacy') }}" class="text-primary hover:underline">privacy policy</a>.
                            No account is needed.
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>
