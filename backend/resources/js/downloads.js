/**
 * One download path for every surface the app is embedded in.
 *
 * The dashboard renders file downloads (PDF receipts, CSV/TXT token exports) as ordinary
 * `<a href>` links. In a normal browser that is all that is needed. Inside the Telegram Mini
 * App and the Android WebView it is not:
 *
 *   - Telegram Mini App: a WebView has no download manager, so a link that returns
 *     `Content-Disposition: attachment` goes nowhere. Telegram exposes `WebApp.downloadFile()`
 *     (Bot API 8.0+) for exactly this, which saves the file through the Telegram client.
 *   - Android app: the app registers a `window.Android` bridge. Its WebView cannot download
 *     either, so the URL is handed to the app, which downloads it natively (with the WebView's
 *     session cookies) and saves it to the user's Downloads.
 *
 * Detection is by feature, not by user agent: the Telegram WebApp object only exists inside
 * Telegram, and `Android.downloadFile` only exists in builds of the app that support it — so an
 * older installed app simply falls through to the browser behaviour instead of breaking.
 *
 * A link opts in with `data-nextel-download` (optional filename) and `data-nextel-mime`
 * (optional MIME type). Without JavaScript — or in a browser — the plain href still works.
 */

const SELECTOR = '[data-nextel-download]';

/** Telegram injects this object into the page only when it is running as a Mini App. */
function telegramWebApp() {
    const app = window.Telegram && window.Telegram.WebApp;

    if (! app) return null;

    // `initData`/`platform` are always present on the real object; a stray global that merely
    // namedrops Telegram must not be treated as the Mini App.
    return 'initData' in app || 'platform' in app ? app : null;
}

/** The Android app's JS bridge, but only when this build can actually download. */
function androidBridge() {
    const bridge = window.Android;

    return bridge && typeof bridge.downloadFile === 'function' ? bridge : null;
}

export function environment() {
    if (telegramWebApp()) return 'telegram';
    if (androidBridge()) return 'android';

    return 'browser';
}

function absolute(url) {
    try {
        return new URL(url, window.location.href).href;
    } catch {
        return url;
    }
}

/** A filename the OS will accept, with a sane fallback derived from the URL. */
export function fileName(url, explicit) {
    const given = (explicit || '').trim();

    if (given !== '') return given.replace(/[\\/:*?"<>|]+/g, '-');

    try {
        const path = new URL(url, window.location.href).pathname;
        const base = decodeURIComponent(path.split('/').filter(Boolean).pop() || '');

        return base.replace(/[\\/:*?"<>|]+/g, '-') || 'nextel-download';
    } catch {
        return 'nextel-download';
    }
}

/**
 * Save a file, routing through whichever surface the page is running in.
 *
 * @returns {'telegram'|'telegram-link'|'android'|'browser'} which route was taken.
 */
export function save(url, { filename = '', mime = '' } = {}) {
    if (! url) return 'browser';

    const target = absolute(url);
    const name = fileName(target, filename);

    const telegram = telegramWebApp();

    if (telegram) {
        if (typeof telegram.downloadFile === 'function') {
            telegram.downloadFile({ url: target, file_name: name });

            return 'telegram';
        }

        // Older Telegram clients have no downloadFile. The link is signed (and the session
        // cookie may travel too), so handing it to the system browser still lands the file.
        if (typeof telegram.openLink === 'function') {
            telegram.openLink(target);

            return 'telegram-link';
        }
    }

    const bridge = androidBridge();

    if (bridge) {
        // The app downloads with the WebView's cookies and reports back by toast — never a
        // `location.assign()`, which would navigate the WebView to the raw file.
        bridge.downloadFile(target, name, mime || '');

        return 'android';
    }

    // Browser: a real anchor click keeps the browser's own download manager in charge
    // (also correct for the desktop backoffice).
    const anchor = document.createElement('a');

    anchor.href = target;
    anchor.download = name;
    anchor.rel = 'noopener';
    anchor.style.display = 'none';

    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();

    return 'browser';
}

if (typeof window !== 'undefined') {
    window.nextelDownload = save;
    window.nextelDownloadEnvironment = environment;

    // Delegated (and capturing) so it also survives Livewire navigation, where the anchors are
    // replaced underneath us. Anything marked as a download is handled here and never navigates.
    document.addEventListener('click', (event) => {
        const trigger = event.target && event.target.closest ? event.target.closest(SELECTOR) : null;

        if (! trigger) return;

        const url = trigger.getAttribute('href') || trigger.dataset.nextelUrl;

        if (! url || url === '#') return;

        event.preventDefault();
        event.stopPropagation();

        save(url, { filename: trigger.dataset.nextelDownload, mime: trigger.dataset.nextelMime });
    }, true);
}

export default { save, environment, fileName };
