@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex items-center justify-between gap-3 pt-1">
        @if ($paginator->onFirstPage())
            <span class="inline-flex items-center gap-1.5 h-10 px-4 rounded-full bg-surface/50 text-muted/50 font-sans text-[13px] cursor-not-allowed">
                <i class="ri-arrow-left-s-line text-[18px]"></i> Prev
            </span>
        @else
            <button type="button" wire:click="previousPage" wire:loading.attr="disabled" rel="prev"
                    class="inline-flex items-center gap-1.5 h-10 px-4 rounded-full bg-surface text-text font-sans text-[13px] shadow-[0_4px_9px_rgba(194,194,194,0.12)] hover:bg-surface/80 transition">
                <i class="ri-arrow-left-s-line text-[18px]"></i> Prev
            </button>
        @endif

        <span class="font-sans text-muted text-[13px]">
            Page <span class="text-text font-semibold">{{ $paginator->currentPage() }}</span> of {{ $paginator->lastPage() }}
        </span>

        @if ($paginator->hasMorePages())
            <button type="button" wire:click="nextPage" wire:loading.attr="disabled" rel="next"
                    class="inline-flex items-center gap-1.5 h-10 px-4 rounded-full bg-primary text-surface font-sans text-[13px] shadow-[0_4px_9px_rgba(24,68,62,0.18)] hover:opacity-95 transition">
                Next <i class="ri-arrow-right-s-line text-[18px]"></i>
            </button>
        @else
            <span class="inline-flex items-center gap-1.5 h-10 px-4 rounded-full bg-surface/50 text-muted/50 font-sans text-[13px] cursor-not-allowed">
                Next <i class="ri-arrow-right-s-line text-[18px]"></i>
            </span>
        @endif
    </nav>
@endif
