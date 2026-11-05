<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Continue with Telegram · TrustLens</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #f4f7f4; color: #24332b; font: 16px system-ui, sans-serif; }
        main { width: min(100%, 390px); padding: 32px 24px; text-align: center; background: #fff; border-radius: 22px; box-shadow: 0 12px 42px #17241c18; }
        h1 { margin: 0 0 9px; font-size: 23px; }
        p { margin: 0 0 25px; color: #6c7870; line-height: 1.5; }
        #login { display: flex; justify-content: center; }
        .error { display: none; margin-top: 18px; color: #a34338; }
        #return-link { display: none; margin-top: 12px; padding: 12px 18px; border-radius: 12px; background: #145A3D; color: #fff; text-decoration: none; font-weight: 700; }
    </style>
</head>
<body>
<main>
    <h1>Sign in to TrustLens</h1>
    <p>Continue securely with your Telegram account.</p>
    <p id="returning" hidden>Telegram confirmed your account. Opening TL Store…</p>
    <p id="error" class="error">If TL Store did not open, use the button below.</p>
    <a id="return-link" href="#">Open TL Store</a>
    <div id="login">
        <script>
            const expectedState = @json($state);
            const isWebCallback = @json($isWebCallback);
            const webScreen = @json($webScreen);
            const webCallbackUrl = @json($webCallbackUrl);
            window.onTelegramAuth = function (user) {
                const query = new URLSearchParams({state: expectedState, payload: JSON.stringify(user)});
                const callback = isWebCallback
                    ? webCallbackUrl + '?' + new URLSearchParams({
                        telegram_callback: '1',
                        screen: webScreen,
                        state: expectedState,
                        payload: JSON.stringify(user)
                    }).toString()
                    : 'trustlens://telegram-login?' + query.toString();
                const androidIntent = 'intent://telegram-login?' + query.toString()
                    + '#Intent;scheme=trustlens;package=pynith.tlstore.review;end';
                document.getElementById('returning').hidden = false;
                const returnLink = document.getElementById('return-link');
                returnLink.href = callback;
                window.location.href = /Android/i.test(navigator.userAgent) ? androidIntent : callback;
                window.setTimeout(() => {
                    if (!document.hidden) {
                        document.getElementById('error').style.display = 'block';
                        returnLink.style.display = 'inline-block';
                    }
                }, 2500);
            };
        </script>
        <script async src="https://telegram.org/js/telegram-widget.js?22"
                data-telegram-login="{{ $botUsername }}"
                data-size="large"
                data-userpic="false"
                data-request-access="write"
                data-onauth="onTelegramAuth(user)"></script>
    </div>
</main>
</body>
</html>
