// Nextel Connect service worker — offline fallback (PWA) + Web Push.
const CACHE = 'nextel-static-v1';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((c) => c.add(OFFLINE_URL)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

// Only intercept top-level navigations — serve the offline page when the network is unreachable.
// Everything else (assets, Livewire XHR, API) passes straight through, so nothing is stale-cached.
self.addEventListener('fetch', (event) => {
    if (event.request.mode === 'navigate') {
        event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE_URL)));
    }
});

// Incoming push → show a notification, UNLESS a Nextel tab is already focused (the in-app toast covers it).
self.addEventListener('push', (event) => {
    let p = {};
    try { p = event.data ? event.data.json() : {}; } catch (e) { p = { body: event.data && event.data.text() }; }
    const url = (p.data && p.data.url) || p.url || '/';
    const options = {
        body: p.body || '',
        icon: p.icon || '/favicon-192x192.png',
        badge: p.badge || '/favicon-192x192.png',
        tag: p.tag || 'nextel',
        renotify: true,
        actions: Array.isArray(p.actions) ? p.actions : [],
        data: { url },
        vibrate: [30],
    };
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            const focused = clients.some((c) => c.visibilityState === 'visible' && c.focused);
            if (focused) return;
            return self.registration.showNotification(p.title || 'Nextel Connect', options);
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || '/';
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            for (const c of clients) {
                if ('focus' in c) {
                    if ('navigate' in c) { c.navigate(url).catch(() => {}); }
                    return c.focus();
                }
            }
            return self.clients.openWindow(url);
        })
    );
});
