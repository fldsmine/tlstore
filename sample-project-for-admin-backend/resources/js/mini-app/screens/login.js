/**
 * Login — "Welcome Back" onboarding-styled screen. Password login binds the
 * Telegram identity on success (instant open next time); the peach quick
 * button performs a one-tap Telegram sign-in when this account is already
 * linked. "Sign up" opens the in-app registration flow.
 */

import { api, ApiError, setToken } from '../api.js';
import { el, toast, errMsg } from '../ui.js';
import { authShell, pillField, checkRow } from '../auth-ui.js';
import { initData, telegramUser, haptic } from '../telegram.js';
import { navigate, handoffToWeb } from '../router.js';
import { setUser } from '../store.js';

let navWired = false;

/** Delegated [data-nav] clicks, wired once for the whole session. */
function wireNav(view) {
    if (navWired) return;
    navWired = true;

    view.addEventListener('click', (event) => {
        const target = event.target.closest('[data-nav]');
        if (!target) return;
        haptic('light');
        navigate(target.dataset.nav);
    });
}

export async function renderLogin(view, params = {}) {
    const pendingName = params?.telegramName ?? telegramUser()?.first_name ?? null;

    view.innerHTML = '';

    const shell = authShell({
        title: 'Welcome Back',
        subtitle: pendingName
            ? `Telegram: ${pendingName} — log in once to link this account.`
            : 'Login to continue to your Nextel account',
    });

    view.appendChild(shell);
    const body = shell.querySelector('.ma-auth-body');

    body.appendChild(pillField({
        label: 'Email or username',
        name: 'login',
        icon: 'ri-user-3-line',
        placeholder: 'Email or username',
    }));

    body.appendChild(pillField({
        label: 'Password',
        name: 'password',
        icon: 'ri-lock-2-line',
        type: 'password',
        placeholder: 'Password',
    }));

    const row = el(`
        <div class="mt-1 flex items-center justify-between">
            <span data-slot-check></span>
            <button type="button" class="ma-linkbtn" data-nav="forgot">Forgot Password?</button>
        </div>`);
    row.querySelector('[data-slot-check]').replaceWith(checkRow('Remember Me', 'remember'));
    body.appendChild(row);

    const buttons = document.createElement('div');
    buttons.className = 'mt-6 flex gap-3';
    buttons.innerHTML = `
        <button type="button" class="ma-btn ma-btn-primary" data-login style="border-radius:16px">Login</button>
        <button type="button" class="ma-btn ma-btn-peach ma-btn-auto" data-telegram style="border-radius:16px">
            <i class="ri-fingerprint-2-line"></i> Telegram
        </button>`;
    body.appendChild(buttons);

    body.appendChild(el(`
        <p class="mt-5 text-center text-[0.85rem]" style="color:var(--ma-muted)">
            Don't have an account?
            <button type="button" class="ma-linkbtn" data-nav="register">Sign up</button>
        </p>`));

    // Delegated navigation — survives any inner re-render or overlay quirks.
    wireNav(view);

    buttons.querySelector('[data-telegram]').addEventListener('click', async () => {
        const payload = initData();

        if (!payload) {
            return toast('One-tap sign-in works inside the Telegram app.', 'info', 3000);
        }

        haptic('light');
        const button = buttons.querySelector('[data-telegram]');
        button.disabled = true;

        try {
            const data = await api('/telegram/auth', { method: 'POST', auth: false, body: { init_data: payload } });

            if (data?.linked && data?.token) {
                setToken(data.token, true);
                setUser(data.user);
                haptic('success');
                return handoffToWeb();
            }

            toast('This Telegram account isn’t linked yet — log in below once.', 'info', 3500);
        } catch (error) {
            toast(errMsg(error), 'error');
        } finally {
            button.disabled = false;
        }
    });

    buttons.querySelector('[data-login]').addEventListener('click', async () => {
        const login = body.querySelector('[name="login"]').value.trim();
        const password = body.querySelector('[name="password"]').value;
        const remember = body.querySelector('[name="remember"]').checked;

        if (!login || !password) {
            return toast('Enter your email/username and password.', 'error');
        }

        const button = buttons.querySelector('[data-login]');
        button.disabled = true;
        button.textContent = 'Logging in…';

        try {
            const data = await api('/login', {
                method: 'POST',
                auth: false,
                body: { login, password, device_name: 'Telegram Mini App' },
            });

            setToken(data.token, remember);
            setUser(data.user);

            await linkTelegramQuietly();
            haptic('success');
            await handoffToWeb();
        } catch (error) {
            toast(error instanceof ApiError ? (error.fieldMessage() ?? error.message) : errMsg(error), 'error');
            button.disabled = false;
            button.textContent = 'Login';
        }
    });
}

/** Best-effort link; failures surface later in Settings, never block login. */
async function linkTelegramQuietly() {
    const payload = initData();
    if (!payload) return;

    try {
        await api('/telegram/link', { method: 'POST', body: { init_data: payload } });
    } catch {
        /* already linked elsewhere or initData consumed — non-fatal */
    }
}
