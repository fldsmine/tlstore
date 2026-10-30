/**
 * Pay Later — mirrors the web Pay Later page: eligibility, active credits
 * (GET /pay-later), plan activation with PIN (POST /pay-later) and token
 * repayment (POST /pay-later/{credit}/repay).
 */

import { api } from '../api.js';
import { el, esc, money, num, dateLabel, skeleton, errorState, emptyState, toast, errMsg, appBar, askPin } from '../ui.js';
import { enableBack } from '../router.js';
import { haptic } from '../telegram.js';

export async function renderPayLater(view) {
    enableBack('services');
    await load(view);
}

async function load(view) {
    view.innerHTML = '';
    view.appendChild(appBar('Pay Later'));
    view.appendChild(skeleton(5));

    let data;
    let plans = [];

    try {
        [data, plans] = await Promise.all([
            api('/pay-later'),
            api('/minute-plans').then((response) => response.plans ?? []).catch(() => []),
        ]);
    } catch (error) {
        view.innerHTML = '';
        view.appendChild(appBar('Pay Later'));
        view.appendChild(errorState(errMsg(error), () => load(view)));
        return;
    }

    view.innerHTML = '';
    view.appendChild(appBar('Pay Later'));

    const shell = el('<div></div>');
    view.appendChild(shell);

    if (!data.enabled) {
        shell.appendChild(emptyState('ri-timer-flash-line', 'Pay Later is not enabled', 'Check back later — this feature rolls out gradually.'));
        return;
    }

    // Outstanding credits.
    const credits = data.credits ?? [];
    const outstanding = credits.filter((credit) => credit.status === 'active');

    if (outstanding.length) {
        shell.appendChild(el('<h3 class="ma-heading mb-2 text-base font-bold">Outstanding</h3>'));
        outstanding.forEach((credit) => shell.appendChild(creditCard(credit, view)));
    }

    // Eligibility banner.
    shell.appendChild(el(`
        <div class="ma-card ma-card-tight mb-3 flex items-center justify-between">
            <span class="text-[0.8rem] font-semibold">Eligibility</span>
            <span class="ma-chip ${data.eligible ? 'ma-chip-success' : 'ma-chip-muted'}">${data.eligible ? 'Eligible' : 'Not eligible yet'}</span>
        </div>`));

    if (!data.eligible && !outstanding.length) {
        shell.appendChild(el('<p class="ma-hint text-center text-[0.76rem]">Keep earning and stay settled to unlock Pay Later.</p>'));
        return;
    }

    // Available plans.
    const applicable = plans.filter((plan) => plan.paylater_duration_days > 0);
    if (!applicable.length) {
        shell.appendChild(el('<p class="ma-hint mt-2 text-center text-[0.76rem]">No Pay Later plans on sale right now.</p>'));
        return;
    }

    shell.appendChild(el('<h3 class="ma-heading mb-2 mt-4 text-base font-bold">Activate now, settle later</h3>'));

    applicable.forEach((plan) => {
        const card = el(`
            <div class="ma-card mb-3">
                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <div class="truncate text-sm font-bold">${esc(plan.name)}</div>
                        <div class="ma-hint text-[0.72rem]">${num(plan.daily_call_minutes)} min/day · settle within ${plan.paylater_duration_days} days</div>
                    </div>
                    <div class="ma-heading text-base font-bold">${money(plan.price_usd)}</div>
                </div>
                <label class="ma-hint mt-2.5 flex items-start gap-2 text-[0.72rem]">
                    <input type="checkbox" data-agree class="mt-0.5">
                    I agree to repay with an activation token before the due date.
                </label>
                <button class="ma-btn ma-btn-primary mt-2.5" data-apply ${data.eligible ? '' : 'disabled'}>
                    <i class="ri-flashlight-line"></i> Activate now
                </button>
            </div>`);

        const agree = card.querySelector('[data-agree]');
        const button = card.querySelector('[data-apply]');

        button.addEventListener('click', async () => {
            if (!agree.checked) return toast('Please agree to the repayment terms.', 'error');

            const pin = await askPin('Confirm with your PIN');
            if (!pin) return;

            button.disabled = true;
            button.textContent = 'Activating…';

            try {
                await api('/pay-later', { method: 'POST', body: { plan_id: plan.id, pin, agreed: true } });
                haptic('success');
                toast('Minutes activated — start earning immediately 🎉', 'success', 3500);
                load(view);
            } catch (error) {
                toast(errMsg(error), 'error');
                button.disabled = false;
                button.innerHTML = '<i class="ri-flashlight-line"></i> Activate now';
            }
        });

        shell.appendChild(card);
    });

    // History.
    const history = credits.filter((credit) => credit.status !== 'active');
    if (history.length) {
        shell.appendChild(el('<h3 class="ma-heading mb-2 mt-4 text-base font-bold">History</h3>'));
        history.forEach((credit) => {
            shell.appendChild(el(`
                <div class="ma-row">
                    <div class="ma-icon"><i class="ri-history-line"></i></div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold">${esc(credit.plan_name)} · ${money(credit.amount_usd)}</div>
                        <div class="ma-hint text-xs">${esc(dateLabel(credit.created_at))} · due ${esc(credit.due_date)}</div>
                    </div>
                    <span class="ma-chip ${credit.status === 'repaid' ? 'ma-chip-success' : 'ma-chip-danger'}">${esc(credit.status)}</span>
                </div>`));
        });
    }
}

function creditCard(credit, view) {
    const card = el(`
        <div class="ma-card mb-3" style="border:1.5px solid color-mix(in srgb, var(--ma-warning) 45%, transparent)">
            <div class="flex items-center justify-between">
                <div class="min-w-0">
                    <div class="truncate text-sm font-bold">${esc(credit.plan_name)}</div>
                    <div class="ma-hint text-[0.72rem]">${num(credit.minutes)} min · due ${esc(credit.due_date)}</div>
                </div>
                <div class="ma-heading text-base font-bold">${money(credit.amount_usd)}</div>
            </div>
            <button class="ma-btn ma-btn-accent mt-3" data-repay><i class="ri-coupon-3-line"></i> Repay with token</button>
            <div class="mt-2 hidden" data-token-form>
                <input class="ma-input" data-token placeholder="Enter your activation token" autocomplete="off">
                <button class="ma-btn ma-btn-primary mt-2" data-confirm>Confirm repayment</button>
            </div>
        </div>`);

    const form = card.querySelector('[data-token-form]');
    const tokenInput = card.querySelector('[data-token]');
    const confirm = card.querySelector('[data-confirm]');

    card.querySelector('[data-repay]').addEventListener('click', () => {
        form.classList.toggle('hidden');
        if (!form.classList.contains('hidden')) tokenInput.focus();
    });

    confirm.addEventListener('click', async () => {
        const token = tokenInput.value.trim();
        if (token.length < 6) return toast('Enter the token from your vendor.', 'error');

        confirm.disabled = true;
        confirm.textContent = 'Settling…';

        try {
            await api(`/pay-later/${credit.id}/repay`, { method: 'POST', body: { token } });
            haptic('success');
            toast('Pay Later repaid — you’re all settled ✅', 'success', 3500);
            load(view);
        } catch (error) {
            toast(errMsg(error), 'error');
            confirm.disabled = false;
            confirm.textContent = 'Confirm repayment';
        }
    });

    return card;
}
