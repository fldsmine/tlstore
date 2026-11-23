/**
 * Profile tab — account overview (GET /user) with onboarding state and
 * entry points into the full Settings screen (profile, currency, security,
 * Telegram, logout).
 */

import { api, clearSession } from '../api.js';
import { esc, skeleton, errorState, toast, initials, appBar } from '../ui.js';
import { navigate } from '../router.js';
import { setUser, resetState } from '../store.js';
import { haptic } from '../telegram.js';

export async function renderProfile(view) {
    view.innerHTML = '';
    view.appendChild(appBar('Profile'));
    view.appendChild(skeleton(4));

    let data;

    try {
        data = await api('/user');
    } catch (error) {
        view.innerHTML = '';
        view.appendChild(appBar('Profile'));
        view.appendChild(errorState(error.message, () => renderProfile(view)));
        return;
    }

    const user = data.user ?? {};
    const onboarding = user.onboarding ?? {};
    const telegram = user.telegram ?? null;

    setUser(user);

    view.innerHTML = '';
    view.appendChild(appBar('Profile', user.package ? esc(user.package.name) : null));

    const shell = elRoot();
    view.appendChild(shell);

    shell.innerHTML = `
        <div class="ma-card flex items-center gap-3">
            ${user.avatar_url
                ? `<img src="${esc(user.avatar_url)}" alt="" class="h-14 w-14 rounded-full object-cover">`
                : `<div class="flex h-14 w-14 items-center justify-center rounded-full text-lg font-bold" style="background:var(--ma-accent);color:var(--ma-accent-text)">${esc(initials(user.first_name, user.last_name))}</div>`}
            <div class="min-w-0 flex-1">
                <div class="truncate font-bold">${esc(user.full_name ?? '')}</div>
                <div class="ma-hint truncate text-xs">@${esc(user.username)} · ${esc(user.email ?? '')}</div>
                <div class="ma-hint truncate text-xs">${esc(user.phone ?? '')} · ${esc(user.country ?? '')}</div>
            </div>
        </div>

        <div class="ma-card mt-3">
            <div class="ma-hint mb-2 text-xs font-semibold uppercase tracking-wider">Account setup</div>
            ${setupRow('Email verified', onboarding.email_verified)}
            ${setupRow('Package activated', onboarding.activated)}
            ${setupRow('Transaction PIN', onboarding.pin_set)}
        </div>

        <div class="ma-card mt-3 space-y-1">
            <button class="ma-row w-full" data-open="settings">
                <span class="ma-icon"><i class="ri-settings-3-line"></i></span>
                <span class="min-w-0 flex-1 text-left">
                    <span class="block truncate text-sm font-semibold">Profile & Settings</span>
                    <span class="ma-hint block truncate text-[0.72rem]">Name, photo, currency, password, PIN</span>
                </span>
                <i class="ri-arrow-right-s-line text-lg" style="color:var(--ma-muted)"></i>
            </button>
            <button class="ma-row w-full" data-open="referrals">
                <span class="ma-icon"><i class="ri-team-line"></i></span>
                <span class="min-w-0 flex-1 text-left">
                    <span class="block truncate text-sm font-semibold">My Team</span>
                    <span class="ma-hint block truncate text-[0.72rem]">Referrals and invite rewards</span>
                </span>
                <i class="ri-arrow-right-s-line text-lg" style="color:var(--ma-muted)"></i>
            </button>
            ${user.is_agent ? `
            <button class="ma-row w-full" data-open="vendor">
                <span class="ma-icon"><i class="ri-vip-crown-line"></i></span>
                <span class="min-w-0 flex-1 text-left">
                    <span class="block truncate text-sm font-semibold">Vendor Dashboard</span>
                    <span class="ma-hint block truncate text-[0.72rem]">Mint tokens, transfers, float</span>
                </span>
                <i class="ri-arrow-right-s-line text-lg" style="color:var(--ma-muted)"></i>
            </button>` : ''}
        </div>

        <div class="ma-card mt-3">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm font-semibold"><i class="ri-telegram-line"></i> Telegram sign-in</div>
                    <div class="ma-hint mt-0.5 text-xs">${telegram?.linked
                        ? `Linked${telegram.username ? ' to @' + esc(telegram.username) : ''} — instant open next time.`
                        : 'Not linked — you log in with password each time.'}</div>
                </div>
                ${telegram?.linked
                    ? '<button class="ma-btn ma-btn-danger ma-btn-sm" id="unlink">Unlink</button>'
                    : '<span class="ma-chip ma-chip-muted">Off</span>'}
            </div>
        </div>

        <button class="ma-btn ma-btn-danger mt-4" id="logout"><i class="ri-logout-box-r-line"></i> Log out</button>`;

    shell.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-open]');
        if (!opener) return;
        haptic('light');
        navigate(opener.dataset.open);
    });

    shell.querySelector('#unlink')?.addEventListener('click', async (event) => {
        const button = event.currentTarget;
        button.disabled = true;

        try {
            await api('/telegram/unlink', { method: 'POST' });
            haptic('success');
            toast('Telegram unlinked', 'success');
            await renderProfile(view);
        } catch (error) {
            toast(error.message, 'error');
            button.disabled = false;
        }
    });

    shell.querySelector('#logout').addEventListener('click', async () => {
        try {
            await api('/logout', { method: 'POST' });
        } catch {
            /* token may already be dead — proceed */
        }

        resetState();
        clearSession();
        await navigate('login');
    });
}

function elRoot() {
    const node = document.createElement('div');
    return node;
}

function setupRow(label, done) {
    return `
        <div class="flex items-center justify-between py-1.5 text-sm" style="border-bottom:1px solid color-mix(in srgb, var(--ma-text) 6%, transparent)">
            <span>${esc(label)}</span>
            <i class="${done ? 'ri-checkbox-circle-fill' : 'ri-close-circle-line'}" style="color:${done ? 'var(--ma-success)' : 'var(--ma-hint)'}"></i>
        </div>`;
}
