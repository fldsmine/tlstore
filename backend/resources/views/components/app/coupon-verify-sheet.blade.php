{{-- Reusable coupon-code verification bottom-sheet.
     Renders an inline trigger anchor and a slide-up dialog that verifies a code against the
     public API (GET /api/v1/coupons/verify?code=…) and displays the response.

     Usage anywhere: <x-app.coupon-verify-sheet label="Verify a coupon code" />
     Optional props: label (trigger text), title (sheet heading). Extra attributes merge onto
     the trigger button (e.g. class overrides). --}}
@props(['label' => 'Verify a code', 'title' => 'Verify Coupon Code'])

<div x-data="{
        open: false,
        code: '',
        loading: false,
        result: null,
        error: null,

        openSheet() {
            this.open = true;
            this.result = null;
            this.error = null;
            this.$nextTick(() => this.$refs.codeInput && this.$refs.codeInput.focus());
        },

        closeSheet() {
            this.open = false;
        },

        async verify() {
            const code = (this.code || '').trim().toUpperCase();

            if (code.length < 3) {
                this.error = 'Enter a coupon code to verify.';
                return;
            }

            this.loading = true;
            this.result = null;
            this.error = null;

            try {
                const response = await fetch('/api/v1/coupons/verify?code=' + encodeURIComponent(code), {
                    headers: { 'Accept': 'application/json' },
                });
                const body = await response.json();

                if (response.ok && body.success) {
                    this.result = body.data;
                } else {
                    this.error = body.message || 'Could not verify this code. Please try again.';
                }
            } catch (e) {
                this.error = 'Network error — please check your connection and try again.';
            } finally {
                this.loading = false;
            }
        },
     }"
     x-on:keydown.escape.window="open && closeSheet()"
     class="inline-flex">

    {{-- Trigger anchor — slides the sheet in --}}
    <button type="button" @click="openSheet"
            {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 font-sans text-[12px] text-primary font-semibold hover:underline']) }}>
        <i class="ri-shield-check-line text-[13px]"></i> {{ $label }}
    </button>

    {{-- Bottom-sheet dialog — data-lock-scroll is bound dynamically so the page scroll stays
         locked only while the sheet is open (the scroll-lock watcher locks on the attribute's
         presence; a static attribute would freeze the page even while the sheet is hidden). --}}
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

            {{-- Code entry --}}
            <div class="flex gap-2">
                <input type="text" x-ref="codeInput" x-model="code" @keydown.enter.prevent="verify" placeholder="CODE" autocomplete="off" aria-label="Coupon code"
                       class="flex-1 min-w-0 h-11 rounded-[12px] bg-background border border-muted/15 px-3 font-mono text-[13px] tracking-wider uppercase text-text placeholder:text-muted/50 placeholder:font-sans placeholder:tracking-normal focus:outline-none focus:border-primary/40 transition">
                <button type="button" @click="verify" :disabled="loading"
                        class="shrink-0 inline-flex items-center justify-center gap-1.5 h-11 px-5 rounded-[12px] bg-primary text-surface font-sans font-semibold text-[13.5px] hover:opacity-95 transition disabled:opacity-60 disabled:cursor-not-allowed">
                    <span x-show="!loading" class="inline-flex items-center gap-1.5"><i class="ri-search-line text-[15px]"></i> Verify</span>
                    <span x-show="loading"><i class="ri-loader-4-line animate-spin"></i></span>
                </button>
            </div>

            {{-- Error state --}}
            <div x-show="error" x-cloak class="flex items-start gap-2 rounded-[12px] bg-danger/10 px-4 py-3">
                <i class="ri-error-warning-line text-danger text-[17px] shrink-0 mt-0.5"></i>
                <p class="font-sans text-[12.5px] text-danger leading-relaxed" x-text="error"></p>
            </div>

            {{-- Result state --}}
            <template x-if="result">
                <div class="flex flex-col gap-3">
                    <div class="flex flex-col items-center gap-2 py-1 text-center">
                        <span class="size-12 rounded-full flex items-center justify-center"
                              :class="result.is_valid ? 'bg-success/12 text-success' : 'bg-warning/15 text-warning'">
                            <i :class="result.is_valid ? 'ri-checkbox-circle-fill' : 'ri-time-line'" class="text-[26px]"></i>
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1"
                              :class="result.is_valid ? 'bg-success/15 text-success' : 'bg-warning/15 text-warning'">
                            <span class="font-sans text-[12px] font-semibold" x-text="result.status"></span>
                        </span>
                        <p class="font-sans text-muted text-[12px] leading-relaxed" x-text="result.verification.message"></p>
                    </div>

                    <div class="rounded-[14px] bg-background px-4 py-3 flex flex-col gap-2">
                        <p class="font-heading font-semibold text-text text-[15px]" x-text="result.display_name"></p>
                        <p class="font-sans text-muted text-[11.5px] flex flex-wrap items-center gap-x-1.5">
                            <span x-text="result.type_name"></span>
                            <template x-if="result.region"><span>· <span x-text="result.region"></span></span></template>
                            <template x-if="result.is_special"><span class="inline-flex items-center gap-1 rounded-full bg-secondary/40 text-primary px-2 py-0.5 font-sans text-[10px] uppercase tracking-wide"><i class="ri-gift-2-line"></i> Special</span></template>
                        </p>

                        {{-- What the coupon grants --}}
                        <template x-if="result.cloud_storage_plan">
                            <p class="font-sans text-[12px] text-text inline-flex items-center gap-1.5">
                                <i class="ri-database-2-line text-primary"></i>
                                <span>+<span x-text="result.cloud_storage_plan.storage"></span> contacts<template x-if="result.cloud_storage_plan.extra_sms > 0"> · +<span x-text="result.cloud_storage_plan.extra_sms"></span> SMS/day</template><template x-if="result.cloud_storage_plan.duration > 0"> · for <span x-text="result.cloud_storage_plan.duration"></span> days</template><template x-if="result.cloud_storage_plan.duration <= 0"> · lifetime</template></span>
                            </p>
                        </template>
                        <template x-if="result.package">
                            <p class="font-sans text-[12px] text-text inline-flex items-center gap-1.5">
                                <i class="ri-time-line text-primary"></i>
                                <span><span x-text="result.package.minutes"></span> daily call minutes</span>
                            </p>
                        </template>
                        <template x-if="result.additional_minute_plan">
                            <p class="font-sans text-[12px] text-text inline-flex items-center gap-1.5">
                                <i class="ri-add-circle-line text-primary"></i>
                                <span>+<span x-text="result.additional_minute_plan.minutes"></span> daily minutes for <span x-text="result.additional_minute_plan.duration"></span> days</span>
                            </p>
                        </template>
                    </div>

                    <div class="flex items-center justify-between font-sans text-[12px]">
                        <span class="text-muted">Issued by</span>
                        <span class="text-text font-medium" x-text="result.agent ? (result.agent.name + (result.agent.username ? ' (@' + result.agent.username + ')' : '')) : '—'"></span>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>