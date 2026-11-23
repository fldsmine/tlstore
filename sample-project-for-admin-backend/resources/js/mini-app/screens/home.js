/**
 * Home — mirrors the Livewire Dashboard page: greeting + plan badge,
 * announcements, the wallet summary card, the quick-action tiles, eSIM tier
 * showcase, and the acquisition banner. Data comes from existing endpoints:
 * /announcements, /wallets, /packages plus the session user.
 */

import { api } from '../api.js';
import { el, esc, money, num, dateLabel, errorState, skeleton, appBar } from '../ui.js';
import { navigate } from '../router.js';
import { state } from '../store.js';
import { haptic } from '../telegram.js';

export async function renderHome(view) {
    view.innerHTML = '';
    view.appendChild(appBar('Dashboard', planBadge()));
    view.appendChild(skeleton(5));

    const user = state.user ?? {};

    try {
        const [announcements, wallets, packages] = await Promise.all([
            api('/announcements').catch(() => ({ data: [] })),
            api('/wallets'),
            api('/packages'),
        ]);

        view.innerHTML = '';
        view.appendChild(appBar('Dashboard', planBadge()));

        // Greeting.
        const first = user.first_name ?? 'there';
        view.appendChild(el(`
            <div class="mt-1 mb-4">
                <div class="text-[15px] font-semibold">Hey ${esc(first)} 👋</div>
                <div class="ma-hint text-[0.8rem]">Welcome back to your Nextel workspace.</div>
            </div>`));

        // Announcements (same feed the web dashboard shows).
        const items = Array.isArray(announcements?.data) ? announcements.data : (announcements?.announcements ?? []);
        if (items.length) {
            const track = el(`<div class="no-scrollbar -mx-1 mb-4 flex snap-x snap-mandatory gap-3 overflow-x-auto px-1 pb-1"></div>`);
            items.slice(0, 3).forEach((item, index) => {
                track.appendChild(el(`
                    <div class="ma-card min-w-[85%] snap-center">
                        ${item.kicker ? `<div class="mb-1 text-[0.6rem] font-bold uppercase tracking-[0.18em]" style="color:var(--ma-primary-light)">${esc(item.kicker)}</div>` : ''}
                        <h3 class="ma-heading text-[0.95rem] font-bold leading-snug">${esc(item.title)}</h3>
                        ${item.body ? `<p class="ma-hint mt-1 text-[0.8rem] leading-relaxed">${esc(truncate(item.body, 110))}</p>` : ''}
                        <div class="ma-hint mt-3 flex items-center justify-between text-[0.68rem]">
                            <span>${esc(dateLabel(item.published_at ?? item.created_at))}</span>
                            ${items.length > 1 ? `<span>${index + 1} / ${Math.min(items.length, 3)}</span>` : ''}
                        </div>
                    </div>`));
            });
            view.appendChild(track);
        }

        // Wallet summary card.
        view.appendChild(el(`
            <section class="ma-card mb-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="ma-hint text-[0.64rem] font-semibold uppercase tracking-wide">Total balance</div>
                        <div class="ma-heading mt-0.5 text-[26px] font-bold leading-none">${money(wallets.total_balance_usd)}</div>
                        <div class="ma-hint mt-1.5 text-[0.7rem]">Lifetime earned ${money(wallets.lifetime_earnings_usd)}</div>
                    </div>
                    <button class="ma-btn ma-btn-primary ma-btn-auto mt-1" data-link="withdraw"><i class="ri-bank-line"></i> Withdraw</button>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2.5">
                    ${topWalletTiles(wallets.wallets ?? [])}
                </div>
            </section>`));

        // Quick actions — the web circular tiles.
        const actions = [
            { route: 'plans', icon: 'ri-sim-line', label: 'Activate eSIM' },
            { route: 'vas', icon: 'ri-smartphone-line', label: 'Top-up' },
            { route: 'withdraw', icon: 'ri-bank-line', label: 'Withdraw' },
            { route: 'earn', icon: 'ri-pie-chart-2-line', label: 'Shares' },
            { route: 'website', icon: 'ri-global-line', label: 'My Website' },
            { route: 'numbers', icon: 'ri-phone-line', label: 'Calls & SMS' },
            { route: 'numbers', icon: 'ri-user-heart-line', label: 'Beneficiaries' },
            { route: 'chats', icon: 'ri-customer-service-2-line', label: 'Support' },
        ];

        const grid = el(`<section class="mb-5 grid grid-cols-4 gap-y-4"></section>`);
        actions.forEach((action) => {
            grid.appendChild(el(`
                <button class="ma-quick" data-link="${action.route}" aria-label="${esc(action.label)}">
                    <span class="ma-quick-icon"><i class="${action.icon}"></i></span>
                    <span>${esc(action.label)}</span>
                </button>`));
        });
        view.appendChild(grid);

        // eSIM tiers (web "Shares" showcase).
        const tiers = packages?.packages ?? [];
        if (tiers.length) {
            const section = el(`<section class="mb-4"><h3 class="ma-heading mb-2 text-base font-bold">eSIM plans</h3></section>`);
            const scroller = el(`<div class="no-scrollbar -mx-1 flex gap-3 overflow-x-auto px-1 pb-1"></div>`);
            tiers.forEach((tier) => {
                scroller.appendChild(el(`
                    <div class="ma-card min-w-[215px]">
                        <div class="flex items-center justify-between gap-2">
                            <span class="ma-chip ma-chip-muted">${esc(tier.name)}</span>
                            ${tier.is_featured ? '<span class="ma-chip ma-chip-accent">Popular</span>' : ''}
                        </div>
                        <div class="ma-heading mt-2 text-xl font-bold">${money(tier.price_usd)}</div>
                        <ul class="ma-hint mt-2 space-y-1 text-[0.72rem]">
                            <li><i class="ri-phone-line mr-1"></i>${num(tier.daily_call_minutes)} min/day to other networks</li>
                            <li><i class="ri-cloud-line mr-1"></i>${tier.cloud_calls ? 'Cloud calls' : 'Standard calls'}${tier.cloud_sms ? ' + SMS' : ''}</li>
                            <li><i class="ri-gift-line mr-1"></i>${money(tier.direct_referral_bonus_usd)} referral bonus</li>
                        </ul>
                        <button class="ma-btn ma-btn-accent mt-3" data-link="plans">Get this plan</button>
                    </div>`));
            });
            section.appendChild(scroller);
            view.appendChild(section);
        }

        // Acquisition banner (web hero).
        view.appendChild(el(`
            <section class="rounded-[20px] p-5 text-white" style="background:var(--ma-primary)">
                <h3 class="ma-heading text-lg font-bold leading-snug">Get your Nextel number today.</h3>
                <p class="mt-1 text-[0.82rem] text-white/70">Free calls on Nextel and generous minutes to every other network.</p>
                <button class="mt-3 inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-[0.85rem] font-bold"
                        style="background:var(--ma-secondary);color:var(--ma-primary)" data-link="plans">
                    Buy eSIM plan <i class="ri-arrow-right-up-line"></i>
                </button>
            </section>`));
    } catch (error) {
        view.innerHTML = '';
        view.appendChild(appBar('Dashboard', planBadge()));
        view.appendChild(errorState(errText(error), () => renderHome(view)));
        return;
    }

    view.addEventListener('click', (event) => {
        const link = event.target.closest('[data-link]');
        if (!link) return;
        haptic('light');
        navigate(link.dataset.link);
    });
}

function planBadge() {
    const pk = state.user?.package;
    return pk ? `<i class="ri-medal-line"></i> ${esc(pk.name)}` : null;
}

function topWalletTiles(wallets) {
    const top = [...wallets].sort((a, b) => (b.balance_usd ?? 0) - (a.balance_usd ?? 0)).slice(0, 2);
    if (!top.length) return '';
    return top.map((wallet) => `
        <div class="rounded-[14px] border border-black/5 bg-white p-3">
            <div class="ma-hint truncate text-[0.62rem] font-semibold uppercase tracking-wide">${esc(wallet.label)}</div>
            <div class="mt-0.5 truncate text-[0.95rem] font-bold">${money(wallet.balance_usd)}</div>
        </div>`).join('');
}

function truncate(text, max) {
    if (!text || text.length <= max) return text ?? '';
    return `${text.slice(0, max - 1)}…`;
}

function errText(error) {
    return error?.message ?? 'Something went wrong. Please try again.';
}
