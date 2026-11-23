{{--
    The dashboard popup dialog.

    Each popup is keyed by its id and carries its own enter transition, so when the visitor
    dismisses one and the next slides in, Livewire swaps the node and Alpine replays the slide.
    Text and images come from the shared x-app.popup-card, which drops whatever is blank.
--}}
<div>
    @if ($popup)
        <div data-lock-scroll class="fixed inset-0 z-[92] flex items-center justify-center sm:items-center sm:p-6">
            <div class="absolute inset-0 bg-black/55 backdrop-blur-[3px]"
                 x-data="{ shown: false }" x-init="$nextTick(() => shown = true)" x-cloak x-show="shown"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"></div>

            <div wire:key="popup-{{ $popup->id }}" class="relative w-full sm:max-w-[900px] max-h-[94vh] overflow-y-auto p-[10px]"
                 x-data="{ shown: false }" x-init="$nextTick(() => shown = true)" x-cloak x-show="shown"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-12 sm:translate-y-4 sm:scale-[0.97]"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">

                <x-app.popup-card
                    :title="$popup->title"
                    :heading="$popup->heading"
                    :body="$popup->body"
                    :header-url="$popup->header_image_url"
                    :background-url="$popup->background_image_url"
                    :caption="$popup->image_caption"
                    :primary-label="$popup->primary_label"
                    :primary-url="$popup->primary_url"
                    :secondary-label="$popup->secondary_label"
                    :secondary-url="$popup->secondary_url"
                    :footer="$popup->footer_text" />

                <button type="button" wire:click="dismiss" aria-label="Close"
                        class="absolute right-4 top-4 z-20 flex size-10 items-center justify-center rounded-full border border-black/10 bg-white/90 text-primary shadow-sm backdrop-blur transition hover:bg-white">
                    <i class="ri-close-line text-[20px]"></i>
                </button>
            </div>

            @if ($queued > 1)
                {{-- Several popups queued: say so, so the next one sliding in is expected. --}}
                <p class="pointer-events-none absolute bottom-3 left-1/2 -translate-x-1/2 rounded-full bg-black/35 px-3 py-1 font-sans text-white/85 text-[11px] backdrop-blur-sm sm:bottom-5">
                    {{ $queued - $following }} of {{ $queued }}
                </p>
            @endif
        </div>
    @endif
</div>
