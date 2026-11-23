{{-- Sets the display currency for every monetary figure in the app; persisted on the user. --}}
<div>
    @if ($hasLocal)
        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
            <button type="button" @click.stop="open = !open" wire:loading.attr="disabled"
                    class="flex items-center gap-1 rounded-full bg-surface shadow-sm px-2.5 h-[28px] font-sans text-[12px] font-medium text-text hover:text-primary transition disabled:opacity-60"
                    aria-label="Switch display currency">
                <span>{{ $active }}</span>
                <i class="ri-arrow-down-s-line text-[15px]" :class="open && 'rotate-180'"></i>
            </button>

            <div x-show="open" x-cloak x-transition.opacity.duration.150ms
                 class="absolute right-0 top-[34px] z-20 w-[120px] rounded-[12px] bg-surface shadow-[0_8px_24px_rgba(0,0,0,0.12)] border border-muted/10 p-1 flex flex-col">
                @foreach (['USD', $localCode] as $code)
                    <button type="button" wire:click="switchTo('{{ $code }}')" @click="open = false"
                            class="flex items-center justify-between rounded-[8px] px-2.5 py-1.5 font-sans text-[13px] hover:bg-background transition {{ $active === $code ? 'text-primary font-semibold' : 'text-text' }}">
                        {{ $code }}
                        @if ($active === $code)<i class="ri-check-line text-[15px]"></i>@endif
                    </button>
                @endforeach
            </div>
        </div>
    @endif
</div>
