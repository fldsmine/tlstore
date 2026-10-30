/**
 * Shared building blocks for the auth screens (login / register / forgot /
 * verify), styled after the Nextel Connect onboarding mock: cream canvas,
 * deep-green decorative circles, centred logo, Nohemi headline and pill
 * inputs with leading icons.
 */

import { el, esc } from './ui.js';
import { navigate } from './router.js';
import { haptic } from './telegram.js';

/** Dial prefixes for the countries Nextel operates in (presentational). */
export const DIAL_CODES = {
    NG: '+234', GH: '+233', CM: '+237', ZA: '+27', KE: '+254', UG: '+256',
    SL: '+232', MW: '+265', ZM: '+260', LR: '+231', GM: '+220', TZ: '+255',
    RW: '+250', BW: '+267', ZW: '+263', CI: '+225', BF: '+226', TG: '+228',
    SN: '+221', BJ: '+229', ML: '+223', NE: '+227',
};

/**
 * Full-bleed auth canvas with the decorative circles + logo.
 * Returns the shell; children are appended inside .ma-auth-inner.
 */
export function authShell({ title, subtitle, backTo = null, centered = false } = {}) {
    const shell = el(`
        <div class="ma-auth">
            <span class="ma-auth-circle" style="top:-90px;right:-110px;width:230px;height:230px"></span>
            <span class="ma-auth-circle" style="top:250px;left:-95px;width:130px;height:130px"></span>
            <span class="ma-auth-circle" style="bottom:-120px;left:-80px;width:230px;height:230px"></span>
            <div class="ma-auth-inner">
                ${backTo
                    ? `<button class="ma-auth-back" data-back><i class="ri-arrow-left-s-line"></i> Back</button>`
                    : '<span class="ma-auth-back" style="visibility:hidden"><i class="ri-arrow-left-s-line"></i> Back</span>'}
                <img src="/images/public/logo-coloured.png" alt="Nextel Connect"
                     class="mx-auto -mt-6 h-14 w-auto object-contain"
                     onerror="this.style.display='none'">
                <h1 class="ma-heading mt-5 ${centered ? 'text-center' : ''} text-[26px] font-bold" style="color:var(--ma-primary)">${esc(title)}</h1>
                ${subtitle ? `<p class="ma-hint mt-1 ${centered ? 'text-center' : ''} text-[0.85rem]">${esc(subtitle)}</p>` : ''}
                <div class="ma-auth-body"></div>
                <p class="ma-auth-foot">
                    <span style="width:10px;height:10px;border-radius:999px;background:var(--ma-secondary);display:inline-block"></span>
                    Trusted network for Nigerians
                </p>
            </div>
        </div>`);

    if (backTo) {
        shell.querySelector('[data-back]').addEventListener('click', () => {
            haptic('light');
            navigate(backTo);
        });
    }

    return shell;
}

/** Label + pill input with leading icon (and optional password eye toggle). */
export function pillField({ label, name, icon, type = 'text', placeholder = '', value = '', inputmode = null, maxlength = null }) {
    const field = el(`
        <div class="mb-4">
            <label class="mb-1.5 block text-[0.85rem] font-medium">${esc(label)}</label>
            <div class="relative">
                <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-[1.05rem]" style="color:var(--ma-muted)"><i class="${icon}"></i></span>
                <input class="ma-input ma-pill" name="${name}" type="${type}" placeholder="${esc(placeholder)}"
                       value="${esc(value)}" autocomplete="off" ${inputmode ? `inputmode="${inputmode}"` : ''} ${maxlength ? `maxlength="${maxlength}"` : ''}>
                ${type === 'password' ? `<button type="button" class="absolute right-4 top-1/2 -translate-y-1/2 text-[1.05rem]" style="color:var(--ma-muted)" data-eye aria-label="Show password"><i class="ri-eye-line"></i></button>` : ''}
            </div>
        </div>`);

    const input = field.querySelector('input');
    const eye = field.querySelector('[data-eye]');

    eye?.addEventListener('click', () => {
        const hidden = input.type === 'password';
        input.type = hidden ? 'text' : 'password';
        eye.innerHTML = `<i class="${hidden ? 'ri-eye-off-line' : 'ri-eye-line'}"></i>`;
    });

    return field;
}

/** Square brand checkbox row. */
export function checkRow(text, name) {
    return el(`
        <label class="flex cursor-pointer items-center gap-2 text-[0.82rem]" style="color:var(--ma-muted)">
            <input type="checkbox" name="${name}" class="ma-check"> ${esc(text)}
        </label>`);
}
