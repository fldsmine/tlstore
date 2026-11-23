/**
 * Thin wrapper around the Telegram WebApp runtime.
 * Every helper degrades gracefully outside Telegram (plain browser preview)
 * so the Mini App remains testable on a normal HTTPS host.
 */

export const tg = window.Telegram?.WebApp ?? null;

export function initTelegram() {
    if (!tg) return;

    tg.ready();
    tg.expand();

    try {
        // Fixed brand colors (same as the web dashboard) regardless of the
        // user's Telegram theme.
        tg.setHeaderColor('#F4F1EA');
        tg.setBackgroundColor('#F4F1EA');
        tg.disableVerticalSwipes?.();
    } catch {
        // Older clients — safe to ignore.
    }
}

/** Raw initData string for server-side validation (empty outside Telegram). */
export function initData() {
    return tg?.initData ?? '';
}

/** The Telegram profile of the person holding the app (for prefill). */
export function telegramUser() {
    try {
        return tg?.initDataUnsafe?.user ?? null;
    } catch {
        return null;
    }
}

/** Deep-link parameter (e.g. menu button URL suffix) if any. */
export function startParam() {
    try {
        return tg?.initDataUnsafe?.start_param ?? null;
    } catch {
        return null;
    }
}

/**
 * Haptic feedback. type: 'success' | 'error' | 'light'
 */
export function haptic(type = 'light') {
    try {
        const fb = tg?.HapticFeedback;
        if (!fb) return;

        if (type === 'success' || type === 'error') {
            fb.notificationOccurred(type);
        } else {
            fb.impactOccurred('light');
        }
    } catch {
        /* unsupported client */
    }
}

/** Show/hide the native MainButton with a tap callback (one at a time). */
let mainButtonHandler = null;

export function setMainButton({ text, visible = true, enabled = true, onClick } = {}) {
    const button = tg?.MainButton;
    if (!button) return;

    if (mainButtonHandler) {
        button.offClick(mainButtonHandler);
        mainButtonHandler = null;
    }

    if (!visible) {
        button.hide();
        return;
    }

    button.setText(text ?? 'CONTINUE');
    button[enabled ? 'enable' : 'disable']();

    if (onClick) {
        mainButtonHandler = onClick;
        button.onClick(mainButtonHandler);
    }

    button.show();
}

export function hideMainButton() {
    try {
        tg?.MainButton?.hide();
    } catch {
        /* noop */
    }
}

export function setMainButtonProgress(on) {
    try {
        on ? tg?.MainButton?.showProgress() : tg?.MainButton?.hideProgress();
    } catch {
        /* noop */
    }
}

/** Show/hide the native BackButton. */
let backButtonHandler = null;

export function setBackButton(onClick) {
    const button = tg?.BackButton;
    if (!button) return;

    if (backButtonHandler) {
        button.offClick(backButtonHandler);
        backButtonHandler = null;
    }

    if (!onClick) {
        button.hide();
        return;
    }

    backButtonHandler = onClick;
    button.onClick(backButtonHandler);
    button.show();
}

export function openLink(url) {
    if (tg?.openLink) {
        try {
            tg.openLink(url);
            return;
        } catch {
            /* fall through */
        }
    }
    window.open(url, '_blank', 'noopener');
}

/** Native Telegram share sheet. */
export function shareUrl(url, text = '') {
    const share = tg?.switchInlineQuery ?? null;

    if (tg?.openTelegramLink) {
        const params = new URLSearchParams({ url });
        if (text) params.set('text', text);
        tg.openTelegramLink(`https://t.me/share/url?${params.toString()}`);
        return;
    }

    if (navigator.share) {
        navigator.share({ url, text }).catch(() => {});
    } else {
        openLink(url);
    }
    void share;
}

export function closeApp() {
    try {
        tg?.close();
    } catch {
        /* noop */
    }
}
