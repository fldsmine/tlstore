/**
 * Wallet hub — all balances (GET /wallets), payout-account status,
 * withdrawal entry point, and the paginated transaction history with the
 * same filters the web Transactions page offers.
 */

import { api } from '../api.js';
import { el, esc, money, skeleton, errorState, emptyState, when, errMsg, appBar } from '../ui.js';
import { navigate } from '../router.js';
import { haptic } from '../telegram.js';

const FILTERS = [
    { id: 'all', label: 'All' },
    { id: 'earned', label: 'Earned' },
    { id: 'pending', label: 'Pending' },
    { id: 'withdrawn', label: 'Withdrawn' },
];

let currentFilter = 'all';
let currentPage = 1;
let lastPage = 1;
let loadingMore = false;

export async function renderWallet(view) {
    currentFilter = 'all';
    currentPage = 1;
    await load(view);
}

async function load(view) {
    view.innerHTML = '';
    view.appendChild(appBar('Wallet'));
    view.appendChild(skeleton(6));

    let wallets;
    let bankAccount = null;

    try {
        wallets = await api('/wallets');
        bankAccount = (await api('/bank-account').catch(() => null))?.bank_account ?? null;
    } catch (error) {
        view.innerHTML = '';
        view.appendChild(appBar('Wallet'));
        view.appendChild(errorState(errMsg(error), () => load(view)));
        return;
    }

    const shell = el(`
        <div>
            <div class="ma-card text-center">
                <div class="ma-hint text-[0.64rem] font-semibold uppercase tracking-wider">Total balance</div>
                <div class="ma-heading mt-1 text-3xl font-bold">${money(wallets.total_balance_usd)}</div>
                <div class="ma-hint mt-1 text-xs">Lifetime earned: ${money(wallets.lifetime_earnings_usd)}</div>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-2.5">
                <button class="ma-btn ma-btn-primary" data-go="withdraw"><i class="ri-bank-line"></i> Withdraw</button>
                <button class="ma-btn" data-go="numbers"><i class="ri-phone-line"></i> Beneficiaries</button>
            </div>

            <button class="ma-card ma-card-tight mt-3 flex w-full items-center gap-3 text-left" data-go="withdraw">
                <span class="ma-icon"><i class="ri-bank-card-line"></i></span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-[0.82rem] font-semibold">${bankAccount
                        ? `Payout: ${esc(bankAccount.account_name || bankAccount.masked_number || 'Saved account')}`
                        : 'Set up your payout account'}</span>
                    <span class="ma-hint block truncate text-[0.7rem]">${bankAccount
                        ? `${esc(bankAccount.bank?.name ?? 'Bank')} · ${esc(bankAccount.masked_number ?? '')}`
                        : 'Bank or mobile money — needed before withdrawing'}</span>
                </span>
                <i class="ri-arrow-right-s-line text-lg" style="color:var(--ma-muted)"></i>
            </button>

            <div class="mt-3 grid grid-cols-2 gap-2" id="balances"></div>

            <div class="no-scrollbar mt-4 flex gap-2 overflow-x-auto pb-1" id="filters"></div>
            <div id="transactions" class="mt-1"></div>
            <div id="more"></div>
        </div>`);

    view.innerHTML = '';
    view.appendChild(appBar('Wallet'));
    view.appendChild(shell);

    const balances = shell.querySelector('#balances');
    (wallets.wallets ?? []).forEach((wallet) => {
        balances.appendChild(el(`
            <div class="ma-card ma-card-tight">
                <div class="ma-hint truncate text-[0.63rem] font-semibold uppercase">${esc(wallet.label)}</div>
                <div class="mt-0.5 truncate text-sm font-bold">${money(wallet.balance_usd)}</div>
            </div>`));
    });

    const filters = shell.querySelector('#filters');
    FILTERS.forEach((filter) => {
        const chip = el(`<button class="ma-chip ${filter.id === currentFilter ? 'ma-chip-accent' : 'ma-chip-muted'} whitespace-nowrap" data-filter="${filter.id}">${filter.label}</button>`);
        chip.addEventListener('click', () => {
            currentFilter = filter.id;
            currentPage = 1;
            haptic('light');
            loadTransactions(shell);
        });
        filters.appendChild(chip);
    });

    shell.addEventListener('click', (event) => {
        const go = event.target.closest('[data-go]');
        if (!go) return;
        haptic('light');
        navigate(go.dataset.go);
    });

    await loadTransactions(shell, true);
}

async function loadTransactions(shell, reset = false) {
    const list = shell.querySelector('#transactions');
    const moreSlot = shell.querySelector('#more');
    if (!list) return;

    if (reset) {
        list.innerHTML = '';
        moreSlot.innerHTML = '';
    }

    let data;

    try {
        data = await api(`/transactions?filter=${encodeURIComponent(currentFilter)}&page=${currentPage}&per_page=20`);
    } catch (error) {
        if (reset) list.appendChild(errorState(errMsg(error), () => loadTransactions(shell, true)));
        return;
    }

    const items = data.items ?? [];
    lastPage = data.pagination?.last_page ?? 1;

    if (reset) {
        shell.querySelectorAll('[data-filter]').forEach((chip) => {
            const active = chip.dataset.filter === currentFilter;
            chip.classList.toggle('ma-chip-accent', active);
            chip.classList.toggle('ma-chip-muted', !active);
        });

        if (!items.length) {
            list.appendChild(emptyState('ri-exchange-line', 'No transactions', 'Your activity will show up here.'));
            return;
        }
    }

    items.forEach((tx) => list.appendChild(buildRow(tx)));

    moreSlot.innerHTML = '';

    if (currentPage < lastPage) {
        const button = el('<button class="ma-btn mt-3">Load more</button>');
        button.addEventListener('click', async () => {
            if (loadingMore) return;
            loadingMore = true;
            button.disabled = true;
            button.textContent = 'Loading…';
            currentPage += 1;
            await loadTransactions(shell);
            loadingMore = false;
        });
        moreSlot.appendChild(button);
    }
}

function buildRow(tx) {
    const credit = Boolean(tx.is_credit);

    return el(`
        <div class="ma-row">
            <div class="ma-icon" style="${credit ? '' : 'background:color-mix(in srgb, var(--ma-danger) 12%, transparent);color:var(--ma-danger);border-color:transparent'}">
                <i class="${credit ? 'ri-arrow-down-circle-line' : 'ri-arrow-up-circle-line'}"></i>
            </div>
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm font-semibold">${esc(tx.type_name ?? tx.type ?? 'Transaction')}</div>
                <div class="ma-hint truncate text-xs">${esc(tx.description ?? tx.reference ?? '')} · ${esc(when(tx.created_at))}</div>
            </div>
            <div class="text-right">
                <div class="text-sm font-bold" style="color:${credit ? 'var(--ma-success)' : 'var(--ma-danger)'}">
                    ${credit ? '+' : '−'}${money(tx.amount_usd)}
                </div>
                <div class="ma-hint text-[0.65rem]">${esc(tx.wallet_label ?? '')}</div>
            </div>
        </div>`);
}
