/**
 * Vendor Dashboard — agent-only area mirroring the web Vendor pages:
 * float summary + token stats (GET /agent/summary), token minting
 * (GET /agent/plans, POST /agent/tokens), token list (GET /agent/tokens)
 * and float transfers (POST /agent/transfer). Guarded by the api.agent
 * middleware server-side.
 */

import { api } from '../api.js';
import { el, esc, money, skeleton, errorState, emptyState, toast, when, errMsg, appBar } from '../ui.js';
import { enableBack } from '../router.js';
import { haptic } from '../telegram.js';

export async function renderVendor(view) {
    enableBack('services');

    view.innerHTML = '';
    view.appendChild(appBar('Vendor Dashboard'));
    view.appendChild(skeleton(5));

    let summary;
    let plans;

    try {
        [summary, plans] = await Promise.all([
            api('/agent/summary'),
            api('/agent/plans'),
        ]);
    } catch (error) {
        view.innerHTML = '';
        view.appendChild(appBar('Vendor Dashboard'));
        view.appendChild(errorState(errMsg(error), () => renderVendor(view)));
        return;
    }

    view.innerHTML = '';
    view.appendChild(appBar('Vendor Dashboard', summary.is_special_vendor ? 'Special vendor' : 'Vendor'));

    const shell = el('<div></div>');
    view.appendChild(shell);

    // Float + token stats.
    shell.appendChild(el(`
        <div class="ma-card mb-3">
            <div class="flex items-center justify-between">
                <div>
                    <div class="ma-hint text-[0.64rem] font-semibold uppercase">Float balance</div>
                    <div class="ma-heading mt-0.5 text-2xl font-bold">${money(summary.float_balance_usd)}</div>
                </div>
                <span class="ma-icon" style="width:2.8rem;height:2.8rem"><i class="ri-safe-2-line"></i></span>
            </div>
            <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                ${stat('Minted', summary.tokens.total)}
                ${stat('Active', summary.tokens.active)}
                ${stat('Used', summary.tokens.used)}
            </div>
        </div>`));

    shell.appendChild(transferCard(summary));
    shell.appendChild(mintCard(plans.plans ?? [], view));
    shell.appendChild(tokenList());
}

function stat(label, value) {
    return `<div class="rounded-[12px] border border-black/5 bg-white p-2.5"><div class="text-sm font-bold">${value}</div><div class="ma-hint text-[0.62rem]">${label}</div></div>`;
}

/* ── Float transfer ───────────────────────────────────────────────── */

function transferCard(summary) {
    const card = el(`
        <div class="ma-card mb-3">
            <div class="ma-heading mb-2 text-base font-bold">Transfer float</div>
            <input class="ma-input" data-username placeholder="Recipient @username">
            <input class="ma-input mt-2" data-amount type="number" min="0.01" step="0.01" inputmode="decimal" placeholder="Amount (USD)">
            <button class="ma-btn ma-btn-primary mt-2" data-send><i class="ri-send-plane-line"></i> Send transfer</button>
        </div>`);

    card.querySelector('[data-send]').addEventListener('click', async () => {
        const username = card.querySelector('[data-username]').value.trim().replace(/^@/, '');
        const amount = parseFloat(card.querySelector('[data-amount]').value);

        if (!username) return toast('Enter the recipient’s username.', 'error');
        if (!Number.isFinite(amount) || amount <= 0) return toast('Enter the amount to transfer.', 'error');
        if (amount > summary.float_balance_usd) return toast('That exceeds your float balance.', 'error');

        const button = card.querySelector('[data-send]');
        button.disabled = true;
        button.textContent = 'Sending…';

        try {
            await api('/agent/transfer', { method: 'POST', body: { recipient_username: username, amount } });
            haptic('success');
            toast(`Sent ${money(amount)} to @${username}`, 'success', 3200);
            renderVendor(card.closest('#view'));
        } catch (error) {
            toast(errMsg(error), 'error');
            button.disabled = false;
            button.innerHTML = '<i class="ri-send-plane-line"></i> Send transfer';
        }
    });

    return card;
}

/* ── Mint tokens ──────────────────────────────────────────────────── */

function mintCard(plans, view) {
    const groups = [...new Set(plans.map((plan) => plan.group))];

    const card = el(`
        <div class="ma-card mb-3">
            <div class="ma-heading mb-2 text-base font-bold">Mint tokens</div>
            <select class="ma-input" data-plan>
                <option value="">Select a plan…</option>
                ${groups.map((group) => `
                    <optgroup label="${esc(group)}">
                        ${plans.filter((plan) => plan.group === group).map((plan) =>
                            `<option value="${esc(plan.key)}">${esc(plan.label)} — agent ${money(plan.agent_price)} / retail ${money(plan.price)}</option>`).join('')}
                    </optgroup>`).join('')}
            </select>
            <input class="ma-input mt-2" data-qty type="number" min="1" max="500" inputmode="numeric" placeholder="Quantity (max 500)">
            <div class="ma-hint mt-1 text-[0.72rem]" data-cost></div>
            <button class="ma-btn ma-btn-primary mt-2" data-mint><i class="ri-coupon-3-line"></i> Mint tokens</button>
        </div>`);

    const select = card.querySelector('[data-plan]');
    const qtyInput = card.querySelector('[data-qty]');
    const costLabel = card.querySelector('[data-cost]');

    const refreshCost = () => {
        const plan = plans.find((candidate) => candidate.key === select.value);
        const quantity = parseInt(qtyInput.value, 10);

        costLabel.textContent = plan && Number.isFinite(quantity) && quantity > 0
            ? `Total cost: ${money(plan.agent_price * quantity)} from your float.`
            : '';
    };

    select.addEventListener('change', refreshCost);
    qtyInput.addEventListener('input', refreshCost);

    card.querySelector('[data-mint]').addEventListener('click', async () => {
        const planKey = select.value;
        const quantity = parseInt(qtyInput.value, 10);

        if (!planKey) return toast('Pick a plan to mint.', 'error');
        if (!Number.isFinite(quantity) || quantity < 1) return toast('Enter how many tokens to mint.', 'error');

        const button = card.querySelector('[data-mint]');
        button.disabled = true;
        button.textContent = 'Minting…';

        try {
            const result = await api('/agent/tokens', { method: 'POST', body: { plan_key: planKey, quantity } });
            haptic('success');
            toast(`${result.batch?.quantity ?? quantity} token(s) generated — cost ${money(result.cost_usd)}`, 'success', 4000);
            renderVendor(view);
        } catch (error) {
            toast(errMsg(error), 'error');
            button.disabled = false;
            button.innerHTML = '<i class="ri-coupon-3-line"></i> Mint tokens';
        }
    });

    return card;
}

/* ── Token list ───────────────────────────────────────────────────── */

function tokenList() {
    const section = el('<div><h3 class="ma-heading mb-2 mt-1 text-base font-bold">Your tokens</h3><div data-rows></div></div>');
    const rows = section.querySelector('[data-rows]');
    rows.appendChild(el('<div class="flex justify-center py-6"><div class="ma-spinner"></div></div>'));

    api('/agent/tokens?per_page=20').then((data) => {
        const items = data.items ?? [];
        rows.innerHTML = '';

        if (!items.length) {
            rows.appendChild(emptyState('ri-coupon-3-line', 'No tokens yet', 'Mint your first batch above.'));
            return;
        }

        items.forEach((token) => {
            const row = el(`
                <div class="ma-row">
                    <div class="ma-icon"><i class="ri-coupon-3-line"></i></div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-mono text-[0.82rem] font-semibold">${esc(token.code)}</div>
                        <div class="ma-hint truncate text-xs">${esc(token.label ?? token.type_name ?? '')} · ${esc(when(token.created_at))}</div>
                    </div>
                    <span class="ma-chip ${token.used_at ? 'ma-chip-muted' : 'ma-chip-success'}">${token.used_at ? 'Used' : 'Active'}</span>
                    <button class="ma-icon" style="width:2.25rem;height:2.25rem" data-copy aria-label="Copy token"><i class="ri-file-copy-line"></i></button>
                </div>`);

            row.querySelector('[data-copy]').addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(token.code);
                    haptic('success');
                    toast('Token copied', 'success', 1500);
                } catch {
                    toast(token.code, 'info', 2500);
                }
            });

            rows.appendChild(row);
        });
    }).catch((error) => {
        rows.innerHTML = '';
        rows.appendChild(errorState(errMsg(error), () => {}));
    });

    return section;
}
