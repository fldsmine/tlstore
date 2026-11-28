# Flutter web build

The web bundle is hosted by Laravel from `public/web`, while Laravel serves the
Flutter entry page at `/` and its client-side routes. Build the bundle with the
web base path before copying `build/web/*` to `public/web/`. Firebase Web
values for this project are configured by default. The environment variables
below can override them when needed:

```powershell
# Optional overrides for the Firebase Web config.
# $env:FIREBASE_WEB_API_KEY = '...'
# $env:FIREBASE_WEB_APP_ID = '...'
# $env:FIREBASE_WEB_AUTH_DOMAIN = '...'
# $env:FIREBASE_WEB_VAPID_KEY = '...'
.\tool\build_web.ps1
```

Use the Web app registered under the `tl-store-application` Firebase project.
The VAPID key is the public key from Firebase Cloud Messaging's Web Push
certificates page. These values are public client configuration, not the
Firebase service-account credentials used by Laravel to send notifications.

The build script creates `build/web/firebase-messaging-sw.js`. Copy the
contents of `build/web` into Laravel's `public/web` directory. Laravel serves
the worker at `/firebase-messaging-sw.js` with root scope so it can receive
background notifications while the Flutter page is served at `/`.

The site must be hosted over HTTPS (or localhost during development) for
browser secure storage and Firebase messaging.

For deployments behind Nginx, do not cache these version-sensitive files:
`/web/index.html`, `/web/flutter_bootstrap.js`,
`/web/flutter_service_worker.js`, `/web/version.json`, and
`/web/main.dart.js`. The Laravel route already sends the app entry response
with `no-store` headers; direct static requests need the equivalent Nginx
rule. If a browser continues to show an old build after deployment, clear that
site's stored data and unregister its old service worker once, then reload.

## Administrator impersonation hand-off

Super administrators can open the web build already signed in as a user (admin console →
Users → account → *Impersonate*, or the Flutter admin screens → user → *Impersonate*). The
hand-off relies on three things that are easy to break in a deployment:

1. **The app is served at `/`.** The link is `https://<host>/?impersonation=<code>`; if a proxy
   rewrites the root to another path or strips query strings, the code never reaches the app.
2. **HTTPS (or localhost).** `flutter_secure_storage` needs a secure context on the web, so the
   redeemed session cannot be stored over plain HTTP.
3. **No aggressive caching of `/`.** The entry page already sends `no-store` via Laravel; keep
   the equivalent rule on static hosts, otherwise a cached page keeps replaying an old code
   (which is single use and fails on the second attempt).

The code is removed from the address bar as soon as it is redeemed, and the impersonated session
ends with the tab or after `ADMIN_IMPERSONATION_SESSION_MINUTES`.
