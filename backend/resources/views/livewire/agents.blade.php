<div>
    <x-public.page-hero badge="Distributors" title="Our<br>Reliable Distributors" breadcrumb="Distributors" />

    <section class="bg-surface py-[40px] md:py-[70px]">
        <div class="max-w-[1280px] mx-auto px-5 md:px-[60px]">
            <div class="flex items-center justify-center gap-2.5 mb-[34px] md:mb-[44px]">
                @foreach (['nigeria' => ['Nigeria', $nigeriaCount], 'foreign' => ['Foreign', $foreignCount]] as $key => [$label, $count])
                    <button type="button" wire:click="selectTab('{{ $key }}')"
                            class="inline-flex items-center gap-2 h-[42px] px-5 rounded-full font-heading text-[15px] transition {{ $tab === $key ? 'bg-primary text-surface' : 'bg-background text-muted hover:text-text' }}">
                        {{ $label }}
                        <span class="inline-flex items-center justify-center min-w-[22px] h-[22px] px-1.5 rounded-full text-[12px] {{ $tab === $key ? 'bg-surface/20 text-surface' : 'bg-surface text-muted' }}">{{ $count }}</span>
                    </button>
                @endforeach
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-[25px] gap-y-[34px]">
                @forelse ($this->agents() as $agent)
                    <div class="flex flex-col items-center gap-2.5">
                        <img src="{{ $agent['avatar'] ?: asset('images/top-earners/earner.jpg') }}" alt="{{ $agent['username'] }}"
                             class="w-full max-w-[150px] aspect-square rounded-[15px] object-cover object-top">
                        <div class="flex flex-col items-center gap-2">
                            <p class="font-heading font-bold text-text text-[18px] tracking-tight">{{ $agent['username'] }}</p>
                            @php $contactPrefill = 'Good day '.$agent['username'].'. I would like to purchase a Nextel eSIM activation code. Please let me know if you are available to assist me.'; @endphp
                            <div class="flex items-center gap-3">
                                @if ($agent['telegram'])
                                    <a href="{{ $agent['telegram'].(str_contains($agent['telegram'], '?') ? '&' : '?').'text='.rawurlencode($contactPrefill) }}" target="_blank" rel="noopener" aria-label="Telegram {{ $agent['username'] }}"
                                       class="size-11 rounded-full bg-background flex items-center justify-center text-[#229ED9] hover:bg-[#229ED9] hover:text-surface transition">
                                        <i class="ri-telegram-2-fill text-[22px]"></i>
                                    </a>
                                @endif
                                <a href="https://wa.me/{{ $agent['whatsapp'] }}?text={{ rawurlencode($contactPrefill) }}" target="_blank" rel="noopener" aria-label="WhatsApp {{ $agent['username'] }}"
                                   class="size-11 rounded-full bg-background flex items-center justify-center text-[#25D366] hover:bg-[#25D366] hover:text-surface transition">
                                    <i class="ri-whatsapp-line text-[21px]"></i>
                                </a>
                                @if (config('settings.chat.enabled'))
                                    <a href="{{ route('chat.purchase', ['vendor' => $agent['id'], 'message' => $contactPrefill]) }}" aria-label="Nextel chat with {{ $agent['username'] }}"
                                       class="size-11 rounded-full bg-background flex items-center justify-center hover:bg-primary/10 transition">
                                        <img src="{{ asset('favicon-192x192.png') }}" alt="Nextel chat" class="size-6 object-contain">
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="col-span-full text-center font-sans text-muted">No {{ $tab === 'foreign' ? 'foreign' : 'Nigerian' }} distributors available right now.</p>
                @endforelse
            </div>
        </div>
    </section>
</div>
