{{-- Soft opt-in for web push — appears once (dismissible) when the browser supports it and the user
     hasn't decided yet. On "Enable" it triggers the native permission prompt + subscribes. --}}
<div x-data="{
        show: false,
        busy: false,
        init() {
            if (! window.nextelPush || ! window.nextelPush.supported()) return;
            if (window.nextelPush.permission() !== 'default') return;
            if (localStorage.getItem('push-prompt-dismissed')) return;
            setTimeout(() => { this.show = true; }, 4000); // let the first paint settle
        },
        async enable() {
            this.busy = true;
            let r = { ok: false };
            try { r = await window.nextelPush.subscribe(); } catch (e) { /* ignore */ }
            this.busy = false;
            this.show = false;
            localStorage.setItem('push-prompt-dismissed', '1'); // don't nag again either way
        },
        dismiss() {
            this.show = false;
            localStorage.setItem('push-prompt-dismissed', '1');
        },
     }"
     x-show="show" x-cloak
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 translate-y-3"
     x-transition:enter-end="opacity-100 translate-y-0"
     class="fixed bottom-4 inset-x-0 z-[110] flex justify-center px-4 pointer-events-none [padding-bottom:env(safe-area-inset-bottom)]">
    <div class="pointer-events-auto w-full sm:w-[400px] max-w-[94vw] flex items-start gap-3 rounded-[16px] bg-surface px-4 py-3.5 shadow-[0_12px_40px_rgba(0,0,0,0.2)] ring-1 ring-primary/15">
        <span class="size-9 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0 mt-0.5"><i class="ri-notification-3-line text-[18px]"></i></span>
        <div class="flex-1 min-w-0">
            <p class="font-heading font-semibold text-text text-[13.5px]">Turn on notifications</p>
            <p class="font-sans text-muted text-[12px] leading-snug mt-0.5">Get alerts for chat replies, purchases and rewards.</p>
            <div class="flex items-center gap-2 mt-2.5">
                <button type="button" @click="enable()" :disabled="busy" class="h-8 px-4 rounded-full bg-primary text-surface font-sans font-semibold text-[12px] disabled:opacity-60"><span x-show="!busy">Enable</span><span x-show="busy" x-cloak>Enabling…</span></button>
                <button type="button" @click="dismiss()" class="h-8 px-3 rounded-full bg-background text-muted font-sans font-semibold text-[12px]">Not now</button>
            </div>
        </div>
        <button type="button" @click="dismiss()" class="shrink-0 size-6 rounded-full hover:bg-background flex items-center justify-center text-muted" aria-label="Dismiss"><i class="ri-close-line text-[16px]"></i></button>
    </div>
</div>
