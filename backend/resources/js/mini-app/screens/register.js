/**
 * Registration / onboarding — "Create Account" screen styled after the
 * Nextel Connect onboarding mock. Preloads the Telegram profile (name +
 * username), country picker modal with flags + dial codes, and binds the
 * Telegram identity right after a successful POST /register.
 */

import { api, setToken } from '../api.js';
import { el, esc, toast, errMsg } from '../ui.js';
import { authShell, pillField, checkRow, DIAL_CODES } from '../auth-ui.js';
import { initData, telegramUser, haptic } from '../telegram.js';
import { navigate, enableBack, handoffToWeb } from '../router.js';
import { setUser } from '../store.js';

export async function renderRegister(view) {
    enableBack('login');

    const tgUser = telegramUser();
    const prefillName = [tgUser?.first_name, tgUser?.last_name].filter(Boolean).join(' ');

    view.innerHTML = '';

    const shell = authShell({
        title: 'Create Account',
        subtitle: 'Join Nextel Connect and get started today',
        backTo: 'login',
        centered: true,
    });

    view.appendChild(shell);
    const body = shell.querySelector('.ma-auth-body');

    body.appendChild(pillField({ label: 'Full name', name: 'full_name', icon: 'ri-user-3-line', placeholder: 'Your name', value: prefillName }));
    body.appendChild(pillField({ label: 'Email', name: 'email', icon: 'ri-mail-line', type: 'email', placeholder: 'Enter your email address' }));
    body.appendChild(pillField({ label: 'Username', name: 'username', icon: 'ri-at-line', placeholder: 'Choose preferred username', value: tgUser?.username ?? '', maxlength: 30 }));

    // Country selector (opens the modal).
    let selectedCountry = { code: 'NG', name: 'Nigeria', flag: '🇳🇬' };
    const countryField = el(`
        <div class="mb-4">
            <label class="mb-1.5 block text-[0.85rem] font-medium">Country</label>
            <button class="ma-input ma-pill w-full text-left" data-country style="display:flex;align-items:center;gap:.6rem">
                <span style="color:var(--ma-muted)"><i class="ri-earth-line"></i></span>
                <span data-flag>🇳🇬</span>
                <span data-name class="flex-1">Nigeria</span>
                <i class="ri-arrow-down-s-line" style="color:var(--ma-muted)"></i>
            </button>
        </div>`);
    body.appendChild(countryField);

    // Phone with dial prefix.
    const phoneField = el(`
        <div class="mb-4">
            <label class="mb-1.5 block text-[0.85rem] font-medium">Phone number</label>
            <div class="relative">
                <span class="absolute left-5 top-1/2 -translate-y-1/2 text-[0.9rem] font-bold" style="color:var(--ma-primary)" data-dial>+234</span>
                <input class="ma-input ma-pill" name="phone" type="tel" inputmode="numeric" placeholder="Phone number" style="padding-left:4.6rem">
                <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-[1.05rem]" style="color:var(--ma-muted)"><i class="ri-phone-line"></i></span>
            </div>
        </div>`);
    body.appendChild(phoneField);

    body.appendChild(pillField({ label: 'Referral code (optional)', name: 'referral_code', icon: 'ri-gift-line', placeholder: 'Referral code', maxlength: 30 }));
    body.appendChild(pillField({ label: 'Password', name: 'password', icon: 'ri-lock-2-line', type: 'password', placeholder: 'At least 8 characters' }));
    body.appendChild(pillField({ label: 'Confirm password', name: 'password_confirmation', icon: 'ri-lock-2-line', type: 'password', placeholder: 'Repeat password' }));

    body.appendChild(checkRow('I agree to the Terms & Conditions', 'agree_terms'));

    const submit = el('<button class="ma-btn ma-btn-primary mt-5" style="border-radius:16px"><i class="ri-user-add-line"></i> Create Account</button>');
    body.appendChild(submit);

    // ── Country modal ──────────────────────────────────────────────
    countryField.querySelector('[data-country]').addEventListener('click', () => {
        openCountryModal(view, (country) => {
            selectedCountry = country;
            countryField.querySelector('[data-flag]').textContent = country.flag ?? '🌍';
            countryField.querySelector('[data-name]').textContent = country.name;
            phoneField.querySelector('[data-dial]').textContent = DIAL_CODES[country.code] ?? '';
            haptic('light');
        });
    });

    // ── Submit ─────────────────────────────────────────────────────
    submit.addEventListener('click', async () => {
        const get = (name) => body.querySelector(`[name="${name}"]`)?.value ?? '';

        const fullName = get('full_name').trim();
        const email = get('email').trim();
        const username = get('username').trim().replace(/^@/, '');
        const phoneLocal = get('phone').replace(/[^\d]/g, '');
        const referral = get('referral_code').trim().replace(/^@/, '');
        const password = get('password');
        const confirmation = get('password_confirmation');
        const agreed = body.querySelector('[name="agree_terms"]').checked;

        if (!fullName) return toast('Enter your full name.', 'error');
        if (!/^\S+@\S+\.\S+$/.test(email)) return toast('Enter a valid email address.', 'error');
        if (username.length < 3) return toast('Username needs at least 3 characters.', 'error');
        if (!phoneLocal) return toast('Enter your phone number.', 'error');
        if (password.length < 8) return toast('Password must be at least 8 characters.', 'error');
        if (password !== confirmation) return toast('Passwords do not match.', 'error');
        if (!agreed) return toast('Please agree to the Terms & Conditions to continue.', 'error');

        const dial = DIAL_CODES[selectedCountry.code] ?? '';
        const phone = `${dial}${phoneLocal}`.slice(0, 20);

        submit.disabled = true;
        submit.textContent = 'Creating your account…';

        try {
            const data = await api('/register', {
                method: 'POST',
                auth: false,
                body: {
                    full_name: fullName,
                    username,
                    email,
                    phone,
                    country: selectedCountry.code,
                    password,
                    password_confirmation: confirmation,
                    referral_code: referral || null,
                    agree_terms: true,
                    // Lets the backend DM the verification code to Telegram
                    // and auto-verify this session.
                    init_data: initData() || null,
                },
            });

            setToken(data.token, true);
            setUser(data.user);

            // Bind this Telegram account to the new Nextel account.
            await linkTelegramQuietly();
            haptic('success');

            if (data.verification_required) {
                toast(data.verification_channel === 'telegram'
                    ? 'Account created — check your Telegram for the code.'
                    : 'Account created — verify your email to finish.', 'success', 3500);
                return navigate('verify', { email, channel: data.verification_channel ?? 'email' });
            }

            toast('Welcome to Nextel! 🎉', 'success', 3500);
            await handoffToWeb();
        } catch (error) {
            toast(errMsg(error), 'error');
            submit.disabled = false;
            submit.innerHTML = '<i class="ri-user-add-line"></i> Create Account';
        }
    });
}

async function linkTelegramQuietly() {
    const payload = initData();
    if (!payload) return;

    try {
        await api('/telegram/link', { method: 'POST', body: { init_data: payload } });
    } catch {
        /* non-fatal */
    }
}

/** Centre-screen country picker with search, flags and dial codes. */
function openCountryModal(view, onPick) {
    const backdrop = el(`
        <div class="ma-sheet-backdrop" style="align-items:center;padding:1.25rem">
            <div class="w-full max-w-[22rem] rounded-[28px] p-5" style="background:var(--ma-surface);max-height:75dvh;display:flex;flex-direction:column">
                <h3 class="ma-heading mb-3 text-lg font-bold" style="color:var(--ma-primary)">Select Country</h3>
                <div class="relative mb-2">
                    <input class="ma-input ma-pill" data-search placeholder="Search country" autocomplete="off">
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto" data-list>
                    <div class="flex justify-center py-6"><div class="ma-spinner"></div></div>
                </div>
            </div>
        </div>`);

    const list = backdrop.querySelector('[data-list]');
    const search = backdrop.querySelector('[data-search]');
    let countries = [];

    const paint = (filter = '') => {
        const term = filter.trim().toLowerCase();
        const rows = countries.filter((country) => !term || country.name.toLowerCase().includes(term));

        list.innerHTML = '';

        rows.forEach((country) => {
            const row = el(`
                <button class="flex w-full items-center gap-3 rounded-2xl px-2 py-3 text-left" data-code="${esc(country.code)}">
                    <span class="text-xl">${esc(country.flag ?? '🌍')}</span>
                    <span class="flex-1 text-[0.95rem]" style="color:var(--ma-text)">${esc(country.name)}</span>
                    <span class="ma-hint text-[0.8rem]">${esc(DIAL_CODES[country.code] ?? '')}</span>
                </button>`);

            row.addEventListener('click', () => {
                onPick(country);
                backdrop.remove();
            });

            list.appendChild(row);
        });

        if (!rows.length) {
            list.innerHTML = '<div class="ma-hint py-6 text-center text-sm">No country matches.</div>';
        }
    };

    search.addEventListener('input', () => paint(search.value));

    api('/countries', { auth: false }).then((data) => {
        countries = data.countries ?? [];
        paint();
    }).catch((error) => {
        list.innerHTML = `<div class="ma-hint py-6 text-center text-sm">${esc(errMsg(error))}</div>`;
    });

    backdrop.addEventListener('click', (event) => {
        if (event.target === backdrop) backdrop.remove();
    });

    view.appendChild(backdrop);
    setTimeout(() => search.focus(), 120);
}
