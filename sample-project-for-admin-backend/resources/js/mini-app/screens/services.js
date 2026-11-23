/**
 * Services hub — mirrors the web sidebar "Services" section (Shares/Plans,
 * Pay Later, Loan, VAS) plus the website and live-chat entry points.
 * Debit-card issuance stays web-only (external iframe flow).
 */

import { el, esc, appBar } from '../ui.js';
import { navigate } from '../router.js';
import { state } from '../store.js';
import { haptic } from '../telegram.js';

const ITEMS = [
    {
        route: 'plans',
        icon: 'ri-sim-line',
        title: 'Shares & Plans',
        desc: 'eSIM tiers, activation and upgrade tokens',
    },
    {
        route: 'paylater',
        icon: 'ri-timer-flash-line',
        title: 'Pay Later',
        desc: 'Activate minutes now, settle with a token',
    },
    {
        route: 'loan',
        icon: 'ri-hand-coin-line',
        title: 'Loan',
        desc: 'Request float and track repayments',
    },
    {
        route: 'vas',
        icon: 'ri-shopping-bag-3-line',
        title: 'VAS & Bills',
        desc: 'Airtime, data, TV and electricity top-ups',
    },
    {
        route: 'website',
        icon: 'ri-global-line',
        title: 'My Website',
        desc: 'Renew your Nextel website subscription',
    },
    {
        route: 'chats',
        icon: 'ri-customer-service-2-line',
        title: 'Live Chats',
        desc: 'Talk to your vendor or Nextel support',
    },
];

export async function renderServices(view) {
    const user = state.user ?? {};

    view.innerHTML = '';
    view.appendChild(appBar('Services'));

    const list = el(`<div class="space-y-3"></div>`);

    ITEMS.forEach((item) => {
        list.appendChild(el(`
            <button class="ma-card flex w-full items-center gap-3 text-left" data-route="${item.route}">
                <span class="ma-icon"><i class="${item.icon}"></i></span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-bold">${esc(item.title)}</span>
                    <span class="ma-hint block truncate text-[0.75rem]">${esc(item.desc)}</span>
                </span>
                <i class="ri-arrow-right-s-line text-lg" style="color:var(--ma-muted)"></i>
            </button>`));
    });

    view.appendChild(list);

    if (user.is_agent) {
        const vendor = el(`
            <button class="ma-card mt-3 flex w-full items-center gap-3 text-left" data-route="vendor"
                    style="background:var(--ma-primary);color:#fff">
                <span class="ma-icon" style="background:rgba(255,255,255,0.12);border-color:transparent;color:var(--ma-secondary)"><i class="ri-vip-crown-line"></i></span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-bold">Vendor Dashboard</span>
                    <span class="block truncate text-[0.75rem] text-white/60">Mint tokens, manage batches and float</span>
                </span>
                <i class="ri-arrow-right-s-line text-lg text-white/60"></i>
            </button>`);
        view.appendChild(vendor);
    }

    view.appendChild(el(`
        <p class="ma-hint mt-4 text-center text-[0.72rem]">
            Debit cards are issued on the web dashboard.<br>Everything else is right here.
        </p>`));

    view.addEventListener('click', (event) => {
        const card = event.target.closest('[data-route]');
        if (!card) return;
        haptic('light');
        navigate(card.dataset.route);
    });
}
