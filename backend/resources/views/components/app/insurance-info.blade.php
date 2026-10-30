@props(['label' => 'label', 'title' => 'Page title'])

<div x-data="{
        open: false,

        openSheet() {
            this.open = true;
        },

        closeSheet() {
            this.open = false;
        },
     }"
     x-on:keydown.escape.window="open && closeSheet()"
     class="inline-flex">

    {{-- Trigger anchor — slides the sheet in --}}
    <button type="button" @click="openSheet"
            {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 font-sans text-[12px] text-primary font-semibold hover:underline']) }}>
        <i class="ri-information-line text-warning text-[17px] shrink-0 mt-0.5"></i> {{ $label }}
    </button>

    <div x-show="open" x-cloak :data-lock-scroll="open ? '' : null" class="fixed inset-0 z-[80] flex items-end sm:items-center justify-center sm:px-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" x-show="open" x-transition.opacity @click="closeSheet"></div>

        <div class="relative w-full sm:max-w-[400px] bg-surface rounded-t-[26px] sm:rounded-[26px] px-6 pt-6 pb-8 flex flex-col gap-5 max-h-[85vh] overflow-y-auto"
             x-show="open"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="opacity-0 translate-y-6 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">

            <div class="flex items-center justify-between">
                <p class="font-heading font-medium text-text text-[16px]">{{ $title }}</p>
                <button type="button" @click="closeSheet" class="size-9 rounded-full bg-background flex items-center justify-center text-text" aria-label="Close">
                    <i class="ri-close-line text-[20px]"></i>
                </button>
            </div>

			<div class="flex flex-col gap-2 rounded-[12px] bg-background px-4 py-3">
				<div class="flex items-start gap-2">
					<span class="font-sans text-[11px] text-primary font-semibold mt-[1px]">1</span>
					<p class="font-sans text-muted text-[12px] leading-relaxed"> <b>Death</b>: The insurer may pay the outstanding loan balance if the borrower dies.</p>
				</div>
				<div class="flex items-start gap-2">
					<span class="font-sans text-[11px] text-primary font-semibold mt-[1px]">2</span>
					<p class="font-sans text-muted text-[12px] leading-relaxed"><b>Permanent Disability</b>: The insurer may settle the outstanding loan if the borrower becomes permanently disabled and can no longer work, subject to the policy terms.</p>
				</div>
				<div class="flex items-start gap-2">
					<span class="font-sans text-[11px] text-primary font-semibold mt-[1px]">3</span>
					<p class="font-sans text-muted text-[12px] leading-relaxed"><b>Loss of Employment / Inability to Work</b>: May cover loan repayments if the borrower loses their job or cannot work due to illness or injury, if included in the policy.</p>
				</div>
				<div class="flex items-start gap-2">
					<span class="font-sans text-[11px] text-primary font-semibold mt-[1px]">4</span>
					<p class="font-sans text-muted text-[12px] leading-relaxed"><b>Property / Collateral Insurance</b>: Protects the asset used as security for the loan (e.g., a house, car, or other property) against specified risks, such as fire or natural disasters, depending on the policy.</p>
				</div>
			</div>
			
        </div>
    </div>
</div>