/**
 * Tiny DOM/UI helpers: element creation, money/date formatting, toasts,
 * and the shared loading/empty/error states used by every screen.
 */

import { haptic } from './telegram.js';
import { state, subscribe } from './store.js';
import { navigate } from './router.js';

/** Create an element from an HTML string (single root node expected). */
export function el(html) {
    const template = document.createElement('template');
    template.innerHTML = html.trim();
    return template.content.firstElementChild;
}

/** Escape untrusted strings before interpolating into HTML. */
export function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    })[char]);
}

const usd = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

export function money(value) {
    const number = Number(value);
    return usd.format(Number.isFinite(number) ? number : 0);
}

export function when(iso) {
    if (!iso) return '';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '';

    const today = new Date();
    const sameDay = date.toDateString() === today.toDateString();

    return sameDay
        ? date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        : date.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

export function initials(first, last) {
    const letters = `${first?.[0] ?? ''}${last?.[0] ?? ''}`.trim();
    return letters.toUpperCase() || '?';
}

/** Transient toast. type: 'info' | 'success' | 'error' */
export function toast(message, type = 'info', duration = 2600) {
    document.querySelectorAll('.ma-toast').forEach((node) => node.remove());

    const node = el(`<div class="ma-toast ${type === 'error' ? 'is-error' : type === 'success' ? 'is-success' : ''}">${esc(message)}</div>`);
    document.body.appendChild(node);

    if (type === 'error') haptic('error');
    if (type === 'success') haptic('success');

    setTimeout(() => node.remove(), duration);
}

export function skeleton(rows = 4) {
    const heights = [56, 88, 72, 72, 64, 64];
    const blocks = Array.from({ length: rows }, (_, index) =>
        `<div class="ma-skeleton" style="height:${heights[index % heights.length]}px;margin-bottom:0.75rem"></div>`);
    return el(`<div aria-hidden="true">${blocks.join('')}</div>`);
}

export function emptyState(icon, title, subtitle = '') {
    return el(`<div class="ma-empty"><i class="${icon}"></i><div class="font-semibold" style="color:var(--ma-text)">${esc(title)}</div><div class="mt-1 text-sm">${esc(subtitle)}</div></div>`);
}

export function errorState(message, onRetry) {
    const node = el(`
        <div class="ma-empty">
            <i class="ri-error-warning-line"></i>
            <div class="font-semibold" style="color:var(--ma-text)">Couldn’t load this</div>
            <div class="mt-1 text-sm">${esc(message)}</div>
            <button class="ma-btn ma-btn-primary mt-4" style="max-width:12rem;margin-left:auto;margin-right:auto">Try again</button>
        </div>`);

    node.querySelector('button').addEventListener('click', onRetry);
    return node;
}

/** Section title row. */
export function sectionTitle(text, action = null) {
    const node = el(`<div class="flex items-center justify-between mt-5 mb-2"><h2 class="text-sm font-bold uppercase tracking-wide ma-muted">${esc(text)}</h2></div>`);
    if (action) node.appendChild(action);
    return node;
}

export function spinnerBlock(label = 'Loading…') {
    return el(`<div class="flex items-center justify-center gap-3 py-10"><div class="ma-spinner"></div><span class="ma-hint">${esc(label)}</span></div>`);
}

/** Friendly message from any thrown error (API field errors first). */
export function errMsg(error) {
    if (error && typeof error.fieldMessage === 'function') {
        return error.fieldMessage() ?? error.message;
    }
    return error?.message ?? 'Something went wrong. Please try again.';
}

/** Short calendar label, e.g. "Sep 30" / "Today". */
export function dateLabel(iso) {
    if (!iso) return '';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '';
    if (date.toDateString() === new Date().toDateString()) return 'Today';
    return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

/** Plain number with two decimals, no currency symbol. */
export function num(value) {
    const number = Number(value);
    return (Number.isFinite(number) ? number : 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

/**
 * App bar matching the web header: screen title, optional badge, and a
 * notification bell whose unread badge stays live via the store.
 */
export function appBar(title, badge = null) {
    const bar = el(`
        <div class="ma-appbar">
            <div class="min-w-0 flex-1">
                <div class="ma-heading truncate text-[19px] font-medium leading-tight">${title}</div>
                ${badge ? `<span class="ma-appbar-badge mt-1 inline-block">${badge}</span>` : ''}
            </div>
            <button class="ma-bell" data-bell aria-label="Notifications">
                <i class="ri-notification-3-line"></i>
                <span class="ma-badge" data-bell-badge style="display:none">0</span>
            </button>
        </div>`);

    bar.querySelector('[data-bell]').addEventListener('click', () => {
        haptic('light');
        navigate('alerts');
    });

    updateBellBadges(bar);
    return bar;
}

/** Sync every bell badge on screen with the store's unread count. */
export function updateBellBadges(scope = document) {
    const count = state.unread ?? 0;
    scope.querySelectorAll('[data-bell-badge]').forEach((badge) => {
        badge.textContent = count > 99 ? '99+' : String(count);
        badge.style.display = count === 0 ? 'none' : 'flex';
    });
}

// Bell badges refresh whenever the unread count changes.
subscribe(() => updateBellBadges());

/**
 * Bottom-sheet PIN prompt shared by all money flows (withdrawals, Pay Later,
 * VAS). Resolves the 4-digit PIN or null when dismissed.
 */
export function askPin(title = 'Enter your transaction PIN') {
    return new Promise((resolve) => {
        const backdrop = el(`
            <div class="ma-sheet-backdrop">
                <div class="ma-sheet">
                    <div class="flex items-center justify-between">
                        <h3 class="ma-heading font-medium text-lg">${esc(title)}</h3>
                        <button type="button" data-close class="ma-icon !h-9 !w-9" aria-label="Close"><i class="ri-close-line"></i></button>
                    </div>
                    <input class="ma-input mt-4 text-center text-2xl tracking-[0.5em]" type="password" inputmode="numeric" maxlength="4" autocomplete="off" placeholder="••••">
                    <div class="ma-hint mt-2 text-center">Your PIN is checked server-side and rate-limited.</div>
                    <button type="button" data-confirm class="ma-btn ma-btn-primary mt-4" disabled>Confirm</button>
                </div>
            </div>`);

        const input = backdrop.querySelector('input');
        const confirm = backdrop.querySelector('[data-confirm]');
        let settled = false;

        const close = (value) => {
            if (settled) return;
            settled = true;
            backdrop.remove();
            resolve(value);
        };

        input.addEventListener('input', () => {
            input.value = input.value.replace(/\D/g, '').slice(0, 4);
            confirm.disabled = input.value.length !== 4;
        });

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && input.value.length === 4) close(input.value);
        });

        confirm.addEventListener('click', () => close(input.value));
        backdrop.querySelector('[data-close]').addEventListener('click', () => close(null));
        backdrop.addEventListener('click', (event) => {
            if (event.target === backdrop) close(null);
        });

        document.body.appendChild(backdrop);
        setTimeout(() => input.focus(), 80);
    });
}

