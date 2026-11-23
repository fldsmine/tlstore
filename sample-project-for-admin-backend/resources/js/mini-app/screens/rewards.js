/**
 * Target rewards: today's target + progress, set-a-target form (MainButton
 * submits), claim achieved targets, and 30-day history. All via the existing
 * /rewards endpoints.
 */

import { api, ApiError } from '../api.js';
import { el, esc, money, skeleton, errorState, emptyState, toast } from '../ui.js';
import { haptic, setMainButton, hideMainButton, setMainButtonProgress } from '../telegram.js';
import { enableBack } from '../router.js';

export async function renderRewards(view) {
    enableBack('home');
    await load(view);
}

async function load(view) {
    view.innerHTML = '';
    view.appendChild(skeleton(4));
    hideMainButton();

    let data;

    try {
        data = await api('/rewards');
    } catch (error) {
        view.innerHTML = '';
        view.appendChild(errorState(error.message, () => load(view)));
        return;
    }

    view.innerHTML = `
        <h1 class="py-2 text-xl font-bold">Target Rewards</h1>
        <div class="grid grid-cols-3 gap-2">
            ${stat('Streak', `${data.streak ?? 0}d`)}
            ${stat('Lifetime', money(data.lifetime_earned_usd))}
            ${stat('Bonus', `${Number(data.reward_percent ?? 0).toFixed(0)}%`)}
        </div>
        <div id="today" class="mt-4"></div>
        <h2 class="ma-muted mb-2 mt-6 text-sm font-bold uppercase tracking-wide">History</h2>
        <div id="history"></div>`;

    const today = view.querySelector('#today');
    const history = view.querySelector('#history');

    if (data.enabled === false) {
        today.appendChild(emptyState('ri-trophy-line', 'Rewards are paused', 'Daily targets will return soon.'));
        return;
    }

    if (data.today_target) {
        today.appendChild(buildTargetCard(data.today_target, view));
    } else {
        today.appendChild(buildSetTargetForm(data, view));
    }

    const rows = data.history ?? [];

    if (!rows.length) {
        history.appendChild(emptyState('ri-history-line', 'No history yet', 'Set your first daily target above.'));
        return;
    }

    rows.forEach((target) => history.appendChild(buildHistoryRow(target, view)));
}

function stat(label, value) {
    return `<div class="ma-card ma-card-tight text-center"><div class="ma-hint text-[0.65rem] uppercase">${label}</div><div class="mt-0.5 text-sm font-bold">${esc(value)}</div></div>`;
}

function buildTargetCard(target, view) {
    const achieved = target.status === 'achieved' || target.status === 'claimed';
    const claimed = target.status === 'claimed';
    const progress = Number(target.progress_usd ?? 0);
    const goal = Number(target.target_amount_usd ?? 0);
    const pct = goal > 0 ? Math.min(100, Math.round((progress / goal) * 100)) : 0;

    const card = el(`
        <div class="ma-card">
            <div class="flex items-center justify-between">
                <span class="ma-hint text-xs uppercase tracking-wider">Today’s target</span>
                <span class="ma-chip ${claimed ? 'ma-chip-muted' : achieved ? 'ma-chip-success' : 'ma-chip-muted'}">${esc(target.status)}</span>
            </div>
            <div class="mt-2 text-2xl font-bold">${money(progress)} <span class="ma-muted text-sm font-normal">of ${money(goal)}</span></div>
            <div class="mt-2 h-2 overflow-hidden rounded-full" style="background:color-mix(in srgb, var(--ma-text) 10%, transparent)">
                <div class="h-full rounded-full" style="width:${pct}%;background:${achieved ? 'var(--ma-success)' : 'var(--ma-accent)'}"></div>
            </div>
            <div class="mt-2 ma-hint text-xs">Hit ${money(goal)} today in spendable earnings to win <b>${money(target.reward_amount_usd)}</b>.</div>
        </div>`);

    if (achieved && !claimed) {
        const claim = el('<button class="ma-btn ma-btn-primary mt-3">Claim reward</button>');
        claim.addEventListener('click', async () => {
            claim.disabled = true;
            claim.textContent = 'Claiming…';

            try {
                await api(`/rewards/${target.id}/claim`, { method: 'POST' });
                haptic('success');
                toast('Reward claimed 🎉', 'success');
                await load(view);
            } catch (error) {
                const message = error instanceof ApiError ? (error.fieldMessage() ?? error.message) : error.message;
                toast(message, 'error');
                claim.disabled = false;
                claim.textContent = 'Claim reward';
            }
        });
        card.appendChild(claim);
    }

    return card;
}

function buildSetTargetForm(data, view) {
    const min = Number(data.min_target_usd ?? 1);
    const max = Number(data.max_target_usd ?? 100);

    const card = el(`
        <div class="ma-card">
            <div class="font-semibold">Set today’s target</div>
            <p class="ma-hint mt-1 text-xs">Earn between ${money(min)} and ${money(max)} today and get a bonus of ${Number(data.reward_percent ?? 0).toFixed(0)}% of the target.</p>
            <input class="ma-input mt-3" type="number" inputmode="decimal" min="${min}" max="${max}" step="0.01" placeholder="Target amount (USD)" id="target-amount">
        </div>`);

    const input = card.querySelector('#target-amount');

    setMainButton({
        text: 'SET TARGET',
        visible: true,
        onClick: async () => {
            const amount = parseFloat(input.value);

            if (!Number.isFinite(amount) || amount < min || amount > max) {
                toast(`Enter an amount between ${money(min)} and ${money(max)}`, 'error');
                return;
            }

            setMainButtonProgress(true);

            try {
                await api('/rewards/target', { method: 'POST', body: { amount } });
                haptic('success');
                toast('Target set — go earn!', 'success');
                await load(view);
            } catch (error) {
                const message = error instanceof ApiError ? (error.fieldMessage() ?? error.message) : error.message;
                toast(message, 'error');
            } finally {
                setMainButtonProgress(false);
            }
        },
    });

    return card;
}

function buildHistoryRow(target, view) {
    const claimed = target.status === 'claimed';
    const achieved = target.status === 'achieved';

    const row = el(`
        <div class="ma-row">
            <div class="ma-icon"><i class="ri-trophy-line"></i></div>
            <div class="min-w-0 flex-1">
                <div class="text-sm font-semibold">${esc(target.date)}</div>
                <div class="ma-hint text-xs">${money(target.progress_usd)} / ${money(target.target_amount_usd)}</div>
            </div>
            ${claimed
                ? '<span class="ma-chip ma-chip-muted">Claimed</span>'
                : achieved
                    ? `<button class="ma-btn ma-btn-primary ma-btn-sm text-xs" data-claim>Claim ${money(target.reward_amount_usd)}</button>`
                    : '<span class="ma-chip ma-chip-muted">Missed</span>'}
        </div>`);

    row.querySelector('[data-claim]')?.addEventListener('click', async (event) => {
        const button = event.currentTarget;
        button.disabled = true;

        try {
            await api(`/rewards/${target.id}/claim`, { method: 'POST' });
            haptic('success');
            toast('Reward claimed 🎉', 'success');
            await load(view);
        } catch (error) {
            const message = error instanceof ApiError ? (error.fieldMessage() ?? error.message) : error.message;
            toast(message, 'error');
            button.disabled = false;
        }
    });

    return row;
}
