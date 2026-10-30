/**
 * Earnings hub — the web's Earnings page (today's claimable call pool via
 * GET /earnings + POST /earnings/{id}/claim or /earnings/claim-all) and the Shareholder Portal
 * (GET /earnings/portal: per-call-type pool breakdown), switchable with a
 * segmented control. Business rules stay server-side in ClaimService.
 */

import { api } from '../api.js';
import { el, esc, money, num, skeleton, errorState, emptyState, toast, when, errMsg, appBar } from '../ui.js';
import { navigate } from '../router.js';
import { haptic } from '../telegram.js';

export async function renderEarnings(view, params = {}) {
    const tab = params?.tab === 'portal' ? 'portal' : 'today';

    view.innerHTML = '';
    view.appendChild(appBar('Earnings'));

    const switcher = el(`
        <div class="mb-4 grid grid-cols-2 gap-1 rounded-2xl p-1" style="background:color-mix(in srgb, var(--ma-muted) 14%, transparent)">
            <button data-tab="today" class="rounded-xl py-2 text-[0.8rem] font-semibold">Daily claims</button>
            <button data-tab="portal" class="rounded-xl py-2 text-[0.8rem] font-semibold">Shareholder Portal</button>
        </div>`);

    const mark = (active) => {
        switcher.querySelectorAll('[data-tab]').forEach((button) => {
            const isActive = button.dataset.tab === active;
            button.style.background = isActive ? 'var(--ma-surface)' : 'transparent';
            button.style.color = isActive ? 'var(--ma-primary)' : 'var(--ma-muted)';
        });
    };

    const panel = el('<div></div>');

    const show = async (active) => {
        mark(active);
        haptic('light');
        if (active === 'portal') await loadPortal(panel);
        else await loadToday(panel);
    };

    switcher.addEventListener('click', (event) => {
        const button = event.target.closest('[data-tab]');
        if (button) show(button.dataset.tab);
    });

    view.appendChild(switcher);
    view.appendChild(panel);

    // The web sidebar's remaining Earnings/Account shortcuts.
    const links = el(`
        <div class="ma-card mt-4 space-y-1">
            <button class="ma-row w-full" data-go="rewards">
                <span class="ma-icon"><i class="ri-trophy-line"></i></span>
                <span class="min-w-0 flex-1 text-left">
                    <span class="block truncate text-sm font-semibold">Rewards</span>
                    <span class="ma-hint block truncate text-[0.72rem]">Consistency targets and payouts</span>
                </span>
                <i class="ri-arrow-right-s-line text-lg" style="color:var(--ma-muted)"></i>
            </button>
            <button class="ma-row w-full" data-go="ads">
                <span class="ma-icon"><i class="ri-megaphone-line"></i></span>
                <span class="min-w-0 flex-1 text-left">
                    <span class="block truncate text-sm font-semibold">Ads</span>
                    <span class="ma-hint block truncate text-[0.72rem]">Share campaigns for extra earnings</span>
                </span>
                <i class="ri-arrow-right-s-line text-lg" style="color:var(--ma-muted)"></i>
            </button>
            <button class="ma-row w-full" data-go="referrals">
                <span class="ma-icon"><i class="ri-team-line"></i></span>
                <span class="min-w-0 flex-1 text-left">
                    <span class="block truncate text-sm font-semibold">My Team</span>
                    <span class="ma-hint block truncate text-[0.72rem]">Invite rewards and downline</span>
                </span>
                <i class="ri-arrow-right-s-line text-lg" style="color:var(--ma-muted)"></i>
            </button>
        </div>`);

    links.addEventListener('click', (event) => {
        const link = event.target.closest('[data-go]');
        if (!link) return;
        haptic('light');
        navigate(link.dataset.go);
    });

    view.appendChild(links);
    await show(tab);
}

/* ── Daily claims (web Earnings page) ─────────────────────────────── */

async function loadToday(panel) {
    panel.innerHTML = '';
    panel.appendChild(skeleton(5));

    let data;

    try {
        data = await api('/earnings');
    } catch (error) {
        panel.innerHTML = '';
        panel.appendChild(errorState(errMsg(error), () => loadToday(panel)));
        return;
    }

    panel.innerHTML = `
        ${data.usage_verified === false ? '<div class="ma-card mb-3 text-[0.72rem]" style="color:var(--ma-muted)"><i class="ri-information-line mr-1"></i>The daily call pool is not independently verified against carrier records.</div>' : ''}
        <div class="grid grid-cols-3 gap-2">
            ${statCard('Today', money(data.today_earnings_usd))}
            ${statCard('Claimed', money(data.claimed_today_usd))}
            ${statCard('Unclaimed', money(data.unclaimed_usd))}
        </div>
        <div class="ma-card mt-3 flex items-center justify-between text-sm">
            <span class="ma-muted">Share minutes left this term</span>
            <b>${num(data.share_minutes_remaining)} / ${num(data.share_term_allocated_minutes)}</b>
        </div>
        <div class="ma-hint mt-2 text-center text-[0.7rem]">Available to claim now: ${num(data.share_minutes_available_now)} minutes · unused accrued minutes roll forward.</div>
        <div class="ma-hint mt-2 text-center text-[0.7rem]">Daily cap remaining: ${money(data.daily_earning_cap_remaining_usd)} of ${money(data.daily_earning_cap_usd)}</div>
        ${Number(data.unclaimed_usd ?? 0) > 0 ? '<button data-claim-all class="ma-btn ma-btn-primary mt-3 w-full">Claim all available</button>' : ''}
        <div id="records" class="mt-3"></div>`;

    const claimAllButton = panel.querySelector('[data-claim-all]');
    claimAllButton?.addEventListener('click', async () => {
        claimAllButton.disabled = true;
        claimAllButton.textContent = 'Claiming…';

        try {
            const result = await api('/earnings/claim-all', { method: 'POST' });
            const amount = Number(result?.amount_usd ?? 0);
            if (amount > 0) {
                haptic('success');
                const count = Number(result.records_claimed ?? 0);
                toast(`Claimed ${money(amount)} across ${count} call${count === 1 ? '' : 's'} 🎉`, 'success');
            } else {
                toast('No eligible call earnings were available to claim.', 'info');
            }
            await loadToday(panel);
        } catch (error) {
            toast(errMsg(error), 'error');
            claimAllButton.disabled = false;
            claimAllButton.textContent = 'Claim all available';
        }
    });

    const list = panel.querySelector('#records');
    const records = data.records ?? [];

    if (!records.length) {
        list.appendChild(emptyState('ri-phone-line', 'No pool records yet', 'Generated daily-pool entries will appear here. They are not independently verified carrier usage.'));
        return;
    }

    records.forEach((record) => list.appendChild(buildRow(record, panel)));
}

function statCard(label, value) {
    return `<div class="ma-card ma-card-tight text-center"><div class="ma-hint text-[0.65rem] uppercase">${esc(label)}</div><div class="mt-0.5 text-sm font-bold">${value}</div></div>`;
}

function buildRow(record, panel) {
    const claimed = record.claimed;
    const claimable = record.can_claim;
    const blockedLabels = {
        already_claimed: 'Already claimed',
        share_plan_inactive: 'Active share plan required',
        call_type_not_in_package: 'Not in your package',
        term_minutes_exhausted: 'No share minutes left',
        share_minutes_not_accrued: 'Next share-minute allowance has not accrued',
        no_claimable_amount: 'No claimable amount',
        daily_earning_cap: 'No claim fits the remaining daily cap',
        call_record_expired: 'Record expired',
    };
    const blockedLabel = blockedLabels[record.blocked_reason] ?? 'Cannot claim right now';
    const duration = Number(record.duration_minutes ?? 0);
    const creditedMinutes = Number(claimed ? record.claimed_minutes ?? 0 : record.claimable_minutes ?? 0);
    const partial = creditedMinutes > 0 && creditedMinutes + 0.001 < duration;
    const durationLabel = partial
        ? ` · partial ${num(creditedMinutes)} of ${num(duration)} min`
        : (duration > 0 ? ` · ${num(duration)} min` : '');

    const row = el(`
        <div class="ma-row">
            <div class="ma-icon"><i class="ri-phone-fill"></i></div>
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-semibold">${esc(record.type)} · ${esc(record.network ?? '')}</div>
                <div class="ma-hint text-xs">${esc(when(record.occurred_at))}${durationLabel}${claimed ? ' · claimed' : ''}</div>
            </div>
            <div class="text-right">
                <div class="text-sm font-bold">${money(record.amount_usd)}</div>
            </div>
        </div>`);

    if (!claimed) {
        const button = el(`<button class="ma-btn ${claimable ? 'ma-btn-primary' : ''} ma-btn-sm mt-2" ${claimable ? '' : 'disabled'}>
            ${claimable ? 'Claim' : esc(blockedLabel)}
        </button>`);

        if (claimable) {
            button.addEventListener('click', async () => {
                button.disabled = true;
                button.textContent = 'Claiming…';

                try {
                    const result = await api(`/earnings/${record.id}/claim`, { method: 'POST' });
                    haptic('success');
                    const isPartial = Number(result.claimed_minutes ?? 0) + 0.001 < Number(result.pool_duration_minutes ?? 0);
                    const detail = isPartial
                        ? ` for ${num(result.claimed_minutes)} of ${num(result.pool_duration_minutes)} min`
                        : '';
                    toast(`Claimed ${money(result.amount_usd)}${detail} 🎉`, 'success');
                    await loadToday(panel);
                } catch (error) {
                    toast(errMsg(error), 'error');
                    button.disabled = false;
                    button.textContent = 'Claim';
                }
            });
        }

        const wrapper = el('<div class="mb-1"></div>');
        wrapper.appendChild(row);
        wrapper.appendChild(button);
        return wrapper;
    }

    return row;
}

/* ── Shareholder Portal (web Shareholder page) ────────────────────── */

async function loadPortal(panel) {
    panel.innerHTML = '';
    panel.appendChild(skeleton(5));

    let data;

    try {
        data = await api('/earnings/portal');
    } catch (error) {
        panel.innerHTML = '';
        panel.appendChild(errorState(errMsg(error), () => loadPortal(panel)));
        return;
    }

    panel.innerHTML = `
        ${data.usage_verified === false ? '<div class="ma-card mb-3 text-[0.72rem]" style="color:var(--ma-muted)"><i class="ri-information-line mr-1"></i>The daily call pool is not independently verified against carrier records.</div>' : ''}
        <div class="ma-card mb-3">
            <div class="flex items-center justify-between">
                <div>
                    <div class="ma-hint text-[0.64rem] font-semibold uppercase tracking-wide">Claimable now</div>
                    <div class="ma-heading mt-0.5 text-2xl font-bold">${money(data.total_claimable_usd)}</div>
                </div>
                <div class="text-right">
                    <div class="ma-hint text-[0.64rem] uppercase">Claimed today</div>
                    <div class="text-sm font-bold" style="color:var(--ma-success)">${money(data.total_claimed_usd)}</div>
                </div>
            </div>
            <div class="ma-hint mt-3 flex items-center justify-between text-[0.72rem]">
                <span><i class="ri-time-line mr-1"></i>${num(data.share_minutes_remaining)} of ${num(data.share_term_allocated_minutes)} share minutes left this term</span>
                ${data.package_name ? `<span class="ma-chip ma-chip-accent">${esc(data.package_name)}</span>` : ''}
            </div>
            <div class="ma-hint mt-1 text-[0.68rem]">Available to claim now: ${num(data.share_minutes_available_now)} minutes</div>
            <div class="ma-hint mt-1 text-[0.68rem]">Partial claims use the remaining share minutes or daily-cap room, with payouts reduced proportionally.</div>
        </div>
        <div id="portal-cards"></div>
        <p class="ma-hint mt-3 text-center text-[0.72rem]">Claim individual calls from the Daily claims tab.</p>`;

    const list = panel.querySelector('#portal-cards');
    const cards = data.cards ?? [];

    if (!cards.length) {
        list.appendChild(emptyState('ri-pie-chart-2-line', 'No call types yet', 'Pool activity appears here once calls flow.'));
        return;
    }

    cards.forEach((card) => {
        list.appendChild(el(`
            <div class="ma-card mb-3">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <span class="ma-icon"><i class="ri-phone-box-line"></i></span>
                        <div class="min-w-0">
                            <div class="truncate text-sm font-bold">${esc(card.name)}</div>
                            <div class="ma-hint text-[0.7rem]">${card.in_package ? `In package · ${num(card.share_percent)}% share` : `Not in your package · ${num(card.share_percent)}% pool share`}</div>
                        </div>
                    </div>
                    ${card.in_package ? '<span class="ma-chip ma-chip-success">Yours</span>' : '<span class="ma-chip ma-chip-muted">Pool</span>'}
                </div>
                <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-[12px] border border-black/5 bg-white p-2.5">
                        <div class="text-sm font-bold">${card.calls}</div>
                        <div class="ma-hint text-[0.62rem]">Calls</div>
                    </div>
                    <div class="rounded-[12px] border border-black/5 bg-white p-2.5">
                        <div class="text-sm font-bold">${num(card.minutes)}</div>
                        <div class="ma-hint text-[0.62rem]">Minutes</div>
                    </div>
                    <div class="rounded-[12px] border border-black/5 bg-white p-2.5">
                        <div class="text-sm font-bold">${money(card.pool_earnings_usd)}</div>
                        <div class="ma-hint text-[0.62rem]">Pool</div>
                    </div>
                </div>
                <div class="ma-hint mt-2.5 flex items-center justify-between text-[0.72rem]">
                    <span>Claimable <b style="color:var(--ma-primary)">${money(card.claimable_usd)}</b></span>
                    <span>Claimed <b>${money(card.claimed_usd)}</b></span>
                </div>
            </div>`));
    });
}
