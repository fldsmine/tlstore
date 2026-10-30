/**
 * Notifications = the N-Cloud inbox (GET /notifications), with read state,
 * mark-one-read and mark-all-read. The tab badge uses /notifications/unread-count.
 */

import { api } from '../api.js';
import { el, esc, skeleton, errorState, emptyState, toast, when } from '../ui.js';
import { refreshUnread } from '../store.js';
import { enableBack } from '../router.js';
import { haptic } from '../telegram.js';

let page = 1;
let lastPage = 1;
let loading = false;

export async function renderAlerts(view) {
    enableBack('home');
    page = 1;
    await load(view);
}

async function load(view) {
    view.innerHTML = `
        <div class="flex items-center justify-between py-2">
            <h1 class="text-xl font-bold">Alerts</h1>
            <button class="ma-btn ma-btn-ghost ma-btn-sm text-xs" id="read-all"><i class="ri-check-double-line"></i> Read all</button>
        </div>
        <div id="list"></div>
        <div id="more"></div>`;

    view.querySelector('#read-all').addEventListener('click', async (event) => {
        const button = event.currentTarget;
        button.disabled = true;

        try {
            await api('/notifications/read-all', { method: 'POST' });
            haptic('success');
            await refreshUnread();
            await loadList(view.querySelector('#list'), true);
        } catch (error) {
            toast(error.message, 'error');
            button.disabled = false;
        }
    });

    await loadList(view.querySelector('#list'), true);
}

async function loadList(list, reset = false) {
    if (!list) return;

    if (reset) {
        page = 1;
        list.innerHTML = '';
        list.appendChild(skeleton(4));
    }

    let data;

    try {
        data = await api(`/notifications?page=${page}&per_page=20`);
    } catch (error) {
        if (reset) {
            list.innerHTML = '';
            list.appendChild(errorState(error.message, () => loadList(list, true)));
        }
        return;
    }

    if (reset) list.innerHTML = '';

    const items = data.items ?? [];
    lastPage = data.pagination?.last_page ?? 1;

    if (reset && !items.length) {
        list.appendChild(emptyState('ri-notification-3-line', 'All caught up', 'Notifications from calls, payments and the team land here.'));
        return;
    }

    items.forEach((notice) => list.appendChild(buildRow(notice, list)));

    const moreSlot = list.parentElement.querySelector('#more');
    moreSlot.innerHTML = '';

    if (page < lastPage) {
        const button = el('<button class="ma-btn mt-3">Load more</button>');
        button.addEventListener('click', async () => {
            if (loading) return;
            loading = true;
            button.disabled = true;
            button.textContent = 'Loading…';
            page += 1;
            await loadList(list);
            loading = false;
        });
        moreSlot.appendChild(button);
    }
}

function buildRow(notice, list) {
    const unread = !notice.read_at;

    const row = el(`
        <div class="ma-row ${unread ? '' : 'opacity-60'}" style="cursor:pointer">
            <div class="ma-icon" style="${unread ? '' : 'background:color-mix(in srgb, var(--ma-text) 8%, transparent);color:var(--ma-hint)'}">
                <i class="${notice.is_sponsored ? 'ri-megaphone-line' : 'ri-notification-2-line'}"></i>
            </div>
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm ${unread ? 'font-bold' : 'font-semibold'}">${esc(notice.subject ?? notice.sender_name ?? 'Notification')}</div>
                <div class="ma-hint mt-0.5 line-clamp-2 text-xs">${esc(notice.body ?? '')}</div>
                <div class="ma-hint mt-0.5 text-[0.65rem]">${when(notice.created_at)}</div>
            </div>
            ${unread ? '<span class="h-2.5 w-2.5 flex-none rounded-full" style="background:var(--ma-accent)"></span>' : ''}
        </div>`);

    row.addEventListener('click', async () => {
        if (!unread) return;

        try {
            await api(`/notifications/${notice.id}/read`, { method: 'POST' });
            row.classList.remove('font-bold');
            row.classList.add('opacity-60');
            row.querySelector('.rounded-full')?.remove();
            await refreshUnread();
        } catch {
            /* non-fatal */
        }
    });

    return row;
}
