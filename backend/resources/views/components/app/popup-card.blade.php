{{--
    The promotional popup card itself (see App\Models\Popup).

    Every piece is optional: the badge only appears with a title, the headline with a heading, the
    buttons only with a label *and* a URL, and the media panel falls back to the default gradient
    when there is no header image. The same component draws the dashboard dialog and the
    backoffice live preview, so what an admin previews is what users get.

    Props are kept flat so the preview can feed it form state rather than a saved model.

    Usage: <x-app.popup-card :title="$popup->title" :heading="$popup->heading" :body="$popup->body" … />
--}}
@props([
    'title' => null,
    'heading' => null,
    'body' => null,
    'headerUrl' => null,
    'backgroundUrl' => null,
    'caption' => null,
    'primaryLabel' => null,
    'primaryUrl' => null,
    'secondaryLabel' => null,
    'secondaryUrl' => null,
    'footer' => null,
])

@php
    // The media panel is the header image, and nothing else: with no image the whole panel —
    // and the caption that sits on it — is left out of the dialog, and the text takes the full
    // width. The gradient (Popup::PANEL_STYLE) remains the backoffice's stand-in for "no image".
    $hasImage = filled($headerUrl);

    // Absolute links leave the app (new tab / Telegram's own browser); in-app paths navigate in place.
    $isExternal = fn (?string $url): bool => (bool) $url && \Illuminate\Support\Str::startsWith($url, ['http://', 'https://', '//']);
@endphp

<div {{ $attributes->merge(['class' => 'w-full overflow-hidden rounded-[26px] bg-surface shadow-[0_24px_70px_rgba(0,0,0,0.28)] '.($hasImage ? 'sm:flex' : '')]) }}>
    {{-- Text column --}}
    <div class="relative order-2 sm:order-1 {{ $hasImage ? 'sm:w-[55%]' : 'w-full' }} flex flex-col justify-center gap-4 px-6 py-7 sm:px-11 sm:py-12">
        @if ($backgroundUrl)
            <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('{{ $backgroundUrl }}')"></div>
            <div class="absolute inset-0 bg-surface/90"></div>
        @endif

        <div class="relative flex flex-col gap-4">
            @if ($title)
                <span class="self-start rounded-full bg-secondary/45 px-3.5 py-1.5 font-sans font-medium text-primary text-[12px]">{{ $title }}</span>
            @endif

            @if ($heading)
                <h2 class="font-heading font-bold text-text text-[30px] sm:text-[42px] leading-[1.06] tracking-[-0.02em]">{{ $heading }}</h2>
            @endif

            <p class="font-sans text-muted text-[14px] sm:text-[15px] leading-relaxed">{{ $body }}</p>

            @if ($primaryLabel && $primaryUrl)
                <a href="{{ $primaryUrl }}"
                   @if ($isExternal($primaryUrl)) target="_blank" rel="noopener noreferrer" @endif
                   class="mt-1 inline-flex w-fit items-center gap-2 rounded-full bg-primary px-7 h-[52px] font-heading font-semibold text-white text-[14px] shadow-[0_10px_24px_rgba(24,68,62,0.22)] transition hover:bg-primary-light">
                    {{ $primaryLabel }} <i class="ri-arrow-right-up-line text-[17px]"></i>
                </a>
            @endif

            @if ($secondaryLabel && $secondaryUrl)
                <a href="{{ $secondaryUrl }}"
                   @if ($isExternal($secondaryUrl)) target="_blank" rel="noopener noreferrer" @endif
                   class="w-fit font-sans font-medium text-primary text-[14px] underline underline-offset-[6px] decoration-primary/40 hover:decoration-primary">
                    {{ $secondaryLabel }}
                </a>
            @endif

            @if ($footer)
                <p class="font-sans text-muted text-[12px] mt-1">{{ $footer }}</p>
            @endif
        </div>
    </div>

    @if ($hasImage)
        {{-- Media column: present only when there is a header image to put in it. --}}
        <div class="relative order-1 sm:order-2 sm:w-[45%] min-h-[200px] sm:min-h-[420px] bg-background">
            <img src="{{ $headerUrl }}" alt="" class="absolute inset-0 size-full object-cover">

            @if ($caption)
                <span class="absolute bottom-5 left-1/2 max-w-[86%] -translate-x-1/2 truncate rounded-full bg-white/85 px-4 py-2 font-sans font-medium text-primary text-[12px] shadow-sm backdrop-blur-sm">{{ $caption }}</span>
            @endif
        </div>
    @endif
</div>