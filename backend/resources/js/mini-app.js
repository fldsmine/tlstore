/**
 * Nextel Telegram Mini App — entry point.
 *
 * A dedicated mobile shell over the existing /api/v1 API, styled to match the
 * Livewire dashboard (same brand tokens, same page structure). No business
 * rules live here — everything is computed by the Laravel backend.
 *
 * Auth flow:
 *   1. Existing token → validate via GET /user.
 *   2. Else exchange Telegram initData (POST /telegram/auth):
 *        linked   → bearer token, straight in;
 *        unlinked → login screen; after password login the app links the
 *                   Telegram identity (POST /telegram/link) automatically.
 */

import { initTelegram, initData, startParam, haptic } from './mini-app/telegram.js';
import { api, getToken, setToken, onUnauthorized, clearSession } from './mini-app/api.js';
import { register, navigate, setView, disableBack, handoffToWeb } from './mini-app/router.js';
import { setUser } from './mini-app/store.js';

import { renderHome } from './mini-app/screens/home.js';
import { renderEarnings } from './mini-app/screens/earnings.js';
import { renderServices } from './mini-app/screens/services.js';
import { renderWallet } from './mini-app/screens/wallet.js';
import { renderProfile } from './mini-app/screens/profile.js';
import { renderAlerts } from './mini-app/screens/alerts.js';
import { renderAds } from './mini-app/screens/ads.js';
import { renderRewards } from './mini-app/screens/rewards.js';
import { renderReferrals } from './mini-app/screens/referrals.js';
import { renderLogin } from './mini-app/screens/login.js';
import { renderRegister } from './mini-app/screens/register.js';
import { renderForgot } from './mini-app/screens/forgot.js';
import { renderVerify } from './mini-app/screens/verify.js';
import { renderPlans } from './mini-app/screens/plans.js';
import { renderPayLater } from './mini-app/screens/paylater.js';
import { renderLoan } from './mini-app/screens/loan.js';
import { renderVas } from './mini-app/screens/vas.js';
import { renderWebsite } from './mini-app/screens/website.js';
import { renderChats } from './mini-app/screens/chats.js';
import { renderNumbers } from './mini-app/screens/numbers.js';
import { renderSettings } from './mini-app/screens/settings.js';
import { renderVendor } from './mini-app/screens/vendor.js';

// Mirrors the web sidebar sections inside a 5-tab mobile shell.
const TABS = [
    { route: 'home', icon: 'ri-home-5-line', label: 'Home' },
    { route: 'earn', icon: 'ri-money-dollar-circle-line', label: 'Earn' },
    { route: 'services', icon: 'ri-apps-2-line', label: 'Services' },
    { route: 'wallet', icon: 'ri-wallet-3-line', label: 'Wallet' },
    { route: 'profile', icon: 'ri-user-3-line', label: 'Profile' },
];

boot();

async function boot() {
    initTelegram();

    register('home', { render: wrapTab(renderHome), tab: true });
    register('earn', { render: wrapTab(renderEarnings), tab: true });
    register('services', { render: wrapTab(renderServices), tab: true });
    register('wallet', { render: wrapTab(renderWallet), tab: true });
    register('profile', { render: wrapTab(renderProfile), tab: true });
    register('alerts', { render: renderAlerts });
    register('ads', { render: renderAds });
    register('rewards', { render: renderRewards });
    register('referrals', { render: renderReferrals });
    register('plans', { render: renderPlans });
    register('paylater', { render: renderPayLater });
    register('loan', { render: renderLoan });
    register('vas', { render: renderVas });
    register('website', { render: renderWebsite });
    register('chats', { render: renderChats });
    register('withdraw', {
        render: (view) => {
            view.innerHTML = `
                <div class="ma-empty">
                    <i class="ri-loader-4-line animate-spin"></i>
                    <div>Opening withdrawal page…</div>
                </div>`;
            return handoffToWeb('withdraw');
        },
    });
    register('numbers', { render: renderNumbers });
    register('settings', { render: renderSettings });
    register('vendor', { render: renderVendor });
    register('login', { render: renderLogin, chrome: false });
    register('register', { render: renderRegister, chrome: false });
    register('forgot', { render: renderForgot, chrome: false });
    register('verify', { render: renderVerify, chrome: false });

    const root = document.getElementById('mini-app');
    root.innerHTML = `
        <main id="view" class="ma-view"></main>
        <nav class="ma-tabbar" id="tabbar" aria-label="Navigation">
            ${TABS.map((tab) => `
                <button class="ma-tab" data-route="${tab.route}" aria-label="${tab.label}">
                    <i class="${tab.icon}"></i>
                    <span>${tab.label}</span>
                </button>`).join('')}
        </nav>`;
    root.removeAttribute('aria-busy');

    setView(root.querySelector('#view'));

    root.querySelector('#tabbar').addEventListener('click', (event) => {
        const tab = event.target.closest('.ma-tab');
        if (!tab) return;
        haptic('light');
        navigate(tab.dataset.route);
    });

    onUnauthorized(() => navigate('login'));

    // Logout from the Livewire app redirects here — drop the stored token
    // and show the login screen without the automatic Telegram sign-in.
    if (new URLSearchParams(window.location.search).has('logged_out')) {
        clearSession();
        return navigate('login');
    }

    await authenticate();
}

/** Screens shown above the tab bar hide the native BackButton. */
function wrapTab(render) {
    return async (view, params) => {
        disableBack();
        await render(view, params);
    };
}

async function authenticate() {
    // 1. Existing Mini App session? → straight into the Livewire dashboard.
    if (getToken()) {
        try {
            await api('/user');
            return handoffToWeb(startParam());
        } catch {
            /* fall through to Telegram exchange */
        }
    }

    // 2. Telegram initData exchange.
    const payload = initData();

    if (payload) {
        try {
            const data = await api('/telegram/auth', { method: 'POST', auth: false, body: { init_data: payload } });

            if (data?.linked && data?.token) {
                setToken(data.token);
                setUser(data.user);
                return handoffToWeb(startParam());
            }

            const name = data?.telegram_user?.first_name ?? null;
            return navigate('login', { telegramName: name });
        } catch {
            return navigate('login');
        }
    }

    // 3. Outside Telegram (plain browser preview) — plain login.
    return navigate('login');
}


