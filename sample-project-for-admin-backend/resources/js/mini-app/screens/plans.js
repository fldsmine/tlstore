/**
 * Shares & Plans — mirrors the web Shares page: eSIM package catalogue with
 * token-based activation/upgrade (POST /activate, /upgrade) and additional
 * minutes plans with token redemption (GET /minute-plans, POST /minutes/redeem).
 */

import { api } from '../api.js';
import { el, esc, money, num, skeleton, errorState, emptyState, toast, errMsg, appBar } from '../ui.js';
import { navigate, enableBack } from '../router.js';
import { state, setUser } from '../store.js';
import { haptic } from '../telegram.js';

export async function renderPlans(view) {
    enableBack('services');

    view.innerHTML = '';
    view.appendChild(appBar('Shares & Plans'));
    view.appendChild(skeleton(5));

    let packages;
    let minutes;

    try {
        [packages, minutes] = await Promise.all([
            api('/packages'),
            api('/minute-plans').catch(() => null),
        ]);
    } catch (error) {
        view.innerHTML = '';
        view.appendChild(appBar('Shares & Plans'));
        view.appendChild(errorState(errMsg(error), () => renderPlans(view)));
        return;
    }

    const user = state.user ?? {};
    const activePackage = user.package ?? null;
    const activated = user.onboarding?.activated ?? activePackage !== null;

    view.innerHTML = '';
    view.appendChild(appBar('Shares & Plans', activePackage ? `${esc(activePackage.name)} active` : 'No active plan'));

    const shell = el('<div></div>');
    view.appendChild(shell);

    // Active package summary.
    if (activePackage) {
        shell.appendChild(el(`
            <div class="ma-card mb-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="ma-hint text-[0.64rem] font-semibold uppercase">Your plan</div>
                        <div class="ma-heading text-lg font-bold">${esc(activePackage.name)}</div>
                    </div>
                    <span class="ma-chip ma-chip-accent"><i class="ri-medal-line"></i> Active</span>
                </div>
                ${minutes ? `
                <div class="ma-hint mt-2 flex items-center justify-between text-[0.75rem]">
                    <span><i class="ri-time-line mr-1"></i>Base ${num(minutes.base_minutes)} min/day</span>
                    <span>+ ${num(minutes.bonus_minutes)} bonus</span>
                </div>` : ''}
            </div>`));
    }

    // Package catalogue.
    shell.appendChild(el('<h3 class="ma-heading mb-2 text-base font-bold">eSIM packages</h3>'));

    (packages?.packages ?? []).forEach((pkg) => {
        const isCurrent = activePackage?.id === pkg.id;
        const action = isCurrent ? 'current' : activated ? 'upgrade' : 'activate';
        const price = action === 'upgrade' && pkg.upgrade_price_usd !== null ? pkg.upgrade_price_usd : pkg.price_usd;

        const card = el(`
            <div class="ma-card mb-3 ${pkg.is_featured ? 'ring-2 ring-[#DCEB6A]' : ''}">
                <div class="flex items-center justify-between gap-2">
                    <div class="text-[0.95rem] font-bold">${esc(pkg.name)}</div>
                    ${isCurrent
                        ? '<span class="ma-chip ma-chip-success">Current</span>'
                        : `<div class="ma-heading text-lg font-bold">${money(price)}${action === 'upgrade' ? '<span class="ma-hint ml-1 text-[0.62rem]">upgrade</span>' : ''}</div>`}
                </div>
                ${pkg.description ? `<p class="ma-hint mt-1 text-[0.76rem] leading-relaxed">${esc(pkg.description)}</p>` : ''}
                <ul class="ma-hint mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-[0.7rem]">
                    <li><i class="ri-phone-line mr-1"></i>${num(pkg.daily_call_minutes)} min/day</li>
                    <li><i class="ri-ids-line mr-1"></i>${pkg.call_id_limit} call ID${pkg.call_id_limit === 1 ? '' : 's'}</li>
                    <li><i class="ri-user-add-line mr-1"></i>${money(pkg.direct_referral_bonus_usd)} direct</li>
                    <li><i class="ri-pie-chart-2-line mr-1"></i>${money(pkg.max_daily_shareholder_earning_usd)}/day cap</li>
                    ${pkg.cloud_calls ? '<li><i class="ri-cloud-line mr-1"></i>Cloud calls</li>' : ''}
                    ${pkg.cloud_sms ? '<li><i class="ri-message-2-line mr-1"></i>Cloud SMS</li>' : ''}
                </ul>
                ${(pkg.features ?? []).length ? `
                    <div class="no-scrollbar mt-2 flex gap-1.5 overflow-x-auto">
                        ${pkg.features.map((feature) => `<span class="ma-chip ma-chip-muted whitespace-nowrap">${esc(feature)}</span>`).join('')}
                    </div>` : ''}
                ${isCurrent ? '' : `
                    <button class="ma-btn ${action === 'activate' ? 'ma-btn-primary' : 'ma-btn-accent'} mt-3" data-redeem>
                        <i class="ri-coupon-3-line"></i> ${action === 'upgrade' ? 'Upgrade with token' : 'Activate with token'}
                    </button>
                    <div class="mt-2 hidden" data-token-form>
                        <input class="ma-input" data-token placeholder="Enter your activation token" autocomplete="off">
                        <button class="ma-btn ma-btn-primary mt-2" data-confirm>Confirm ${esc(action)}</button>
                    </div>`}
            </div>`);

        const redeemButton = card.querySelector('[data-redeem]');
        const form = card.querySelector('[data-token-form]');
        const tokenInput = card.querySelector('[data-token]');
        const confirmButton = card.querySelector('[data-confirm]');

        redeemButton?.addEventListener('click', () => {
            form.classList.toggle('hidden');
            if (!form.classList.contains('hidden')) tokenInput.focus();
        });

        confirmButton?.addEventListener('click', async () => {
            const token = tokenInput.value.trim();
            if (token.length < 6) return toast('Enter the token from your vendor.', 'error');

            confirmButton.disabled = true;
            confirmButton.textContent = 'Processing…';

            try {
                const result = await api(action === 'upgrade' ? '/upgrade' : '/activate', {
                    method: 'POST',
                    body: { package_id: pkg.id, token },
                });
                haptic('success');
                toast(result?.message ?? 'Done — welcome aboard!', 'success', 3500);

                // Refresh the session user so the badge reflects the new plan.
                api('/user').then((data) => setUser(data.user ?? null)).catch(() => {});
                renderPlans(view);
            } catch (error) {
                toast(errMsg(error), 'error');
                confirmButton.disabled = false;
                confirmButton.textContent = `Confirm ${action}`;
            }
        });

        shell.appendChild(card);
    });

    // Additional minutes plans.
    if (minutes) {
        shell.appendChild(el('<h3 class="ma-heading mb-2 mt-5 text-base font-bold">Additional minutes</h3>'));

        const activeTopUps = minutes.active_top_ups ?? [];
        if (activeTopUps.length) {
            activeTopUps.forEach((topUp) => {
                shell.appendChild(el(`
                    <div class="ma-card ma-card-tight mb-2 flex items-center justify-between">
                        <div class="min-w-0">
                            <div class="truncate text-[0.82rem] font-semibold">${esc(topUp.plan_name ?? topUp.plan?.name ?? 'Top-up')}</div>
                            <div class="ma-hint text-[0.7rem]">${num(topUp.daily_call_minutes ?? 0)} extra min/day</div>
                        </div>
                        <span class="ma-chip ma-chip-success">Active</span>
                    </div>`));
            });
        }

        const plans = minutes.plans ?? [];
        if (!plans.length) {
            if (!activeTopUps.length) shell.appendChild(emptyState('ri-timer-flash-line', 'No minute plans on sale', 'Check back later.'));
        } else {
            plans.forEach((plan) => {
                const card = el(`
                    <div class="ma-card mb-3">
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-bold">${esc(plan.name)}</div>
                                <div class="ma-hint text-[0.72rem]">${num(plan.daily_call_minutes)} min/day · ${plan.duration_days} days</div>
                            </div>
                            <div class="ma-heading text-base font-bold">${money(plan.price_usd)}</div>
                        </div>
                        <button class="ma-btn mt-3" data-redeem><i class="ri-coupon-3-line"></i> Redeem token</button>
                        <div class="mt-2 hidden" data-token-form>
                            <input class="ma-input" data-token placeholder="Enter your minutes token" autocomplete="off">
                            <button class="ma-btn ma-btn-primary mt-2" data-confirm>Redeem</button>
                        </div>
                    </div>`);

                const form = card.querySelector('[data-token-form]');
                const tokenInput = card.querySelector('[data-token]');
                const confirmButton = card.querySelector('[data-confirm]');

                card.querySelector('[data-redeem]').addEventListener('click', () => {
                    form.classList.toggle('hidden');
                    if (!form.classList.contains('hidden')) tokenInput.focus();
                });

                confirmButton.addEventListener('click', async () => {
                    const token = tokenInput.value.trim();
                    if (token.length < 6) return toast('Enter the token from your vendor.', 'error');

                    confirmButton.disabled = true;
                    confirmButton.textContent = 'Redeeming…';

                    try {
                        await api('/minutes/redeem', { method: 'POST', body: { plan_id: plan.id, token } });
                        haptic('success');
                        toast('Minutes activated 🎉', 'success');
                        renderPlans(view);
                    } catch (error) {
                        toast(errMsg(error), 'error');
                        confirmButton.disabled = false;
                        confirmButton.textContent = 'Redeem';
                    }
                });

                shell.appendChild(card);
            });
        }
    }
}
