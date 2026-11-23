/**
 * Profile & Settings — the web Account area in one screen: profile edit +
 * avatar (PUT /profile, POST /profile/avatar), display-currency switcher
 * (mirrors the web CurrencySwitcher), password change, transaction PIN
 * setup/change/reset, Telegram link management, email verification, logout.
 */

import { api, getToken } from '../api.js';
import { el, esc, skeleton, errorState, emptyState, toast, errMsg, appBar } from '../ui.js';
import { navigate, enableBack } from '../router.js';
import { state, setUser, resetState } from '../store.js';
import { haptic } from '../telegram.js';

export async function renderSettings(view, params = {}) {
    enableBack('profile');

    view.innerHTML = '';
    view.appendChild(appBar('Profile & Settings'));
    view.appendChild(skeleton(5));

    let user;
    let countries = [];

    try {
        user = (await api('/user')).user ?? {};
        countries = (await api('/countries').catch(() => ({ countries: [] }))).countries ?? [];
    } catch (error) {
        view.innerHTML = '';
        view.appendChild(appBar('Profile & Settings'));
        view.appendChild(errorState(errMsg(error), () => renderSettings(view)));
        return;
    }

    setUser(user);

    view.innerHTML = '';
    view.appendChild(appBar('Profile & Settings'));

    const shell = el('<div class="space-y-3"></div>');
    view.appendChild(shell);

    shell.appendChild(profileSection(user));
    shell.appendChild(currencySection(user, countries));
    shell.appendChild(securitySection(user, params?.section === 'security'));
    shell.appendChild(telegramSection(user));
    shell.appendChild(emailSection(user));

    const logout = el('<button class="ma-btn ma-btn-danger mt-1"><i class="ri-logout-box-r-line"></i> Log out</button>');
    logout.addEventListener('click', async () => {
        try {
            await api('/logout', { method: 'POST' });
        } catch {
            /* token may already be dead */
        }
        resetState();
        await navigate('login');
    });
    shell.appendChild(logout);
}

/* ── Profile ──────────────────────────────────────────────────────── */

function profileSection(user) {
    const card = el(`
        <div class="ma-card">
            <div class="mb-3 flex items-center gap-3">
                ${user.avatar_url
                    ? `<img src="${esc(user.avatar_url)}" alt="" class="h-14 w-14 rounded-full object-cover" data-avatar-img>`
                    : `<span class="flex h-14 w-14 items-center justify-center rounded-full text-lg font-bold" style="background:var(--ma-accent);color:var(--ma-accent-text)" data-avatar-img>${esc(initials(user))}</span>`}
                <div class="min-w-0 flex-1">
                    <div class="truncate font-bold">${esc(user.full_name ?? '')}</div>
                    <div class="ma-hint truncate text-xs">@${esc(user.username ?? '')} · ${esc(user.email ?? '')}</div>
                </div>
                <button class="ma-icon" data-avatar-pick aria-label="Change photo"><i class="ri-camera-line"></i></button>
                <input type="file" accept="image/*" class="hidden" data-avatar-file>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <input class="ma-input" name="first_name" value="${esc(user.first_name ?? '')}" placeholder="First name">
                <input class="ma-input" name="last_name" value="${esc(user.last_name ?? '')}" placeholder="Last name">
            </div>
            <input class="ma-input mt-2" name="phone" value="${esc(user.phone ?? '')}" placeholder="Phone number">
            <input class="ma-input mt-2" name="telegram" value="${esc(telegramHandle(user.telegram_url))}" placeholder="Telegram username (optional)">
            <textarea class="ma-input mt-2" name="biography" rows="2" maxlength="300" placeholder="Bio (optional)">${esc(user.biography ?? '')}</textarea>
            <button class="ma-btn ma-btn-primary mt-3" data-save>Save profile</button>
        </div>`);

    // Avatar upload (multipart — bypasses the JSON api() helper).
    const fileInput = card.querySelector('[data-avatar-file]');
    card.querySelector('[data-avatar-pick]').addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', async () => {
        const file = fileInput.files?.[0];
        if (!file) return;
        if (file.size > 4 * 1024 * 1024) return toast('Image must be under 4 MB.', 'error');

        const body = new FormData();
        body.append('photo', file);

        try {
            const response = await fetch('/api/v1/profile/avatar', {
                method: 'POST',
                headers: { Authorization: `Bearer ${getToken()}`, Accept: 'application/json' },
                body,
            });
            const payload = await response.json();

            if (!response.ok || !payload.success) {
                throw new Error(payload.message ?? 'Upload failed.');
            }

            haptic('success');
            toast('Photo updated', 'success');

            const avatarUrl = payload.data?.user?.avatar_url ?? payload.data?.avatar_url;
            if (avatarUrl && state.user) setUser({ ...state.user, avatar_url: avatarUrl });
        } catch (error) {
            toast(error.message, 'error');
        } finally {
            fileInput.value = '';
        }
    });

    card.querySelector('[data-save]').addEventListener('click', async () => {
        const button = card.querySelector('[data-save]');
        button.disabled = true;
        button.textContent = 'Saving…';

        const body = {
            first_name: card.querySelector('[name="first_name"]').value.trim(),
            last_name: card.querySelector('[name="last_name"]').value.trim() || null,
            phone: card.querySelector('[name="phone"]').value.trim() || null,
            telegram: card.querySelector('[name="telegram"]').value.trim() || null,
            biography: card.querySelector('[name="biography"]').value.trim() || null,
        };

        try {
            const result = await api('/profile', { method: 'PUT', body });
            if (result?.user) setUser(result.user);
            haptic('success');
            toast('Profile saved', 'success');
        } catch (error) {
            toast(errMsg(error), 'error');
        } finally {
            button.disabled = false;
            button.textContent = 'Save profile';
        }
    });

    return card;
}

/* ── Display currency (web CurrencySwitcher parity) ───────────────── */

function currencySection(user, countries) {
    const codes = ['USD', ...new Set(countries.map((country) => country.currency_code).filter(Boolean))];
    const current = user.display_currency ?? 'USD';

    const card = el(`
        <div class="ma-card">
            <div class="text-sm font-bold">Display currency</div>
            <div class="ma-hint mt-0.5 text-[0.72rem]">Balances are stored in USD — this mirrors the web switcher.</div>
            <div class="mt-2.5 flex flex-wrap gap-2">
                ${codes.map((code) => `<button class="ma-chip ${code === current ? 'ma-chip-accent' : 'ma-chip-muted'}" data-code="${esc(code)}">${esc(code)}</button>`).join('')}
            </div>
        </div>`);

    card.addEventListener('click', async (event) => {
        const chip = event.target.closest('[data-code]');
        if (!chip || chip.dataset.code === current) return;

        try {
            // The profile endpoint validates the whole payload, so re-send the
            // current values alongside the currency preference.
            await api('/profile', {
                method: 'PUT',
                body: {
                    first_name: user.first_name ?? '',
                    last_name: user.last_name ?? null,
                    phone: user.phone ?? null,
                    telegram: telegramHandle(user.telegram_url) || null,
                    biography: user.biography ?? null,
                    display_currency: chip.dataset.code === 'USD' ? null : chip.dataset.code,
                },
            });
            haptic('success');
            toast(`Display currency set to ${chip.dataset.code}`, 'success');
            if (state.user) setUser({ ...state.user, display_currency: chip.dataset.code === 'USD' ? null : chip.dataset.code });
            card.querySelectorAll('[data-code]').forEach((other) => {
                const active = other.dataset.code === chip.dataset.code;
                other.classList.toggle('ma-chip-accent', active);
                other.classList.toggle('ma-chip-muted', !active);
            });
        } catch (error) {
            toast(errMsg(error), 'error');
        }
    });

    return card;
}

/* ── Security: password + PIN ─────────────────────────────────────── */

function securitySection(user, expanded = false) {
    const hasPin = Boolean(user.has_transaction_pin);

    const card = el(`
        <div class="ma-card">
            <div class="text-sm font-bold"><i class="ri-shield-check-line mr-1" style="color:var(--ma-primary-light)"></i>Security</div>

            <div class="mt-3 border-t pt-3" style="border-color:color-mix(in srgb, var(--ma-border) 40%, transparent)">
                <div class="text-[0.8rem] font-semibold">Change password</div>
                <input class="ma-input mt-2" type="password" data-cur autocomplete="current-password" placeholder="Current password">
                <input class="ma-input mt-2" type="password" data-new autocomplete="new-password" placeholder="New password">
                <input class="ma-input mt-2" type="password" data-new2 autocomplete="new-password" placeholder="Repeat new password">
                <button class="ma-btn mt-2" data-password>Update password</button>
            </div>

            <div class="mt-3 border-t pt-3" style="border-color:color-mix(in srgb, var(--ma-border) 40%, transparent)">
                <div class="flex items-center justify-between">
                    <div class="text-[0.8rem] font-semibold">Transaction PIN</div>
                    <span class="ma-chip ${hasPin ? 'ma-chip-success' : 'ma-chip-warning'}">${hasPin ? 'Set' : 'Not set'}</span>
                </div>

                ${hasPin ? `
                    <input class="ma-input mt-2" type="password" inputmode="numeric" maxlength="4" data-pin-cur placeholder="Current PIN">
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <input class="ma-input" type="password" inputmode="numeric" maxlength="4" data-pin-new placeholder="New PIN">
                        <input class="ma-input" type="password" inputmode="numeric" maxlength="4" data-pin-new2 placeholder="Repeat new">
                    </div>
                    <div class="mt-2 flex gap-2">
                        <button class="ma-btn" data-pin-change>Change PIN</button>
                        <button class="ma-btn ma-btn-ghost ma-btn-auto" data-pin-reset>Forgot?</button>
                    </div>
                    <div class="mt-2 hidden" data-reset-box>
                        <input class="ma-input" data-reset-token placeholder="Reset token from support/vendor">
                        <button class="ma-btn mt-2" data-reset-go>Reset PIN with token</button>
                    </div>` : `
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <input class="ma-input" type="password" inputmode="numeric" maxlength="4" data-pin-new placeholder="New PIN">
                        <input class="ma-input" type="password" inputmode="numeric" maxlength="4" data-pin-new2 placeholder="Repeat new">
                    </div>
                    <button class="ma-btn mt-2" data-pin-setup>Set PIN</button>`}
            </div>
        </div>`);

    const digits = (input) => input.value.replace(/\D/g, '').slice(0, 4);

    card.querySelector('[data-password]')?.addEventListener('click', async () => {
        const current = card.querySelector('[data-cur]').value;
        const password = card.querySelector('[data-new]').value;
        const confirmation = card.querySelector('[data-new2]').value;

        if (!current || !password) return toast('Fill in your current and new password.', 'error');
        if (password !== confirmation) return toast('New passwords do not match.', 'error');

        try {
            await api('/account/password', {
                method: 'PUT',
                body: { current_password: current, password, password_confirmation: confirmation },
            });
            haptic('success');
            toast('Password changed', 'success');
            ['[data-cur]', '[data-new]', '[data-new2]'].forEach((selector) => {
                card.querySelector(selector).value = '';
            });
        } catch (error) {
            toast(errMsg(error), 'error');
        }
    });

    card.querySelector('[data-pin-setup]')?.addEventListener('click', async () => {
        const pin = digits(card.querySelector('[data-pin-new]'));
        const confirmation = digits(card.querySelector('[data-pin-new2]'));

        if (pin.length !== 4) return toast('PIN must be 4 digits.', 'error');
        if (pin !== confirmation) return toast('PINs do not match.', 'error');

        try {
            await api('/account/pin', { method: 'POST', body: { pin, pin_confirmation: confirmation } });
            haptic('success');
            toast('Transaction PIN set', 'success');
            if (state.user) setUser({ ...state.user, has_transaction_pin: true });
            renderSettings(card.closest('#view'));
        } catch (error) {
            toast(errMsg(error), 'error');
        }
    });

    card.querySelector('[data-pin-change]')?.addEventListener('click', async () => {
        const currentPin = digits(card.querySelector('[data-pin-cur]'));
        const pin = digits(card.querySelector('[data-pin-new]'));
        const confirmation = digits(card.querySelector('[data-pin-new2]'));

        if (currentPin.length !== 4 || pin.length !== 4) return toast('PINs must be 4 digits.', 'error');
        if (pin !== confirmation) return toast('New PINs do not match.', 'error');

        try {
            await api('/account/pin', { method: 'PUT', body: { current_pin: currentPin, pin, pin_confirmation: confirmation } });
            haptic('success');
            toast('PIN changed', 'success');
        } catch (error) {
            toast(errMsg(error), 'error');
        }
    });

    const resetBox = card.querySelector('[data-reset-box]');
    card.querySelector('[data-pin-reset]')?.addEventListener('click', () => resetBox.classList.toggle('hidden'));

    card.querySelector('[data-reset-go]')?.addEventListener('click', async () => {
        const token = card.querySelector('[data-reset-token]').value.trim();
        const pin = digits(card.querySelector('[data-pin-new]'));
        const confirmation = digits(card.querySelector('[data-pin-new2]'));

        if (token.length < 6) return toast('Enter your reset token.', 'error');
        if (pin.length !== 4 || pin !== confirmation) return toast('Enter a new 4-digit PIN twice.', 'error');

        try {
            await api('/account/pin/reset', { method: 'POST', body: { token, pin, pin_confirmation: confirmation } });
            haptic('success');
            toast('PIN reset successfully', 'success');
        } catch (error) {
            toast(errMsg(error), 'error');
        }
    });

    if (expanded) {
        setTimeout(() => card.scrollIntoView({ behavior: 'smooth', block: 'start' }), 150);
    }

    return card;
}

/* ── Telegram link ────────────────────────────────────────────────── */

function telegramSection(user) {
    const linked = user.telegram?.linked ?? false;

    const card = el(`
        <div class="ma-card flex items-center justify-between gap-3">
            <div class="min-w-0">
                <div class="text-sm font-bold"><i class="ri-telegram-line mr-1" style="color:var(--ma-info)"></i>Telegram sign-in</div>
                <div class="ma-hint mt-0.5 text-[0.72rem]">${linked
                    ? `Linked${user.telegram?.username ? ` to @${esc(user.telegram.username)}` : ''} — instant open next time.`
                    : 'Not linked — you log in with password each time.'}</div>
            </div>
            ${linked ? '<button class="ma-btn ma-btn-danger ma-btn-sm" data-unlink>Unlink</button>' : '<span class="ma-chip ma-chip-muted">Off</span>'}
        </div>`);

    card.querySelector('[data-unlink]')?.addEventListener('click', async (event) => {
        const button = event.currentTarget;
        button.disabled = true;

        try {
            await api('/telegram/unlink', { method: 'POST' });
            haptic('success');
            toast('Telegram unlinked', 'success');
            renderSettings(card.closest('#view'));
        } catch (error) {
            toast(errMsg(error), 'error');
            button.disabled = false;
        }
    });

    return card;
}

/* ── Email verification ───────────────────────────────────────────── */

function emailSection(user) {
    if (user.onboarding?.email_verified) {
        return el(`
            <div class="ma-card ma-card-tight flex items-center justify-between">
                <span class="text-[0.82rem] font-semibold"><i class="ri-mail-check-line mr-1" style="color:var(--ma-success)"></i>Email verified</span>
                <span class="ma-chip ma-chip-success">${esc(user.email ?? '')}</span>
            </div>`);
    }

    const card = el(`
        <div class="ma-card">
            <div class="text-sm font-bold"><i class="ri-mail-line mr-1" style="color:var(--ma-warning)"></i>Verify your email</div>
            <div class="ma-hint mt-0.5 text-[0.72rem]">Withdrawals and purchases need a verified email.</div>
            <div class="mt-2 flex gap-2">
                <input class="ma-input flex-1" data-code inputmode="numeric" maxlength="6" placeholder="6-digit code">
                <button class="ma-btn ma-btn-primary ma-btn-auto" data-verify>Verify</button>
            </div>
            <button class="ma-btn ma-btn-ghost ma-btn-sm mt-2" data-resend>Resend code</button>
        </div>`);

    card.querySelector('[data-verify]').addEventListener('click', async () => {
        const code = card.querySelector('[data-code]').value.trim();
        if (!code) return toast('Enter the code from your email.', 'error');

        try {
            await api('/email/verify', { method: 'POST', body: { code } });
            haptic('success');
            toast('Email verified 🎉', 'success');
            renderSettings(card.closest('#view'));
        } catch (error) {
            toast(errMsg(error), 'error');
        }
    });

    card.querySelector('[data-resend]').addEventListener('click', async (event) => {
        event.currentTarget.disabled = true;
        try {
            await api('/email/resend', { method: 'POST' });
            toast('Code sent — check your inbox.', 'success');
        } catch (error) {
            toast(errMsg(error), 'error');
            event.currentTarget.disabled = false;
        }
    });

    return card;
}

function initials(user) {
    const letters = `${user?.first_name?.[0] ?? ''}${user?.last_name?.[0] ?? ''}`.trim();
    return letters.toUpperCase() || '?';
}

/** Show the raw handle when the stored value is a t.me URL. */
function telegramHandle(url) {
    return String(url ?? '').replace(/^https?:\/\/(www\.)?t\.me\//, '').replace(/^@/, '');
}
