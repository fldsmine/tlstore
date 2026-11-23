/**
 * Withdraw — mirrors the web Withdraw page: pick a spendable wallet, enter
 * the USD amount, confirm with the transaction PIN (POST /withdrawals). Also
 * hosts payout-account setup (GET /banks, POST /bank-account/verify for NG,
 * POST|PUT /bank-account), since withdrawals need a saved account.
 */

import { api } from '../api.js';
import { el, esc, money, skeleton, errorState, toast, when, errMsg, appBar, askPin } from '../ui.js';
import { navigate, enableBack } from '../router.js';
import { state } from '../store.js';
import { haptic } from '../telegram.js';

export async function renderWithdraw(view) {
    enableBack('wallet');

    view.innerHTML = '';
    view.appendChild(appBar('Withdraw'));
    view.appendChild(skeleton(5));

    let wallets;
    let account;
    let history;

    try {
        [wallets, account, history] = await Promise.all([
            api('/wallets'),
            api('/bank-account'),
            api('/withdrawals?per_page=5').catch(() => ({ items: [] })),
        ]);
    } catch (error) {
        view.innerHTML = '';
        view.appendChild(appBar('Withdraw'));
        view.appendChild(errorState(errMsg(error), () => renderWithdraw(view)));
        return;
    }

    const bankAccount = account?.bank_account ?? null;
    const spendable = (wallets.wallets ?? []).filter((wallet) => wallet.spendable);
    const user = state.user ?? {};

    view.innerHTML = '';
    view.appendChild(appBar('Withdraw'));

    const shell = el(`<div></div>`);
    view.appendChild(shell);

    shell.appendChild(bankAccount ? accountCard(bankAccount) : accountSetup(user, shell));
    shell.appendChild(requestForm(wallets, spendable, bankAccount, user));
    shell.appendChild(recentList(history.items ?? []));
}

/* ── Payout account ───────────────────────────────────────────────── */

function accountCard(bankAccount) {
    return el(`
        <div class="ma-card mb-3">
            <div class="flex items-center gap-3">
                <span class="ma-icon"><i class="ri-bank-card-line"></i></span>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-bold">${esc(bankAccount.account_name || 'Payout account')}</div>
                    <div class="ma-hint truncate text-[0.72rem]">${esc(bankAccount.bank?.name ?? 'Bank')} · ${esc(bankAccount.masked_number ?? bankAccount.account_number ?? '')}</div>
                </div>
                <span class="ma-chip ma-chip-success">Saved</span>
            </div>
        </div>`);
}

function accountSetup(user, shell) {
    const isNigerian = (user.country ?? '').toUpperCase() === 'NG';

    const card = el(`
        <div class="ma-card mb-3">
            <div class="mb-2 flex items-center gap-2">
                <span class="ma-icon"><i class="ri-bank-card-line"></i></span>
                <div>
                    <div class="text-sm font-bold">Set up your payout account</div>
                    <div class="ma-hint text-[0.72rem]">${isNigerian ? 'Your Nigerian bank account (verified automatically).' : 'Your bank or mobile-money account.'}</div>
                </div>
            </div>
            <select class="ma-input" name="bank_id"><option value="">Select bank…</option></select>
            <input class="ma-input mt-2" name="account_number" inputmode="numeric"
                   placeholder="${isNigerian ? '10-digit account number' : 'Mobile money / account number'}">
            ${isNigerian
                ? '<button class="ma-btn mt-2" data-verify><i class="ri-search-line"></i> Verify account</button><div class="ma-hint mt-1 text-[0.72rem]" data-verify-result></div>'
                : `<input class="ma-input mt-2" name="account_name" placeholder="Account name (as registered)">
                   <label class="ma-hint mt-2 flex items-center gap-2 text-[0.72rem]"><input type="checkbox" name="confirmed"> I confirm these details are correct.</label>`}
            <button class="ma-btn ma-btn-primary mt-3" data-save disabled>Save payout account</button>
        </div>`);

    const bankSelect = card.querySelector('[name="bank_id"]');
    const numberInput = card.querySelector('[name="account_number"]');
    const nameInput = card.querySelector('[name="account_name"]');
    const confirmed = card.querySelector('[name="confirmed"]');
    const saveButton = card.querySelector('[data-save]');
    let verifiedName = null;

    // Banks for the user's country.
    api('/banks').then((data) => {
        (data.banks ?? []).forEach((bank) => {
            bankSelect.appendChild(el(`<option value="${esc(bank.id)}">${esc(bank.name)}${bank.tag ? ` (${esc(bank.tag)})` : ''}</option>`));
        });
    }).catch(() => {
        bankSelect.disabled = true;
        bankSelect.innerHTML = '<option value="">No banks available for your country</option>';
    });

    const refresh = () => {
        const ok = isNigerian
            ? bankSelect.value && numberInput.value.replace(/\D/g, '').length === 10 && verifiedName
            : bankSelect.value && numberInput.value.trim().length >= 6 && (nameInput?.value.trim() ?? '') !== '' && confirmed?.checked;
        saveButton.disabled = !ok;
    };

    [bankSelect, numberInput, nameInput, confirmed].filter(Boolean).forEach((field) => {
        field.addEventListener('input', () => {
            verifiedName = field === numberInput ? null : verifiedName;
            refresh();
        });
        field.addEventListener('change', refresh);
    });

    if (isNigerian) {
        const verifyButton = card.querySelector('[data-verify]');
        const verifyResult = card.querySelector('[data-verify-result]');

        verifyButton.addEventListener('click', async () => {
            verifyResult.textContent = 'Checking…';
            verifyButton.disabled = true;

            try {
                const data = await api('/bank-account/verify', {
                    method: 'POST',
                    body: { bank_id: bankSelect.value, account_number: numberInput.value.replace(/\D/g, '') },
                });
                verifiedName = data.name;
                verifyResult.innerHTML = `<span style="color:var(--ma-success)">✓ ${esc(data.name)}</span>`;
                haptic('success');
            } catch (error) {
                verifiedName = null;
                verifyResult.textContent = errMsg(error);
            } finally {
                verifyButton.disabled = false;
                refresh();
            }
        });
    }

    saveButton.addEventListener('click', async () => {
        saveButton.disabled = true;
        saveButton.textContent = 'Saving…';

        const body = isNigerian
            ? { bank_id: bankSelect.value, account_number: numberInput.value.replace(/\D/g, '') }
            : {
                bank_id: bankSelect.value,
                account_number: numberInput.value.trim(),
                account_name: nameInput.value.trim(),
                confirmed: true,
            };

        try {
            await api('/bank-account', { method: 'POST', body }).catch(async (error) => {
                // Already has one (e.g. set elsewhere) → update instead.
                if (error?.status === 422) {
                    await api('/bank-account', { method: 'PUT', body });
                    return;
                }
                throw error;
            });
            haptic('success');
            toast('Payout account saved', 'success');
            renderWithdraw(currentViewOf(shell));
        } catch (error) {
            toast(errMsg(error), 'error');
            saveButton.disabled = false;
            saveButton.textContent = 'Save payout account';
        }
    });

    return card;
}

/* ── Withdrawal request ───────────────────────────────────────────── */

function requestForm(wallets, spendable, bankAccount, user) {
    const card = el(`
        <div class="ma-card mb-3">
            <div class="ma-heading mb-3 text-base font-bold">Request a withdrawal</div>
            ${user.has_transaction_pin ? '' : `
                <div class="ma-card ma-card-tight mb-3" style="background:color-mix(in srgb, var(--ma-warning) 12%, transparent)">
                    <div class="text-[0.78rem] font-semibold">Set your transaction PIN first</div>
                    <div class="ma-hint text-[0.7rem]">Withdrawals are PIN-protected.</div>
                    <button class="ma-btn ma-btn-sm mt-2" data-setup-pin>Set PIN</button>
                </div>`}
            <label class="ma-hint text-[0.7rem] font-semibold uppercase">From wallet</label>
            <select class="ma-input mt-1" name="wallet">
                ${spendable.map((wallet) => `<option value="${esc(wallet.wallet)}" ${wallet.balance_usd <= 0 ? 'disabled' : ''}>${esc(wallet.label)} — ${money(wallet.balance_usd)}</option>`).join('')}
            </select>
            <label class="ma-hint mt-3 block text-[0.7rem] font-semibold uppercase">Amount (USD)</label>
            <input class="ma-input mt-1" name="amount" type="number" min="5" step="0.01" inputmode="decimal" placeholder="Minimum $5.00">
            <div class="ma-hint mt-1.5 text-[0.72rem]">
                <i class="ri-information-line mr-1"></i>Min $5.00 · fee $0.10 · one pending withdrawal at a time.
            </div>
            <button class="ma-btn ma-btn-primary mt-3" data-withdraw ${user.has_transaction_pin ? '' : 'disabled'}>
                <i class="ri-bank-line"></i> Withdraw
            </button>
        </div>`);

    card.querySelector('[data-setup-pin]')?.addEventListener('click', () => navigate('settings', { section: 'security' }));

    const button = card.querySelector('[data-withdraw]');

    button.addEventListener('click', async () => {
        const wallet = card.querySelector('[name="wallet"]').value;
        const amount = parseFloat(card.querySelector('[name="amount"]').value);

        if (!wallet) return toast('Choose a wallet to withdraw from.', 'error');
        if (!Number.isFinite(amount) || amount < 5) return toast('Minimum withdrawal is $5.00.', 'error');
        if (!bankAccount) return toast('Set up your payout account first.', 'error');

        const pin = await askPin('Confirm withdrawal PIN');
        if (!pin) return;

        button.disabled = true;
        button.textContent = 'Submitting…';

        try {
            const result = await api('/withdrawals', { method: 'POST', body: { wallet, amount, pin } });
            haptic('success');
            const withdrawal = result?.withdrawal;
            toast(`Withdrawal of ${money(amount)} submitted${withdrawal?.local_amount ? ` — ${withdrawal.local_amount.toLocaleString()} ${withdrawal.local_currency ?? ''}` : ''}`, 'success', 4200);
            renderWithdraw(currentViewOf(card));
        } catch (error) {
            toast(errMsg(error), 'error');
            button.disabled = false;
            button.innerHTML = '<i class="ri-bank-line"></i> Withdraw';
        }
    });

    return card;
}

function recentList(items) {
    const card = el(`<div class="ma-card"><div class="ma-heading mb-1 text-base font-bold">Recent withdrawals</div><div data-rows></div></div>`);
    const rows = card.querySelector('[data-rows]');

    if (!items.length) {
        rows.appendChild(el('<div class="ma-hint py-3 text-center text-[0.78rem]">No withdrawals yet.</div>'));
        return card;
    }

    items.forEach((withdrawal) => {
        const statusChip = {
            completed: 'ma-chip-success',
            processing: 'ma-chip-warning',
            pending: 'ma-chip-warning',
            failed: 'ma-chip-danger',
        }[withdrawal.status] ?? 'ma-chip-muted';

        rows.appendChild(el(`
            <div class="ma-row">
                <div class="ma-icon"><i class="ri-bank-line"></i></div>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-semibold">${money(withdrawal.amount_usd)} · ${esc(withdrawal.wallet_label ?? withdrawal.wallet)}</div>
                    <div class="ma-hint truncate text-xs">${esc(withdrawal.reference ?? '')} · ${esc(when(withdrawal.created_at))}</div>
                </div>
                <span class="ma-chip ${statusChip}">${esc(withdrawal.status ?? '')}</span>
            </div>`));
    });

    return card;
}

/** Climb to the screen root so we can re-render after state changes. */
function currentViewOf(node) {
    return node.closest('#view');
}
