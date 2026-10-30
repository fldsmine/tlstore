/**
 * Shared session state: the authenticated user + the notifications badge.
 * Screens read from here to avoid redundant requests.
 */

import { api, cacheUser, clearSession } from './api.js';

export const state = {
    user: null,
    unread: 0,
};

const listeners = new Set();

export function subscribe(listener) {
    listeners.add(listener);
}

function emit() {
    listeners.forEach((listener) => listener(state));
}

export function setUser(user) {
    state.user = user;
    cacheUser(user);
    emit();
}

export async function refreshUnread() {
    try {
        const data = await api('/notifications/unread-count');
        state.unread = data?.unread_count ?? 0;
        emit();
    } catch {
        /* badge is best-effort */
    }
}

export function resetState() {
    state.user = null;
    state.unread = 0;
    clearSession();
    emit();
}
