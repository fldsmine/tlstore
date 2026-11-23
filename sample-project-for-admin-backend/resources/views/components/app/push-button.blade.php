{{-- Explicit "enable web push" prompt. Self-hides when the browser is unsupported or permission is
     already granted; shows a short blocked-hint if the user denied. Clicking Enable fires the native
     permission prompt and subscribes (window.nextelPush lives in resources/js/app.js). --}}
<div x-data="{
        show: false,
        denied: false,
        busy: false,
        init() {
            const p = window.nextelPush;
            if (! p || ! p.supported()) { return; }        // unsupported → stay hidden
            const perm = p.permission();
            if (perm === 'granted') { return; }             // already enabled → hidden
            if (perm === 'denied') { this.denied = true; return; }
            this.show = true;                               // 'default' → offer the button
        },
        async enable() {
            this.busy = true;
            let r = { ok: false };
            try { r = await window.nextelPush.subscribe(); } catch (e) { /* ignore */ }
            this.busy = false;
            if (r && r.ok) { this.show = false; return; }   // subscribed → hide
            if (window.nextelPush.permission() === 'denied') { this.show = false; this.denied = true; }
        },
     }" {{ $attributes }}>
    <div x-show="show" x-cloak
         class="flex items-center gap-3 rounded-[16px] bg-surface ring-1 ring-primary/15 shadow-[0_4px_9px_rgba(194,194,194,0.07),0_16px_16px_rgba(194,194,194,0.06)] px-4 py-3">
        <span class="size-10 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0"><i class="ri-notification-3-line text-[19px]"></i></span>
        <div class="min-w-0 flex-1">
            <p class="font-heading font-semibold text-text text-[13.5px]">Enable notifications</p>
            <p class="font-sans text-muted text-[12px] leading-snug mt-0.5">Get alerts for chat replies, purchases and rewards.</p>
        </div>
        <button type="button" @click="enable()" :disabled="busy"
                class="h-9 px-4 rounded-full bg-primary text-surface font-sans font-semibold text-[12.5px] shrink-0 disabled:opacity-60 hover:opacity-95 transition">
            <span x-show="!busy">Enable</span><span x-show="busy" x-cloak>Enabling…</span>
        </button>
    </div>

    <div x-show="denied" x-cloak class="flex items-center gap-2.5 rounded-[16px] bg-background px-4 py-3">
        <i class="ri-notification-off-line text-muted text-[18px] shrink-0"></i>
        <p class="font-sans text-muted text-[12px] leading-snug">Notifications are blocked. Turn them on for {{ config('app.name') }} in your browser settings.</p>
    </div>
</div>
