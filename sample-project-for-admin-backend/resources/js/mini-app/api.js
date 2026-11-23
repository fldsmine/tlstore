/**
 * Minimal API client for /api/v1. Talks the app's standard envelope:
 *   { success, message, data }            on success
 *   { success: false, message, errors? }  on failure
 * The bearer token is kept in sessionStorage only — it survives navigation
 * inside the WebView session but never leaks to other apps or tabs.
 */

const TOKEN_KEY = 'nextel.miniapp.token';
const USER_KEY = 'nextel.miniapp.user';

export class ApiError extends Error {
    constructor(status, message, errors = null) {
        super(message);
        this.status = status;
        this.errors = errors;
    }

    /** First field error message, if the server sent any. */
    fieldMessage() {
        if (!this.errors) return null;

        for (const messages of Object.values(this.errors)) {
            if (Array.isArray(messages) && messages.length) return messages[0];
        }

        return null;
    }
}

export function getToken() {
    try {
        return sessionStorage.getItem(TOKEN_KEY) ?? localStorage.getItem(TOKEN_KEY);
    } catch {
        return null;
    }
}

/**
 * Store the bearer token. `persist` (Remember Me) mirrors it into
 * localStorage so the WebView session survives app restarts; otherwise the
 * token lives only for this WebView session.
 */
export function setToken(token, persist = false) {
    try {
        sessionStorage.setItem(TOKEN_KEY, token);
        persist ? localStorage.setItem(TOKEN_KEY, token) : localStorage.removeItem(TOKEN_KEY);
    } catch {
        /* private mode — ignore */
    }
}

export function getCachedUser() {
    try {
        const raw = sessionStorage.getItem(USER_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

export function cacheUser(user) {
    try {
        sessionStorage.setItem(USER_KEY, JSON.stringify(user));
    } catch {
        /* private mode — ignore */
    }
}

export function clearSession() {
    try {
        sessionStorage.removeItem(TOKEN_KEY);
        sessionStorage.removeItem(USER_KEY);
        localStorage.removeItem(TOKEN_KEY);
    } catch {
        /* private mode — ignore */
    }
}

const logoutListeners = new Set();

export function onUnauthorized(listener) {
    logoutListeners.add(listener);
}

/**
 * Perform an API request and return `data` from the envelope.
 * Throws ApiError with a human-friendly message on any failure.
 */
export async function api(path, { method = 'GET', body, auth = true } = {}) {
    const headers = { Accept: 'application/json' };
    const token = getToken();

    if (body !== undefined) headers['Content-Type'] = 'application/json';
    if (auth && token) headers.Authorization = `Bearer ${token}`;

    let response;

    try {
        response = await fetch(`/api/v1${path}`, {
            method,
            headers,
            body: body !== undefined ? JSON.stringify(body) : undefined,
        });
    } catch {
        throw new ApiError(0, 'Network error — check your connection and try again.');
    }

    let payload = null;

    try {
        payload = await response.json();
    } catch {
        payload = null;
    }

    if (response.ok && payload?.success) {
        return payload.data ?? null;
    }

    if (response.status === 401 && auth) {
        clearSession();
        logoutListeners.forEach((listener) => listener());
    }

    const message =
        payload?.message ??
        (response.status === 429 ? 'Too many attempts — slow down a little.' : 'Something went wrong. Please try again.');

    throw new ApiError(response.status, message, payload?.errors ?? null);
}
