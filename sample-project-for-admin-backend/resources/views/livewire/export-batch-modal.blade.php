<div>
    @if ($open)
        <div data-lock-scroll class="fixed inset-0 z-[90] flex items-end justify-center sm:items-center sm:p-4"
             x-data="{ shown: false }" x-init="$nextTick(() => shown = true)">
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" x-show="shown" x-transition.opacity @click="$wire.close()"></div>

            <div class="relative w-full sm:max-w-[400px] bg-background rounded-t-[26px] sm:rounded-[26px] max-h-[92vh] overflow-y-auto px-6 pt-6 pb-8 flex flex-col gap-5"
                 x-show="shown"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">

                <button type="button" wire:click="close" class="absolute right-5 top-5 size-9 rounded-full bg-surface shadow-sm flex items-center justify-center text-text" aria-label="Close">
                    <i class="ri-close-line text-[20px]"></i>
                </button>

                <div class="flex flex-col gap-1 mt-1">
                    <p class="font-heading font-semibold text-primary text-[22px]">Export Tokens</p>
                    <p class="font-sans text-muted text-[13px] leading-relaxed">Choose a format and which codes to include.</p>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-[13px] text-text font-medium">Format</label>
                    <div class="flex items-center gap-1 bg-surface rounded-[16px] p-1 shadow-sm">
                        @foreach (['txt' => ['Text', 'ri-file-list-line'], 'csv' => ['CSV', 'ri-file-excel-2-line'], 'pdf' => ['PDF', 'ri-file-pdf-2-line']] as $val => [$label, $icon])
                            <button type="button" wire:click="$set('format', '{{ $val }}')"
                                    class="flex-1 h-10 rounded-[12px] font-sans text-[13px] font-semibold transition flex items-center justify-center gap-1.5 {{ $format === $val ? 'bg-primary text-surface' : 'text-muted hover:text-text' }}">
                                <i class="{{ $icon }} text-[15px]"></i> {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="font-sans text-[13px] text-text font-medium">Status</label>
                    <div class="flex items-center gap-1 bg-surface rounded-[16px] p-1 shadow-sm">
                        @foreach (['all' => 'All', 'active' => 'Active', 'used' => 'Used'] as $val => $label)
                            <button type="button" wire:click="$set('scope', '{{ $val }}')"
                                    class="flex-1 h-10 rounded-[12px] font-sans text-[13px] font-semibold transition {{ $scope === $val ? 'bg-primary text-surface' : 'text-muted hover:text-text' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center gap-3 mt-1">
                    <button type="button" wire:click="download" wire:loading.attr="disabled" wire:target="download"
                            class="flex-1 h-[50px] rounded-[30px] bg-primary text-surface font-sans font-semibold text-[14px] flex items-center justify-center gap-2 hover:opacity-95 transition disabled:opacity-60">
                        <span wire:loading.remove wire:target="download" class="flex items-center gap-2"><i class="ri-download-2-line text-[17px]"></i> Download</span>
                        <span wire:loading wire:target="download" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i> Preparing…</span>
                    </button>
                    <button type="button" wire:click="close" class="h-[50px] px-6 rounded-[30px] bg-surface text-text font-sans font-semibold text-[14px] shadow-sm hover:bg-surface/70 transition">Cancel</button>
                </div>
            </div>
        </div>
    @endif
</div>
