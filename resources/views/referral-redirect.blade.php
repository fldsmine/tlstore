<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#145A3D">
    <title>Join TLStore</title>
    <style>
        :root { color-scheme: light; font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; display: grid; place-items: center; padding: 24px; background: #f2f6f3; color: #123b2c; }
        main { width: min(100%, 440px); padding: 32px; border: 1px solid #dce8df; border-radius: 28px; background: #fff; box-shadow: 0 20px 60px #123b2c12; text-align: center; }
        .mark { width: 64px; height: 64px; margin: 0 auto 18px; display: grid; place-items: center; border-radius: 20px; background: #e7f2eb; color: #145a3d; font-size: 30px; font-weight: 800; }
        h1 { margin: 0; font-size: 28px; letter-spacing: -.04em; }
        p { margin: 12px 0 24px; color: #60756a; line-height: 1.55; }
        .code { display: inline-block; margin-top: 8px; padding: 8px 14px; border-radius: 999px; background: #eef5f0; color: #145a3d; font-weight: 800; letter-spacing: .08em; }
        .actions { display: grid; gap: 10px; margin-top: 24px; }
        a { min-height: 50px; display: grid; place-items: center; padding: 12px 18px; border: 1px solid #145a3d; border-radius: 15px; color: #145a3d; font-weight: 700; text-decoration: none; }
        a.primary { background: #145a3d; color: #fff; }
        .hint { min-height: 1.4em; margin: 18px 0 0; font-size: 13px; }
    </style>
</head>
<body>
    <main>
        <div class="mark" aria-hidden="true">TL</div>
        <h1>You’re invited to TLStore</h1>
        <p>Continue in the TLStore app if it is installed, or create your account in the web app. Your referral code is ready to use.</p>
        <span class="code">{{ $referralCode }}</span>
        <div class="actions">
            <a class="primary" href="{{ $appUrl }}">Open TLStore app</a>
            <a href="{{ $webUrl }}">Continue on the web</a>
        </div>
        <p class="hint" id="redirect-hint" aria-live="polite">Opening TLStore…</p>
    </main>
    <script>
        (() => {
            const appUrl = @json($appUrl);
            const webUrl = @json($webUrl);
            const mobile = /android|iphone|ipad|ipod/i.test(navigator.userAgent);
            const hint = document.getElementById('redirect-hint');

            if (!mobile) {
                window.location.replace(webUrl);
                return;
            }

            let appWasOpened = false;
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) appWasOpened = true;
            }, { once: true });
            window.location.href = appUrl;
            window.setTimeout(() => {
                if (!appWasOpened && !document.hidden) {
                    hint.textContent = 'The app is not opening? Continue in the web app below.';
                    window.location.replace(webUrl);
                }
            }, 1400);
        })();
    </script>
</body>
</html>
