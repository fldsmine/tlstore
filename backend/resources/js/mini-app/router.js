/**
 * Featherweight state router for the Mini App shell.
 * Screens register a render function; navigation swaps the view container,
 * updates the tab bar, and drives the native Telegram BackButton.
 */

import { setBackButton } from './telegram.js';
import { api } from './api.js';

/**
 * Mini App → Livewire handoff. Establishes a real web session for the
 * Sanctum-authenticated user, then navigates the WebView to the full
 * dashboard so the Livewire views render exactly as on the web.
 * Deep-link params map onto dashboard pages.
 */
const WEB_PATHS = {
    home: '/dashboard',
    earn: '/dashboard/earnings',
    services: '/dashboard',
    wallet: '/dashboard/transactions',
    profile: '/dashboard/profile',
    alerts: '/cloud/inbox',
    referrals: '/dashboard/invite',
    numbers: '/dashboard/beneficiaries',
    website: '/dashboard/my-website',
    vas: '/dashboard/vas',
    withdraw: '/dashboard/withdraw',
    vendor: '/dashboard/agent',
    chats: '/dashboard/agent/chats',
};

export async function handoffToWeb(param = null) {
    try {
        await api('/web-session', { method: 'POST' });
    } catch {
        // Session mint failed — navigate anyway; the web app handles auth.
    }

    window.location.assign(WEB_PATHS[param] ?? '/dashboard');
}

const screens = new Map();
let view = null;
let activeName = null;

export function setView(node) {
    view = node;
}

/**
 * `chrome: false` screens (login / register / forgot / verify) render
 * full-bleed without the bottom tab bar.
 */
export function register(name, { render, tab = false, chrome = true }) {
    screens.set(name, { render, tab, chrome });
}

export function currentRoute() {
    return activeName;
}

export async function navigate(name, params = {}) {
    const screen = screens.get(name);
    if (!screen || !view) return;

    activeName = name;

    const tabbar = document.querySelector('.ma-tabbar');
    if (tabbar) tabbar.style.display = screen.chrome ? '' : 'none';
    view.classList.toggle('ma-view-bare', !screen.chrome);

    document.querySelectorAll('.ma-tab').forEach((node) => {
        node.classList.toggle('is-active', node.dataset.route === name);
    });

    view.innerHTML = '';
    window.scrollTo(0, 0);

    try {
        await screen.render(view, params);
    } catch (error) {
        // A render failure must never leave the user with a dead screen.
        console.error('[mini-app] render failed:', error);
        view.innerHTML = `
            <div class="ma-empty">
                <i class="ri-error-warning-line"></i>
                <div class="font-semibold">Something went wrong</div>
                <div class="mt-1 text-sm">${String(error?.message ?? error)}</div>
                <button class="ma-btn ma-btn-primary mt-4" style="max-width:12rem;margin-left:auto;margin-right:auto" data-retry>Try again</button>
            </div>`;
        view.querySelector('[data-retry]').addEventListener('click', () => navigate(name, params));
    }
}

/** Native back support for sub-screens (ads, rewards, referrals…). */
export function enableBack(fallback = 'home') {
    setBackButton(() => navigate(fallback));
}

export function disableBack() {
    setBackButton(null);
}
