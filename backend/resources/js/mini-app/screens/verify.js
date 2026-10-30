/**
 * Account verification. Two channels:
 *  - telegram: the code was DM'd by the bot; the app auto-confirms with the
 *    same Telegram session (POST /telegram/verify) — retrying briefly — and
 *    falls back to manual code entry.
 *  - email: classic 6-digit code entry (POST /email/verify) with resend.
 */

import { api } from '../api.js';
import { el, esc, toast, errMsg } from '../ui.js';
import { authShell } from '../auth-ui.js';
import { initData, haptic } from '../telegram.js';
import { navigate, enableBack, handoffToWeb } from '../router.js';
import { setUser } from '../store.js';

export async function renderVerify(view, params = {}) {
    enableBack('login');
    const email = params?.email ?? '';
    const channel = params?.channel === 'telegram' && initData() ? 'telegram' : 'email';

    view.innerHTML = '';

    const shell = authShell({
        title: 'Verify Account',
        subtitle: channel === 'telegram'
            ? `We sent a code to your Telegram${email ? ` (and to ${email})` : ''}.`
            : `We sent a 6-digit code to ${email || 'your inbox'}.`,
        backTo: 'login',
        centered: true,
    });

    view.appendChild(shell);
    const body = shell.querySelector('.ma-auth-body');

    // Telegram auto-verification banner.
    if (channel === 'telegram') {
        const banner = el(`
            <div class="ma-card ma-card-tight mb-4 flex items-center gap-3">
                <div class="ma-spinner" data-spin></div>
                <div class="min-w-0 flex-1">
                    <div class="text-[0.82rem] font-semibold" data-title>Verifying via Telegram…</div>
                    <div class="ma-hint text-[0.7rem]" data-sub>Confirming this session received the code.</div>
                </div>
                <span class="ma-icon" style="width:2.4rem;height:2.4rem"><i class="ri-telegram-line"></i></span>
            </div>`);
        body.appendChild(banner);
        autoVerify(banner);
    }

    // Manual code entry (fallback for both channels).
    const field = el(`
        <div class="mb-4">
            <label class="mb-1.5 block text-[0.85rem] font-medium">Verification code</label>
            <div class="relative">
                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-[1.05rem]" style="color:var(--ma-muted)"><i class="ri-shield-keyhole-line"></i></span>
                <input class="ma-input ma-pill text-center text-xl tracking-[0.4em]" name="code" inputmode="numeric" maxlength="6" placeholder="••••••" style="padding-left:2.9rem">
            </div>
            <div class="ma-hint mt-1.5 text-center text-[0.7rem]">${channel === 'telegram' ? 'The code is in your Telegram messages.' : 'The code is in your email inbox.'}</div>
        </div>`);
    body.appendChild(field);

    const input = field.querySelector('input');
    input.addEventListener('input', () => {
        input.value = input.value.replace(/\D/g, '').slice(0, 6);
    });

    const button = el('<button class="ma-btn ma-btn-primary" style="border-radius:16px"><i class="ri-mail-check-line"></i> Verify</button>');
    body.appendChild(button);

    const resend = el('<button class="ma-btn ma-btn-ghost ma-btn-auto mx-auto mt-3 block"><i class="ri-refresh-line"></i> Resend code</button>');
    body.appendChild(resend);

    button.addEventListener('click', async () => {
        const code = input.value.trim();
        if (code.length !== 6) return toast('Enter the 6-digit code.', 'error');

        button.disabled = true;
        button.textContent = 'Verifying…';

        try {
            await api('/email/verify', { method: 'POST', body: { code } });
            haptic('success');
            toast('Verified — welcome aboard! 🎉', 'success', 3500);
            await enterApp();
        } catch (error) {
            toast(errMsg(error), 'error');
            button.disabled = false;
            button.innerHTML = '<i class="ri-mail-check-line"></i> Verify';
        }
    });

    resend.addEventListener('click', async () => {
        resend.disabled = true;
        try {
            await api('/email/resend', { method: 'POST' });
            toast('A new code has been sent.', 'success');
        } catch (error) {
            toast(errMsg(error), 'error');
            resend.disabled = false;
        }
    });

    async function enterApp() {
        try {
            const data = await api('/user');
            setUser(data.user ?? null);
        } catch {
            /* session is already authenticated; best-effort refresh */
        }
        await handoffToWeb();
    }

    /** Try POST /telegram/verify a few times; degrade to manual on failure. */
    async function autoVerify(banner) {
        const title = banner.querySelector('[data-title]');
        const sub = banner.querySelector('[data-sub]');

        for (let attempt = 1; attempt <= 3; attempt++) {
            try {
                const result = await api('/telegram/verify', { method: 'POST', body: { init_data: initData() } });

                if (result?.verified) {
                    haptic('success');
                    if (result.user) setUser(result.user);
                    toast('Verified via Telegram 🎉', 'success', 3000);
                    return handoffToWeb();
                }
            } catch {
                /* not yet — retry shortly */
            }

            if (attempt < 3) {
                sub.textContent = `Waiting for the Telegram message… (attempt ${attempt + 1} of 3)`;
                await new Promise((resolve) => setTimeout(resolve, 4000));
            }
        }

        banner.querySelector('[data-spin]').outerHTML = '<i class="ri-error-warning-line text-lg" style="color:var(--ma-warning)"></i>';
        title.textContent = 'Auto-verify unavailable';
        sub.textContent = 'Enter the code from your Telegram message below.';
    }
}
