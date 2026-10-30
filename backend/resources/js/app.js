import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// File downloads across surfaces (browser / Telegram Mini App / Android WebView bridge).
// Registers the delegated handler for [data-nextel-download] links and window.nextelDownload.
import './downloads';

// Laravel Echo over Reverb (WebSockets) — powers real-time Cloud call delivery (the dock listens
// for CloudBroadcastFired). A slow Livewire poll remains as a fallback if the socket drops, so
// sockets are an ENHANCEMENT, never a requirement.
//
// Vite bakes VITE_* at BUILD time. A build without them leaves `key: undefined`, and Pusher throws
// on construction — which at module top level aborts the whole file and silently takes every Alpine
// component in the app down with it. Guarded so a broadcasting misconfiguration degrades to polling
// instead of breaking the UI.
const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

if (reverbKey) {
    try {
        window.Pusher = Pusher;
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
            wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    } catch (error) {
        console.warn('Nextel: real-time updates unavailable —', error?.message ?? error);
    }
} else {
    console.warn('Nextel: VITE_REVERB_APP_KEY was missing at build time — real-time updates are off, falling back to polling.');
}

// ── Nextel Cloud call audio ─────────────────────────────────────────────────
// Web Audio ring tones mirrored from the product mockup: an outgoing ringback,
// a looping incoming ringtone, and a short "connected" chime played before the
// sponsor message. One shared AudioContext, unlocked on the first user gesture
// so server-pushed incoming calls can ring without a click on the call UI.
const CloudAudio = {
    ctx: null,
    loop: null, // setInterval id for whichever ring is currently looping

    _ac() {
        const AC = window.AudioContext || window.webkitAudioContext;
        if (! AC) return null;
        if (! this.ctx) this.ctx = new AC();
        if (this.ctx.state === 'suspended') this.ctx.resume();
        return this.ctx;
    },

    // Looping incoming ringtone — a falling 210→85Hz double-beep every 1.8s.
    startIncoming() {
        const ctx = this._ac();
        if (! ctx) return;
        this.stop();
        const beep = (t) => {
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = 'sine';
            o.frequency.setValueAtTime(210, t);
            o.frequency.exponentialRampToValueAtTime(85, t + 0.2);
            g.gain.setValueAtTime(0.0001, t);
            g.gain.exponentialRampToValueAtTime(0.4, t + 0.02);
            g.gain.exponentialRampToValueAtTime(0.0001, t + 0.24);
            o.connect(g);
            g.connect(ctx.destination);
            o.start(t);
            o.stop(t + 0.26);
        };
        const burst = () => {
            const t = this.ctx.currentTime;
            beep(t);
            beep(t + 0.3);
        };
        burst();
        this.loop = setInterval(burst, 1800);
    },

    // Looping outgoing ringback — dual-tone 660/528Hz (with harmonics), 3.2s cycle.
    startRingback() {
        const ctx = this._ac();
        if (! ctx) return;
        this.stop();
        const cycle = (start) => {
            [
                { freq: 660, delay: 0, duration: 1.2 },
                { freq: 528, delay: 0.55, duration: 1.4 },
            ].forEach(({ freq, delay, duration }) => {
                const at = start + delay;
                [1, 2, 3].forEach((mult, idx) => {
                    const o = ctx.createOscillator();
                    const g = ctx.createGain();
                    o.type = 'sine';
                    o.frequency.setValueAtTime(freq * mult, at);
                    const peak = [0.28, 0.1, 0.04][idx];
                    g.gain.setValueAtTime(0.0001, at);
                    g.gain.exponentialRampToValueAtTime(peak, at + 0.015);
                    g.gain.exponentialRampToValueAtTime(0.0001, at + duration);
                    o.connect(g);
                    g.connect(ctx.destination);
                    o.start(at);
                    o.stop(at + duration + 0.05);
                });
            });
        };
        cycle(ctx.currentTime);
        this.loop = setInterval(() => cycle(this.ctx.currentTime), 3200);
    },

    // One-shot "connected" chime — a short C-E-G-A-G-E arpeggio.
    chime() {
        const ctx = this._ac();
        if (! ctx) return;
        const master = ctx.createGain();
        master.gain.setValueAtTime(0.0001, ctx.currentTime);
        master.gain.exponentialRampToValueAtTime(0.06, ctx.currentTime + 0.3);
        master.connect(ctx.destination);
        const notes = [261.63, 329.63, 392, 440, 392, 329.63];
        const step = 0.14;
        notes.forEach((f, n) => {
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = 'sine';
            const at = ctx.currentTime + n * step;
            o.frequency.setValueAtTime(f, at);
            g.gain.setValueAtTime(0.0001, at);
            g.gain.exponentialRampToValueAtTime(1, at + 0.03);
            g.gain.setValueAtTime(1, at + step - 0.04);
            g.gain.exponentialRampToValueAtTime(0.0001, at + step);
            o.connect(g);
            g.connect(master);
            o.start(at);
            o.stop(at + step + 0.02);
        });
    },

    stop() {
        if (this.loop) {
            clearInterval(this.loop);
            this.loop = null;
        }
    },
};

// Warm up the speech voices early — getVoices() loads them asynchronously, so kick it off now
// (and again on voiceschanged) so a voice is ready by the time a call connects.
const Speech = 'speechSynthesis' in window ? window.speechSynthesis : null;
if (Speech) {
    Speech.getVoices();
    Speech.addEventListener?.('voiceschanged', () => Speech.getVoices());
}

// Unlock the shared AudioContext AND the speech engine on the first user gesture: the ring can
// then play for a server-pushed incoming call, and — crucially — the sponsor message can be
// spoken even though it starts ~2.5s after the click (Safari/WebKit, and increasingly Chrome,
// gate speechSynthesis behind a user gesture; speaking a silent utterance here unlocks it).
const unlockMedia = () => {
    CloudAudio._ac();
    if (Speech) {
        try {
            Speech.resume();
            const warm = new SpeechSynthesisUtterance(' ');
            warm.volume = 0;
            Speech.speak(warm);
        } catch (e) { /* ignore */ }
    }
};
['pointerdown', 'keydown', 'touchstart'].forEach((evt) => {
    window.addEventListener(evt, unlockMedia, { once: true, passive: true });
});

// Speak text aloud in the browser's own voice. Binds the default (or first English) voice so a
// concrete voice is always chosen, resume()s the engine, and — because Chrome drops an utterance
// queued in the same tick as cancel() — only cancels (then defers) when something is already
// speaking. `onDone` fires when the utterance ends (or can't start). Browser TTS is inherently
// per-device: healthy engines speak, a stuck engine (e.g. some Chrome installs) stays silent.
window.nextelSpeak = (text, onDone) => {
    if (!('speechSynthesis' in window) || !text) { onDone && onDone(); return; }
    const ss = window.speechSynthesis;
    const utter = new SpeechSynthesisUtterance(String(text));
    const voices = ss.getVoices();
    if (voices.length) {
        utter.voice = voices.find((v) => v.default) || voices.find((v) => /^en(-|_|$)/i.test(v.lang)) || voices[0];
    }
    if (onDone) { utter.onend = onDone; utter.onerror = onDone; }
    const go = () => { try { ss.resume(); ss.speak(utter); } catch (e) { onDone && onDone(); } };
    if (ss.speaking || ss.pending) { ss.cancel(); setTimeout(go, 90); } else { go(); }
};

/**
 * Copy text, resolving true/false rather than rejecting. navigator.clipboard is unavailable on
 * non-secure origins and rejects outright when permission is denied, which would otherwise leave a
 * copy button silently dead — so fall back to a throwaway textarea + execCommand.
 */
window.nextelCopy = async (text) => {
    try {
        await navigator.clipboard.writeText(text);

        return true;
    } catch (e) {
        try {
            const area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.cssText = 'position:fixed;top:-9999px;opacity:0';
            document.body.appendChild(area);
            area.select();
            const copied = document.execCommand('copy');
            area.remove();

            return copied;
        } catch (_) {
            return false;
        }
    }
};

// Soft two-note chime + a short vibrate for a new chat message/request (fired by the toast).
// Reuses the shared AudioContext, which is unlocked on the first user gesture.
window.nextelChatPing = () => {
    try {
        const ctx = CloudAudio._ac();
        if (ctx) {
            const t = ctx.currentTime;
            [[880, 0], [1174.66, 0.09]].forEach(([freq, delay]) => {
                const o = ctx.createOscillator();
                const g = ctx.createGain();
                o.type = 'sine';
                o.frequency.setValueAtTime(freq, t + delay);
                g.gain.setValueAtTime(0.0001, t + delay);
                g.gain.exponentialRampToValueAtTime(0.14, t + delay + 0.02);
                g.gain.exponentialRampToValueAtTime(0.0001, t + delay + 0.2);
                o.connect(g);
                g.connect(ctx.destination);
                o.start(t + delay);
                o.stop(t + delay + 0.22);
            });
        }
    } catch (e) { /* ignore */ }
    if (navigator.vibrate) {
        try { navigator.vibrate(30); } catch (e) { /* ignore */ }
    }
};

// ── PWA service worker + Web Push ────────────────────────────────────────────
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch((e) => console.warn('Nextel: SW registration failed —', e?.message ?? e));
    });
}

// Web Push helper used by the soft opt-in prompt (x-app.push-prompt). The VAPID public key is read
// from a <meta> tag; the browser subscription is POSTed to the app so the server can push to it.
window.nextelPush = {
    supported() {
        return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    },
    permission() {
        return this.supported() ? Notification.permission : 'unsupported';
    },
    _csrf() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    },
    _urlBase64ToUint8Array(base64) {
        const padding = '='.repeat((4 - (base64.length % 4)) % 4);
        const b64 = (base64 + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = atob(b64);
        return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
    },
    async subscribe() {
        if (!this.supported()) return { ok: false, reason: 'unsupported' };
        const key = document.querySelector('meta[name="vapid-public-key"]')?.content;
        if (!key) return { ok: false, reason: 'no-key' };
        if ((await Notification.requestPermission()) !== 'granted') return { ok: false, reason: 'denied' };
        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: this._urlBase64ToUint8Array(key),
        });
        const res = await fetch('/push/subscribe', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this._csrf() },
            body: JSON.stringify(sub),
        });
        return { ok: res.ok };
    },
    async unsubscribe() {
        if (!this.supported()) return;
        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.getSubscription();
        if (!sub) return;
        await fetch('/push/subscribe', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this._csrf() },
            body: JSON.stringify({ endpoint: sub.endpoint }),
        });
        await sub.unsubscribe();
    },
};

// Keep the browser's single push subscription attached to the CURRENTLY logged-in user. On a shared
// browser, account B inherits A's granted permission (so the enable prompt stays hidden), yet the
// subscription is still A's on the server — so B would silently receive no pushes. When the logged-in
// user changes, re-subscribe (permission is already granted → no prompt) so the server (which deletes
// a subscription owned by someone else and recreates it) re-attaches this browser to B.
window.addEventListener('load', async () => {
    try {
        const p = window.nextelPush;
        const user = document.querySelector('meta[name="push-user"]')?.content;
        if (! user || ! p.supported() || p.permission() !== 'granted') return;
        if (localStorage.getItem('push-user') === user) return; // already synced to this user
        const r = await p.subscribe(); // granted → no prompt; (re)creates + posts the subscription
        if (r && r.ok) localStorage.setItem('push-user', user);
    } catch (e) { /* ignore */ }
});

document.addEventListener('alpine:init', () => {
    Alpine.plugin(collapse);
    Alpine.plugin(focus);

    // Nextel Cloud — outgoing-call controller: drives the call screen + reads the sponsor
    // message aloud via the Web Speech API, then reports the duration back to Livewire.
    Alpine.data('cloudCalls', () => ({
        active: null,
        elapsed: 0,        // seconds on the line (display clock)
        progress: 0,       // 0–100 of the message played
        phase: 'connecting', // connecting | speaking | done
        showPrompt: false, // the sponsor-message pop
        done: false,
        timer: null,       // 1s clock
        playTimer: null,   // playback progress ticker
        connectTimer: null, // ring → connect delay

        // Outgoing call — the server records it first (guards + history).
        call(num) {
            if (this.active) return; // one call at a time
            this._primeSpeech(); // unlock TTS inside the click gesture (the message speaks ~2.5s later)
            this._setup(num, 'outgoing');
            this.$wire.startCall(num.id);
            CloudAudio.startRingback(); // ring while the call connects
            this.connectTimer = setTimeout(() => this._connect(), 2200);
        },

        // Answered incoming call — the server already opened the call record (answerBroadcast).
        // The incoming ringtone was playing on the prompt; stop it and connect briefly.
        playAnswered(num) {
            if (this.active) return;
            this._primeSpeech(); // unlock TTS inside the Accept gesture
            CloudAudio.stop();
            this._setup(num, 'incoming');
            this.connectTimer = setTimeout(() => this._connect(), 700);
        },

        // Speak a silent utterance now (inside a user gesture) so the real, delayed message is
        // allowed to play — browsers gate speechSynthesis behind recent user activation.
        _primeSpeech() {
            if (!('speechSynthesis' in window)) return;
            try {
                window.speechSynthesis.resume();
                const warm = new SpeechSynthesisUtterance(' ');
                warm.volume = 0;
                window.speechSynthesis.speak(warm);
            } catch (e) { /* ignore */ }
        },

        // Stop the ring, play the "connected" chime, then read the sponsor message.
        _connect() {
            if (! this.active) return;
            CloudAudio.stop();
            CloudAudio.chime();
            setTimeout(() => this._play(), 320);
        },

        _setup(num, direction) {
            this.active = { ...num, direction };
            this.elapsed = 0;
            this.progress = 0;
            this.phase = 'connecting';
            this.showPrompt = false;
            this.done = false;
            this.timer = setInterval(() => this.elapsed++, 1000);
        },

        // Play the sponsor message. The reward requires fully listening, so we pace playback to the
        // server's duration estimate and only complete when it's reached — the sponsor pop shows partway.
        _play() {
            if (!this.active) return;
            this.phase = 'speaking';
            this._speakAudio(); // best-effort TTS (audio only — does not gate completion)

            const total = Math.max(3, Number(this.active.duration) || 6);
            const startedAt = Date.now();
            this.playTimer = setInterval(() => {
                if (!this.active) return;
                const el = (Date.now() - startedAt) / 1000;
                this.progress = Math.min(100, Math.round((el / total) * 100));
                if (el >= total * 0.25) this.showPrompt = true;
                if (el >= total) this.complete();
            }, 200);
        },

        _speakAudio() {
            const msgs = (this.active && this.active.messages) || [];
            let i = 0;

            const fallback = (text, done) => window.nextelSpeak(text, done);

            const next = () => {
                if (!this.active || i >= msgs.length) return;
                const note = msgs[i++];
                if (note.audio) {
                    const audio = new Audio(note.audio);
                    this._voiceAudio = audio;
                    audio.onended = next;
                    audio.onerror = () => fallback(note.text, next);
                    const p = audio.play();
                    if (p && p.catch) p.catch(() => fallback(note.text, next));
                } else {
                    fallback(note.text, next);
                }
            };

            if ('speechSynthesis' in window) window.speechSynthesis.cancel();
            setTimeout(next, 80);
        },

        _stopVoice() {
            if (this._voiceAudio) {
                try { this._voiceAudio.pause(); this._voiceAudio.currentTime = 0; } catch (e) { /* ignore */ }
                this._voiceAudio = null;
            }
            if ('speechSynthesis' in window) window.speechSynthesis.cancel();
        },

        // Message fully played → mark complete and WAIT for the user to claim. No auto-credit:
        // the reward is only booked when the user clicks "Claim offer" (enabled once complete).
        complete() {
            if (!this.active || this.done || this.phase === 'complete') return;
            this.phase = 'complete';
            this.progress = 100;
            this.showPrompt = true;                          // make sure the claim button is visible
            if (this.timer) clearInterval(this.timer);       // stop the on-the-line clock
            if (this.playTimer) clearInterval(this.playTimer);
            this._stopVoice();
        },

        // User clicked "Claim offer" — only possible once the message is complete. Credits the reward
        // (the server re-verifies listen time) and opens the sponsor's offer link.
        claim() {
            if (!this.active || this.done || this.phase !== 'complete') return;
            this.done = true;
            if (this.active.claimUrl) window.open(this.active.claimUrl, '_blank', 'noopener');
            this.$wire.completeCall(this.elapsed);
            setTimeout(() => this.reset(), 2600); // show the "earned" state briefly, then close
        },

        // End the call early → forfeit the reward (no completeCall).
        hangUp() {
            if (!this.active) return;
            const wasDone = this.done;
            this.reset();
            if (!wasDone) this.$wire.endCall();
        },

        reset() {
            if (this.timer) clearInterval(this.timer);
            if (this.playTimer) clearInterval(this.playTimer);
            if (this.connectTimer) clearTimeout(this.connectTimer);
            CloudAudio.stop();
            this._stopVoice();
            this.active = null;
            this.elapsed = 0;
            this.progress = 0;
            this.phase = 'connecting';
            this.showPrompt = false;
            this.done = false;
        },

        fmt(s) {
            const m = Math.floor(s / 60);
            const r = s % 60;
            return m + ':' + String(r).padStart(2, '0');
        },
    }));

    // Rings the incoming ringtone for as long as the incoming-call prompt is mounted.
    // Mount = the server delivered a broadcast; unmount (answered/declined/expired) stops it.
    Alpine.data('cloudIncomingRing', () => ({
        init() {
            CloudAudio.startIncoming();
        },
        destroy() {
            CloudAudio.stop();
        },
    }));

    // VAS bill-payment form — owns ALL form state client-side so provider/plan/wallet/amount/
    // quick-picks/toggles and live min-max validation are instant with zero server round-trips.
    // Only two things hit the server: verify() (a VTpass merchant lookup) and submit() (the
    // authoritative purchase). init = the JSON blob rendered by App\Livewire\Dashboard\Vas\Purchase.
    Alpine.data('vasPurchase', (init) => ({
        // Static catalog + limits (never mutated).
        d: init,

        // Reactive form state.
        serviceId: init.serviceId,
        wallet: init.wallet,
        amount: '',
        variationCode: null,
        recipient: '',
        phone: init.phone || '',
        meterType: 'prepaid',
        subscriptionType: 'renew',

        // Verify + submit UI state.
        verified: false,
        customerName: null,
        verifying: false,
        recipientError: '',
        confirming: false,
        pinArr: ['', '', '', ''],
        submitting: false,
        submitError: '',

        init() {
            // Changing the target invalidates a prior verification.
            this.$watch('recipient', () => this.resetVerify());
            this.$watch('meterType', () => this.resetVerify());
        },

        resetVerify() {
            this.verified = false;
            this.customerName = null;
            this.recipientError = '';
        },

        // ── Derived (getters — recomputed instantly, no server) ──
        get provider() {
            return this.d.providers.find((p) => p.id === this.serviceId) || this.d.providers[0] || {};
        },
        get entersAmount() {
            return this.d.pricing === 'amount';
        },
        get needsVerify() {
            return !! this.provider.verify;
        },
        get recipientLabel() {
            return this.provider.recipient || 'Phone number';
        },
        get recipientIsPhone() {
            return this.recipientLabel.toLowerCase().includes('phone');
        },
        get plans() {
            return this.d.plansByProvider[this.serviceId] || [];
        },
        get selectedPlan() {
            return this.plans.find((p) => p.code === this.variationCode) || null;
        },
        get walletData() {
            return this.d.wallets[this.wallet] || { label: '', balance: 0, ngn: 0, min: 0, max: 0 };
        },
        get minNgn() {
            return Number(this.walletData.min) || 0;
        },
        get maxNgn() {
            return Number(this.walletData.max) || 0;
        },
        get ngnAmount() {
            return this.entersAmount ? (parseFloat(this.amount) || 0) : (this.selectedPlan ? Number(this.selectedPlan.amount) : 0);
        },
        get usdAmount() {
            const r = Number(this.d.rate) || 0;
            return r > 0 ? this.ngnAmount / r : this.ngnAmount;
        },
        get limitLabel() {
            const bits = [];
            if (this.minNgn > 0) bits.push('Min ₦' + this.fmt(this.minNgn));
            if (this.maxNgn > 0) bits.push('Max ₦' + this.fmt(this.maxNgn));
            return bits.join(' · ');
        },
        get amountError() {
            const n = this.ngnAmount;
            if (n <= 0) return '';
            if (this.minNgn > 0 && n < this.minNgn) return 'Minimum is ₦' + this.fmt(this.minNgn) + '.';
            if (this.maxNgn > 0 && n > this.maxNgn) return 'Maximum is ₦' + this.fmt(this.maxNgn) + '.';
            if (this.usdAmount > Number(this.walletData.balance)) return 'More than your balance.';
            return '';
        },
        get recipientValid() {
            const v = this.recipient.trim();
            return this.recipientIsPhone ? /^0\d{10}$/.test(v) : v.length > 0;
        },
        get contactValid() {
            return this.recipientIsPhone || /^0\d{10}$/.test(this.phone.trim());
        },
        get canContinue() {
            if (!this.wallet || this.ngnAmount <= 0 || this.amountError) return false;
            if (!this.recipientValid || !this.contactValid) return false;
            if (this.needsVerify && !this.verified) return false;
            return true;
        },
        planOver(plan) {
            const a = Number(plan.amount);
            return (this.maxNgn > 0 && a > this.maxNgn) || a < this.minNgn;
        },
        picks() {
            return [50, 100, 200, 500, 1000].filter((v) => (this.maxNgn <= 0 || v <= this.maxNgn) && v >= this.minNgn);
        },
        fmt(n) {
            return Number(n).toLocaleString('en-US', { maximumFractionDigits: 0 });
        },
        usd(n) {
            return '$' + (Number(n) || 0).toFixed(2);
        },

        // ── Actions ──
        selectProvider(id) {
            this.serviceId = id;
            this.variationCode = null;
            this.amount = '';
            this.resetVerify();
        },

        async verify() {
            if (this.verifying) return;
            // Electricity verifies against the meter type; TV/education against the chosen item (Flutterwave needs it).
            const type = this.d.category === 'electricity'
                ? this.meterType
                : ((this.d.category === 'tv' || this.d.category === 'education') ? this.variationCode : null);
            this.verifying = true;
            this.recipientError = '';
            try {
                const res = await this.$wire.verify(this.serviceId, this.recipient.trim(), type);
                if (res && res.ok) {
                    this.verified = true;
                    this.customerName = res.name;
                } else {
                    this.verified = false;
                    this.recipientError = (res && res.error) || 'Verification failed.';
                }
            } catch (e) {
                this.recipientError = 'Verification is unavailable right now.';
            } finally {
                this.verifying = false;
            }
        },

        // PIN boxes (client-owned; only sent inside submit()).
        get pin() {
            return this.pinArr.join('');
        },
        pinInput(i, e) {
            const v = (e.target.value || '').replace(/[^0-9]/g, '').slice(-1);
            e.target.value = v;
            this.pinArr[i] = v;
            if (v && i < 3) this.$refs['pb' + (i + 1)]?.focus();
        },
        pinKey(i, e) {
            if (e.key === 'Backspace' && !this.pinArr[i] && i > 0) this.$refs['pb' + (i - 1)]?.focus();
        },
        resetPin() {
            this.pinArr = ['', '', '', ''];
            for (let i = 0; i < 4; i++) if (this.$refs['pb' + i]) this.$refs['pb' + i].value = '';
        },

        openConfirm() {
            if (!this.canContinue) return;
            this.submitError = '';
            this.resetPin();
            this.confirming = true;
        },
        closeConfirm() {
            this.confirming = false;
            this.resetPin();
        },

        async submit() {
            if (this.submitting || this.pin.length < 4) return;
            this.submitting = true;
            this.submitError = '';
            try {
                const res = await this.$wire.purchase({
                    serviceId: this.serviceId,
                    wallet: this.wallet,
                    amount: this.entersAmount ? this.ngnAmount : null,
                    variationCode: this.variationCode,
                    recipient: this.recipient.trim(),
                    phone: this.phone.trim(),
                    meterType: this.meterType,
                    subscriptionType: this.subscriptionType,
                    customerName: this.customerName,
                    pin: this.pin,
                });
                // Success redirects server-side; only a failure result comes back.
                if (res && !res.ok) {
                    this.submitError = res.error || 'Something went wrong.';
                    this.resetPin();
                }
            } catch (e) {
                this.submitError = 'Something went wrong. Please try again.';
                this.resetPin();
            } finally {
                this.submitting = false;
            }
        },
    }));

    // Chat thread: smart auto-scroll (don't yank you down if you've scrolled up — show a pill instead)
    // + optimistic outgoing bubbles that clear once the real message renders. `data-count` on the
    // scroll element carries the server-side message count so we can tell a real message from a poll.
    Alpine.data('chatThread', () => ({
        atBottom: true,
        hasNew: false,
        pending: [],
        lastCount: 0,
        init() {
            this.lastCount = this.count();
            this.scrollBottom(false);
            this.$refs.scroll.addEventListener('scroll', () => {
                this.atBottom = this.nearBottom();
                if (this.atBottom) this.hasNew = false;
            });
            new MutationObserver(() => {
                const c = this.count();
                const changed = c !== this.lastCount;
                if (changed) { this.lastCount = c; this.pending = []; } // real message(s) rendered
                if (changed && this.atBottom) this.scrollBottom();
                else if (changed) this.hasNew = true;
            }).observe(this.$refs.scroll, { childList: true, subtree: true });
        },
        count() { return parseInt(this.$refs.scroll.dataset.count || '0', 10) || 0; },
        nearBottom() {
            const e = this.$refs.scroll;
            return (e.scrollHeight - e.scrollTop - e.clientHeight) < 90;
        },
        scrollBottom(smooth = true) {
            this.$nextTick(() => {
                const e = this.$refs.scroll;
                e.scrollTo({ top: e.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
                this.atBottom = true;
                this.hasNew = false;
            });
        },
        addPending(text) {
            const t = (text || '').trim();
            if (! t) return;
            this.pending.push({ id: Date.now() + '-' + Math.round(Math.random() * 1e6), text: t });
            this.scrollBottom();
        },
    }));

    // Live "typing…" indicator — listens on the chat's public channel for the OTHER party's ChatTyping.
    Alpine.data('chatTyping', (chatId, myRole) => ({
        typing: false,
        _timer: null,
        init() {
            if (! window.Echo) return;
            window.Echo.channel('chat.' + chatId).listen('.ChatTyping', (e) => {
                if (! e || e.role === myRole) return;
                this.typing = true;
                clearTimeout(this._timer);
                this._timer = setTimeout(() => { this.typing = false; }, 3500);
            });
        },
    }));
});
// Lock background scroll whenever a modal/sheet/drawer (marked [data-lock-scroll]) is open.
// Watches both element mount/unmount AND the attribute toggling (the mobile drawer stays in
// the DOM and just adds/removes data-lock-scroll when it opens/closes).
//
// Uses the position:fixed technique (what battle-tested body-scroll-lock libraries do): freeze
// <body> at its current scroll offset (top: -scrollY) and restore the exact offset on unlock.
// This is bulletproof against the scroll snapping to the top — which plain overflow:hidden does
// NOT reliably prevent when the page is scrolled and a Livewire re-render happens. We also pad
// for the scrollbar width so the centered/boxed layout never shifts sideways when it disappears.
let lockedScrollY = 0;
const syncScrollLock = () => {
    const root = document.documentElement;
    const body = document.body;
    const locked = !! document.querySelector('[data-lock-scroll]');

    if (locked && ! root.hasAttribute('data-scroll-locked')) {
        lockedScrollY = window.scrollY || root.scrollTop || 0;
        const scrollbarWidth = window.innerWidth - root.clientWidth;
        root.setAttribute('data-scroll-locked', '');
        body.style.position = 'fixed';
        body.style.top = `-${lockedScrollY}px`;
        body.style.left = '0';
        body.style.right = '0';
        if (scrollbarWidth > 0) {
            body.style.paddingRight = scrollbarWidth + 'px';
        }
    } else if (! locked && root.hasAttribute('data-scroll-locked')) {
        root.removeAttribute('data-scroll-locked');
        body.style.position = '';
        body.style.top = '';
        body.style.left = '';
        body.style.right = '';
        body.style.paddingRight = '';
        window.scrollTo({ top: lockedScrollY, left: 0, behavior: 'instant' });
    }
};
new MutationObserver(syncScrollLock).observe(document.documentElement, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['data-lock-scroll'],
});
document.addEventListener('livewire:navigated', syncScrollLock);
