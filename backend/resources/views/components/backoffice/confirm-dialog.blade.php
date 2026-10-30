{{--
    Trigger via $dispatch('confirm-action', { id, method, params, title, message, confirmText, tone }).
    On confirm, calls the originating component's method server-side (which still runs its own authorizeWrite()).
--}}
<div x-data="{
        open: false,
        busy: false,
        p: { title: '', message: '', confirmText: 'Confirm', tone: 'danger', id: null, method: null, params: [] },
        show(detail) {
            this.busy = false;
            this.p = Object.assign({ title: 'Are you sure?', message: '', confirmText: 'Confirm', tone: 'danger', params: [] }, detail);
            this.open = true;
        },
        proceed() {
            if (this.busy) { return; }
            const p = this.p;
            if (! (p.id && p.method && window.Livewire)) { this.open = false; return; }
            const c = window.Livewire.find(p.id);
            if (! c) { this.open = false; return; }
            // Keep open with a spinner until the server action resolves.
            this.busy = true;
            Promise.resolve(c.call(p.method, ...(p.params || []))).catch(() => {}).finally(() => {
                this.busy = false;
                this.open = false;
            });
        },
        tones: {
            danger:  { btn: 'bg-danger text-white',  ring: 'bg-danger/12 text-danger',  icon: 'ri-error-warning-line' },
            primary: { btn: 'bg-primary text-white', ring: 'bg-primary/10 text-primary', icon: 'ri-question-line' },
            warning: { btn: 'bg-[#d79937] text-white', ring: 'bg-[#d79937]/15 text-[#d79937]', icon: 'ri-alert-line' },
        },
     }"
     @confirm-action.window="show($event.detail)"
     @keydown.escape.window="busy || (open = false)"
     :data-lock-scroll="open ? '' : null"
     x-cloak>
    <div x-show="open" x-transition.opacity class="fixed inset-0 z-[95] bg-black/45 backdrop-blur-sm" @click="busy || (open = false)"></div>
    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         class="fixed inset-x-4 top-1/2 -translate-y-1/2 sm:inset-x-auto sm:left-1/2 sm:-translate-x-1/2 sm:w-[400px] z-[96] bg-surface rounded-[22px] p-6 shadow-[0_20px_60px_rgba(0,0,0,0.3)]">
        <div class="flex flex-col items-center text-center gap-3">
            <span class="size-12 rounded-full flex items-center justify-center" :class="(tones[p.tone] || tones.danger).ring">
                <i class="text-[24px]" :class="(tones[p.tone] || tones.danger).icon"></i>
            </span>
            <p class="font-heading font-medium text-text text-[17px]" x-text="p.title"></p>
            <p class="font-sans text-muted text-[13px] leading-snug" x-text="p.message"></p>
        </div>
        <div class="flex items-center gap-2.5 mt-6">
            <button type="button" @click="open = false" :disabled="busy" class="flex-1 h-[46px] rounded-[14px] bg-background text-text font-sans text-[14px] disabled:opacity-60">Cancel</button>
            <button type="button" @click="proceed()" :disabled="busy" class="flex-1 h-[46px] rounded-[14px] font-sans text-[14px] flex items-center justify-center gap-2 disabled:opacity-80" :class="(tones[p.tone] || tones.danger).btn">
                <span x-show="! busy" x-text="p.confirmText"></span>
                <span x-show="busy" class="flex items-center gap-2"><i class="ri-loader-4-line animate-spin"></i></span>
            </button>
        </div>
    </div>
</div>
