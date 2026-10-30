@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex items-center justify-center gap-1.5 flex-wrap">
        @if ($paginator->onFirstPage())
            <span class="size-10 rounded-full bg-surface/50 text-muted/40 flex items-center justify-center cursor-not-allowed"><i class="ri-arrow-left-s-line text-[18px]"></i></span>
        @else
            <button type="button" wire:click="previousPage" wire:loading.attr="disabled" aria-label="Previous"
                    class="size-10 rounded-full bg-surface text-text shadow-[0_4px_9px_rgba(194,194,194,0.12)] hover:bg-surface/80 transition flex items-center justify-center"><i class="ri-arrow-left-s-line text-[18px]"></i></button>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-1.5 font-sans text-muted/70 text-[13px]">{{ $element }}</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page" class="min-w-10 h-10 px-3 rounded-full bg-primary text-surface font-sans text-[13px] flex items-center justify-center">{{ $page }}</span>
                    @else
                        <button type="button" wire:click="gotoPage({{ $page }})" wire:loading.attr="disabled"
                                class="min-w-10 h-10 px-3 rounded-full bg-surface text-text font-sans text-[13px] shadow-[0_4px_9px_rgba(194,194,194,0.12)] hover:bg-surface/80 transition flex items-center justify-center">{{ $page }}</button>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <button type="button" wire:click="nextPage" wire:loading.attr="disabled" aria-label="Next"
                    class="size-10 rounded-full bg-surface text-text shadow-[0_4px_9px_rgba(194,194,194,0.12)] hover:bg-surface/80 transition flex items-center justify-center"><i class="ri-arrow-right-s-line text-[18px]"></i></button>
        @else
            <span class="size-10 rounded-full bg-surface/50 text-muted/40 flex items-center justify-center cursor-not-allowed"><i class="ri-arrow-right-s-line text-[18px]"></i></span>
        @endif
    </nav>
@endif
