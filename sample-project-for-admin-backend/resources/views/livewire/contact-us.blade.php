@php
    $phone = trim((string) config('settings.contact.phone'));
    $email = trim((string) config('settings.contact.email'));
    $telegram = trim((string) config('settings.contact.telegram_general'));

    // Only render contact methods that are actually configured (non-empty).
    $details = array_values(array_filter([
        $phone !== '' ? [
            'label' => 'Call us',
            'value' => $phone,
            'href' => 'tel:'.preg_replace('/[^0-9+]/', '', $phone),
            'icon' => 'M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25z',
        ] : null,
        $email !== '' ? [
            'label' => 'Email us',
            'value' => $email,
            'href' => 'mailto:'.$email,
            'icon' => 'M3 8l9 6 9-6M3 8v8a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2z',
        ] : null,
        $telegram !== '' ? [
            'label' => 'Telegram',
            'value' => '@'.basename(rtrim($telegram, '/')),
            'href' => $telegram,
            'icon' => 'M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5',
        ] : null,
    ]));

    // sm:grid-cols-1 sm:grid-cols-2 sm:grid-cols-3 (kept here so Tailwind detects them)
    $gridCols = [1 => 'sm:grid-cols-1', 2 => 'sm:grid-cols-2', 3 => 'sm:grid-cols-3'][min(count($details), 3)] ?? 'sm:grid-cols-1';
@endphp

<div>
    <x-public.page-hero badge="Contact Us" title="Get<br>In Touch" breadcrumb="Contact Us" />

    <x-public.shell class="bg-surface">
        <div class="max-w-[760px] mx-auto flex flex-col gap-8">

            <div class="grid {{ $gridCols }} gap-4 max-w-[820px] mx-auto w-full">
                @foreach ($details as $d)
                    <div class="flex flex-col gap-3 p-5 rounded-2xl bg-background border border-border/60">
                        <span class="size-11 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $d['icon'] }}"/>
                            </svg>
                        </span>
                        <p class="font-sans text-[13px] text-muted">{{ $d['label'] }}</p>
                        @if ($d['href'])
                            <a href="{{ $d['href'] }}" class="font-heading font-medium text-[15px] text-text hover:text-primary transition-colors break-words">{{ $d['value'] }}</a>
                        @else
                            <p class="font-heading font-medium text-[15px] text-text break-words leading-snug">{{ $d['value'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <form wire:submit="submit"
                  class="bg-primary rounded-[20px] md:rounded-[28px] px-5 md:px-12 py-8 md:py-12 flex flex-col gap-6">

                <h2 class="font-heading font-medium text-surface text-[22px] md:text-[28px]">Send us a message</h2>

                @if (session('contact-success'))
                    <p class="font-sans text-[14px] text-secondary">{{ session('contact-success') }}</p>
                @endif

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-[14px] text-surface">Name</label>
                    <input type="text" wire:model="name"
                           class="w-full bg-transparent border border-surface/40 rounded-full px-5 h-[50px] font-sans text-[14px] text-surface placeholder:text-surface/50 focus:outline-none focus:border-secondary">
                    @error('name') <span class="font-sans text-[13px] text-secondary">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-[14px] text-surface">Email</label>
                    <input type="email" wire:model="email"
                           class="w-full bg-transparent border border-surface/40 rounded-full px-5 h-[50px] font-sans text-[14px] text-surface placeholder:text-surface/50 focus:outline-none focus:border-secondary">
                    @error('email') <span class="font-sans text-[13px] text-secondary">{{ $message }}</span> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-[14px] text-surface">How can we help you?</label>
                    <textarea wire:model="message" rows="4"
                              class="w-full bg-transparent border border-surface/40 rounded-[20px] px-5 py-3 font-sans text-[14px] text-surface placeholder:text-surface/50 focus:outline-none focus:border-secondary resize-none"></textarea>
                    @error('message') <span class="font-sans text-[13px] text-secondary">{{ $message }}</span> @enderror
                </div>

                <button type="submit"
                        class="w-full h-[52px] bg-white text-primary rounded-full font-sans font-semibold text-[14px] hover:bg-secondary transition-colors">
                    <span wire:loading.remove wire:target="submit">Send Message</span>
                    <span wire:loading wire:target="submit">Sending…</span>
                </button>
            </form>
        </div>
    </x-public.shell>

    <x-public.cta />
</div>
