/**
 * VAS & Bills — mirrors the web VAS purchase flow: category → biller →
 * plan/amount → optional customer verification → PIN-confirmed purchase
 * (GET /vas/categories, /vas/categories/{key}, /vas/variations/{serviceId},
 * POST /vas/verify, POST /vas/purchase), plus recent orders with delivered
 * tokens (GET /vas/orders).
 */

import { api } from '../api.js';
import { el, esc, money, skeleton, errorState, emptyState, toast, when, errMsg, appBar, askPin } from '../ui.js';
import { enableBack } from '../router.js';
import { haptic } from '../telegram.js';

let state = {
    category: null,
    provider: null,
    variation: null,
    customerName: null,
};

export async function renderVas(view) {
    enableBack('services');
    state = { category: null, provider: null, variation: null, customerName: null };
    await loadCategories(view);
}

async function loadCategories(view) {
    view.innerHTML = '';
    view.appendChild(appBar('VAS & Bills'));
    view.appendChild(skeleton(4));

    let data;

    try {
        data = await api('/vas/categories');
    } catch (error) {
        view.innerHTML = '';
        view.appendChild(appBar('VAS & Bills'));
        view.appendChild(errorState(errMsg(error), () => loadCategories(view)));
        return;
    }

    view.innerHTML = '';
    view.appendChild(appBar('VAS & Bills'));

    if (!data.enabled) {
        view.appendChild(emptyState('ri-shopping-bag-3-line', 'Bill payments are offline', 'The VTU service is paused right now — try again later.'));
        return;
    }

    const icons = { airtime: 'ri-smartphone-line', data: 'ri-wifi-line', tv: 'ri-tv-2-line', electricity: 'ri-flashlight-line', education: 'ri-graduation-cap-line' };
    const grid = el('<div class="grid grid-cols-2 gap-3"></div>');

    (data.categories ?? []).forEach((category) => {
        const tile = el(`
            <button class="ma-card text-left">
                <span class="ma-icon mb-2 inline-flex"><i class="${icons[category.key] ?? 'ri-coupon-3-line'}"></i></span>
                <div class="text-sm font-bold">${esc(category.label)}</div>
                <div class="ma-hint mt-0.5 text-[0.7rem] leading-snug">${esc(category.blurb ?? 'Instant top-up')}</div>
            </button>`);
        tile.addEventListener('click', () => {
            haptic('light');
            loadCategory(view, category.key);
        });
        grid.appendChild(tile);
    });

    view.appendChild(grid);
    view.appendChild(ordersSection());
}

/* ── Category → providers → purchase ──────────────────────────────── */

async function loadCategory(view, key) {
    view.innerHTML = '';
    view.appendChild(appBar('VAS & Bills'));
    view.appendChild(skeleton(4));

    let data;

    try {
        data = await api(`/vas/categories/${encodeURIComponent(key)}`);
    } catch (error) {
        toast(errMsg(error), 'error');
        return loadCategories(view);
    }

    const category = data.category;
    state = { category, provider: null, variation: null, customerName: null };

    view.innerHTML = '';
    view.appendChild(appBar(category.label));

    const back = el('<button class="ma-btn ma-btn-ghost ma-btn-auto mb-3"><i class="ri-arrow-left-line"></i> All categories</button>');
    back.addEventListener('click', () => loadCategories(view));
    view.appendChild(back);

    const shell = el('<div></div>');
    view.appendChild(shell);

    const providers = data.providers ?? [];
    shell.appendChild(el('<h3 class="ma-heading mb-2 text-base font-bold">Choose a provider</h3>'));
    const providerGrid = el('<div class="grid grid-cols-2 gap-3"></div>');

    providers.forEach((provider) => {
        const tile = el(`
            <button class="ma-card text-center">
                ${provider.logo_url
                    ? `<img src="${esc(provider.logo_url)}" alt="" class="mx-auto mb-2 h-10 w-10 rounded-xl object-contain" onerror="this.style.display='none'">`
                    : '<span class="ma-icon mx-auto mb-2"><i class="ri-store-2-line"></i></span>'}
                <div class="text-sm font-bold">${esc(provider.label)}</div>
                <div class="ma-hint mt-0.5 text-[0.68rem]">${esc(provider.recipient)}</div>
            </button>`);
        tile.addEventListener('click', () => {
            haptic('light');
            state.provider = provider;
            loadPurchaseForm(view, shell, providerGrid, provider);
        });
        providerGrid.appendChild(tile);
    });

    shell.appendChild(providerGrid);
}

async function loadPurchaseForm(view, shell, providerGrid, provider) {
    providerGrid.remove();
    shell.innerHTML = '';

    const category = state.category;

    shell.appendChild(el(`
        <div class="ma-card mb-3 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <span class="ma-icon"><i class="ri-store-2-line"></i></span>
                <div>
                    <div class="text-sm font-bold">${esc(provider.label)}</div>
                    <div class="ma-hint text-[0.7rem]">${esc(category.label)}</div>
                </div>
            </div>
            <button class="ma-btn ma-btn-ghost ma-btn-sm" data-change>Change</button>
        </div>`));

    shell.querySelector('[data-change]').addEventListener('click', () => loadCategory(view, category.key));

    const usesPlans = (category.pricing ?? 'amount') !== 'amount';
    const needsVerify = provider.verify || category.verify;

    const form = el(`
        <div class="ma-card mb-3">
            <label class="ma-hint text-[0.7rem] font-semibold uppercase">${esc(provider.recipient ?? 'Recipient')}</label>
            <input class="ma-input mt-1" name="recipient" placeholder="${esc(provider.recipient ?? 'Recipient')}">

            ${category.key === 'electricity' ? `
                <label class="ma-hint mt-3 block text-[0.7rem] font-semibold uppercase">Meter type</label>
                <div class="mt-1 grid grid-cols-2 gap-2">
                    <button class="ma-btn ma-btn-sm" data-meter="prepaid">Prepaid</button>
                    <button class="ma-btn ma-btn-sm" data-meter="postpaid">Postpaid</button>
                </div>` : ''}

            ${category.subscription ? `
                <label class="ma-hint mt-3 block text-[0.7rem] font-semibold uppercase">Renewal</label>
                <div class="mt-1 grid grid-cols-2 gap-2">
                    <button class="ma-btn ma-btn-sm" data-sub="renew">Renew existing</button>
                    <button class="ma-btn ma-btn-sm" data-sub="new">New subscription</button>
                </div>` : ''}

            ${needsVerify ? '<button class="ma-btn mt-3" data-verify><i class="ri-search-line"></i> Verify customer</button><div class="ma-hint mt-1 text-[0.72rem]" data-verify-result></div>' : ''}

            <div class="mt-3" data-plan-slot></div>

            <button class="ma-btn ma-btn-primary mt-3" data-buy disabled><i class="ri-shopping-cart-2-line"></i> Buy now</button>
        </div>`);

    shell.appendChild(form);

    let meterType = 'prepaid';
    let subscriptionType = 'renew';
    let plans = [];

    form.querySelectorAll('[data-meter]').forEach((button) => {
        button.addEventListener('click', () => {
            meterType = button.dataset.meter;
            form.querySelectorAll('[data-meter]').forEach((other) => other.classList.toggle('ma-btn-primary', other === button));
        });
    });
    form.querySelector('[data-meter="prepaid"]')?.classList.add('ma-btn-primary');

    form.querySelectorAll('[data-sub]').forEach((button) => {
        button.addEventListener('click', () => {
            subscriptionType = button.dataset.sub;
            form.querySelectorAll('[data-sub]').forEach((other) => other.classList.toggle('ma-btn-primary', other === button));
        });
    });
    form.querySelector('[data-sub="renew"]')?.classList.add('ma-btn-primary');

    const recipientInput = form.querySelector('[name="recipient"]');
    const planSlot = form.querySelector('[data-plan-slot]');
    const buyButton = form.querySelector('[data-buy]');

    // Load plans for variation-based categories.
    if (usesPlans) {
        planSlot.innerHTML = '<div class="ma-hint">Loading plans…</div>';
        try {
            const response = await api(`/vas/variations/${encodeURIComponent(provider.id)}?category=${encodeURIComponent(category.key)}`);
            plans = response.plans ?? [];

            if (!plans.length) {
                planSlot.innerHTML = '<div class="ma-hint text-[0.75rem]">No plans listed — enter an amount below.</div>';
            } else {
                const select = el(`<select class="ma-input" name="plan"><option value="">Select plan…</option></select>`);
                plans.forEach((plan) => {
                    select.appendChild(el(`<option value="${esc(plan.code)}">${esc(plan.label)} — ₦${Number(plan.amount).toLocaleString()}</option>`));
                });
                planSlot.innerHTML = '<label class="ma-hint text-[0.7rem] font-semibold uppercase">Plan</label>';
                planSlot.appendChild(select);
            }
        } catch {
            planSlot.innerHTML = '<div class="ma-hint text-[0.75rem]">Could not load plans — enter an amount below.</div>';
        }
    }

    // Amount field — the biller's fallback when no plan is picked.
    const amountField = el(`
        <div class="mt-3">
            <label class="ma-hint text-[0.7rem] font-semibold uppercase">Amount (₦)</label>
            <input class="ma-input mt-1" name="amount" type="number" min="1" inputmode="numeric" placeholder="Amount in naira">
        </div>`);
    form.insertBefore(amountField, buyButton);

    const amountInput = form.querySelector('[name="amount"]');
    const planSelect = form.querySelector('[name="plan"]');
    const verifyResult = form.querySelector('[data-verify-result]');

    if (needsVerify) {
        form.querySelector('[data-verify]').addEventListener('click', async () => {
            const recipient = recipientInput.value.trim();
            if (!recipient) return toast(`Enter the ${provider.recipient?.toLowerCase() ?? 'recipient'} first.`, 'error');

            verifyResult.textContent = 'Verifying…';
            try {
                const result = await api('/vas/verify', {
                    method: 'POST',
                    body: { category: category.key, service_id: provider.id, recipient, meter_type: category.key === 'electricity' ? meterType : undefined },
                });
                state.customerName = result.name;
                verifyResult.innerHTML = `<span style="color:var(--ma-success)">✓ ${esc(result.name)}</span>`;
                haptic('success');
            } catch (error) {
                state.customerName = null;
                verifyResult.textContent = errMsg(error);
            }
        });
    }

    const valid = () => {
        if (!recipientInput.value.trim()) return false;
        if (needsVerify && !state.customerName) return false;
        if (planSelect?.value) return true;
        return parseFloat(amountInput?.value ?? '') > 0;
    };

    form.addEventListener('input', (event) => {
        if (event.target === recipientInput && needsVerify) {
            state.customerName = null;
            if (verifyResult) verifyResult.textContent = '';
        }
        buyButton.disabled = !valid();
    });
    planSelect?.addEventListener('change', () => {
        buyButton.disabled = !valid();
    });

    buyButton.addEventListener('click', async () => {
        const variation = plans.find((plan) => plan.code === planSelect?.value) ?? null;

        const pin = await askPin('Confirm purchase PIN');
        if (!pin) return;

        buyButton.disabled = true;
        buyButton.textContent = 'Purchasing…';

        const body = {
            category: category.key,
            service_id: provider.id,
            recipient: recipientInput.value.trim(),
            phone: recipientInput.value.trim(),
            pin,
        };

        if (variation) body.variation_code = variation.code;
        if (amountInput?.value && parseFloat(amountInput.value) > 0) body.amount = parseFloat(amountInput.value);
        if (category.key === 'electricity') body.meter_type = meterType;
        if (category.subscription) body.subscription_type = subscriptionType;
        if (state.customerName) body.customer_name = state.customerName;

        try {
            await api('/vas/purchase', { method: 'POST', body });
            haptic('success');
            toast('Purchase submitted — check orders below.', 'success', 3200);
            loadCategories(view);
        } catch (error) {
            toast(errMsg(error), 'error');
            buyButton.disabled = false;
            buyButton.innerHTML = '<i class="ri-shopping-cart-2-line"></i> Buy now';
        }
    });
}

/* ── Recent orders ────────────────────────────────────────────────── */

function ordersSection() {
    const section = el('<div class="mt-5" id="vas-orders"></div>');

    api('/vas/orders?per_page=5').then((data) => {
        const items = data.items ?? [];
        if (!items.length) return;

        section.innerHTML = '<h3 class="ma-heading mb-2 text-base font-bold">Recent orders</h3>';

        items.forEach((order) => {
            const chip = {
                delivered: 'ma-chip-success',
                pending: 'ma-chip-warning',
                failed: 'ma-chip-danger',
                reversed: 'ma-chip-danger',
            }[order.status] ?? 'ma-chip-muted';

            section.appendChild(el(`
                <div class="ma-row">
                    <div class="ma-icon"><i class="ri-receipt-line"></i></div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold">${esc(order.plan_label ?? order.service ?? 'Order')} · ₦${Number(order.amount_ngn).toLocaleString()}</div>
                        <div class="ma-hint truncate text-xs">${esc(order.recipient ?? '')} · ${esc(when(order.created_at))}${order.token ? ` · token: ${esc(order.token)}` : ''}</div>
                    </div>
                    <span class="ma-chip ${chip}">${esc(order.status)}</span>
                </div>`));
        });
    }).catch(() => {
        /* orders are best-effort */
    });

    return section;
}
