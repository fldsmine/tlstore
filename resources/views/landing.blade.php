<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0c3528">
    <title>{{ $appName }} — Review. Share. Earn.</title>
    <meta name="description" content="Discover apps, complete genuine review tasks, share sponsored campaigns and earn rewards with TLStore.">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $appName }} — Review. Share. Earn.">
    <meta property="og:description" content="A simple way to discover apps, complete tasks and earn rewards.">
    <meta property="og:url" content="https://tlstore.org">
    <meta property="og:image" content="{{ asset('images/logo-long.png') }}">
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--green:#0c3528;--green-2:#126b4f;--mint:#eaf5ef;--lime:#c5ec77;--ink:#17251f;--muted:#63716a;--line:#e5ece7;--paper:#fff;--wash:#f6faf7;--shadow:0 24px 70px rgba(9,48,34,.13)}
        *{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:var(--paper);color:var(--ink);font-family:"DM Sans",sans-serif;line-height:1.55}a{color:inherit;text-decoration:none}.wrap{width:min(1160px,calc(100% - 44px));margin:auto}
        .topbar{height:82px;display:flex;align-items:center;justify-content:space-between;gap:24px}.brand img{width:170px;height:auto;display:block}.nav{display:flex;gap:32px;align-items:center;color:#53635b;font-size:14px;font-weight:600}.nav a:hover{color:var(--green-2)}.nav-actions{display:flex;align-items:center;gap:12px}.text-link{font-weight:700;color:var(--green)}.button{display:inline-flex;align-items:center;justify-content:center;gap:9px;padding:13px 20px;border-radius:14px;border:1px solid transparent;font-weight:700;font-size:14px;transition:transform .18s,box-shadow .18s,background .18s}.button:hover{transform:translateY(-2px)}.button-primary{background:var(--green);color:#fff;box-shadow:0 10px 24px rgba(12,53,40,.18)}.button-primary:hover{background:#14543f}.button-soft{background:#eff7f1;color:var(--green)}.button-outline{border-color:#cbdad0;color:var(--green);background:#fff}.menu-toggle{display:none;background:none;border:0;color:var(--green);font-size:26px}
        .hero{padding:62px 0 78px;background:radial-gradient(ellipse at 93% 25%,rgba(197,236,119,.19),transparent 32%),linear-gradient(180deg,#f7fbf7 0%,#fff 100%);overflow:hidden}.hero-grid{display:grid;grid-template-columns:1.05fr .95fr;gap:44px;align-items:center}.eyebrow{display:inline-flex;align-items:center;gap:8px;border-radius:30px;background:#e8f4eb;color:var(--green-2);padding:8px 13px;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase}.eyebrow i{width:7px;height:7px;background:#70ae4e;border-radius:50%}h1,h2,h3{font-family:Manrope,sans-serif;line-height:1.12;letter-spacing:-.035em}h1{font-size:clamp(42px,5.5vw,68px);margin:22px 0 18px;color:var(--green);max-width:680px}h1 span{color:#27805e}.hero-copy{max-width:545px;color:#5f7067;font-size:18px}.hero-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}.store-links{display:flex;align-items:center;flex-wrap:wrap;gap:12px;margin-top:24px}.store-badge{display:inline-flex;align-items:center;justify-content:center;height:48px;min-width:148px;padding:5px 8px;border:1px solid var(--line);border-radius:10px;background:#fff}.store-badge img{max-height:36px;max-width:136px}.store-badge.disabled{opacity:.54;cursor:not-allowed}.store-note{width:100%;font-size:12px;color:#839088}.hero-visual{position:relative;min-height:470px;display:grid;place-items:center}.glow{position:absolute;width:420px;height:420px;border-radius:50%;background:#e4f2e6;filter:blur(2px)}.phone{position:relative;width:275px;min-height:430px;padding:14px;border:8px solid #132c22;border-radius:38px;background:#f9fcf9;box-shadow:var(--shadow);transform:rotate(5deg)}.phone-notch{width:92px;height:17px;border-radius:0 0 14px 14px;background:#132c22;margin:-14px auto 17px}.phone-head{display:flex;align-items:center;justify-content:space-between;padding:4px 2px 14px}.phone-head img{width:105px}.avatar{width:32px;height:32px;border-radius:50%;display:grid;place-items:center;background:var(--lime);color:var(--green);font-weight:800;font-size:11px}.balance{padding:17px;border-radius:18px;background:var(--green);color:#fff}.balance small{opacity:.74}.balance strong{display:block;font:800 25px Manrope;margin:7px 0}.balance span{font-size:11px;color:#d4ebda}.phone-section{font:700 14px Manrope;margin:17px 2px 10px}.task-card{display:flex;gap:10px;align-items:center;padding:11px;border:1px solid var(--line);border-radius:15px;background:#fff;margin:8px 0}.task-icon{width:38px;height:38px;border-radius:12px;background:#e9f4ed;display:grid;place-items:center;color:var(--green-2);font-size:17px}.task-text{flex:1}.task-text b{font-size:11px;display:block}.task-text small{font-size:9px;color:#809087}.reward{font-size:10px;color:#19805d;font-weight:800}.float-card{position:absolute;display:flex;align-items:center;gap:11px;padding:13px 16px;background:#fff;border:1px solid #e6eee8;border-radius:16px;box-shadow:0 12px 30px rgba(9,48,34,.11);font-size:12px;font-weight:700}.float-card span{font-size:19px}.float-a{top:58px;left:0}.float-b{right:-5px;bottom:64px}
        .trust-row{border-top:1px solid var(--line);border-bottom:1px solid var(--line);padding:19px 0;background:#fff}.trust-inner{display:flex;justify-content:center;flex-wrap:wrap;gap:38px;color:#607168;font-size:13px;font-weight:600}.trust-inner span{display:flex;align-items:center;gap:8px}.trust-inner b{color:var(--green-2);font-size:17px}
        section{padding:88px 0}.section-head{text-align:center;max-width:670px;margin:0 auto 42px}.section-head h2{font-size:clamp(30px,4vw,44px);color:var(--green);margin:15px 0}.section-head p{color:var(--muted);font-size:16px;margin:0}.feature-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}.feature{padding:27px;border:1px solid var(--line);border-radius:22px;background:#fff;box-shadow:0 8px 28px rgba(9,48,34,.035)}.feature-icon{width:48px;height:48px;display:grid;place-items:center;background:var(--mint);border-radius:15px;color:var(--green-2);font-size:22px}.feature h3{font-size:19px;margin:19px 0 8px}.feature p{color:var(--muted);font-size:14px;margin:0}.how{background:var(--wash)}.steps{display:grid;grid-template-columns:repeat(3,1fr);gap:24px}.step{position:relative;padding:27px;background:#fff;border:1px solid var(--line);border-radius:20px}.step-number{display:grid;place-items:center;width:40px;height:40px;background:var(--lime);color:var(--green);border-radius:13px;font-weight:800}.step h3{font-size:18px;margin:17px 0 8px}.step p{font-size:14px;color:var(--muted);margin:0}.earn{background:var(--green);color:white;overflow:hidden;position:relative}.earn:after{content:"";position:absolute;right:-110px;top:-190px;width:460px;height:460px;border-radius:50%;border:1px solid rgba(255,255,255,.16);box-shadow:0 0 0 48px rgba(255,255,255,.035),0 0 0 96px rgba(255,255,255,.025)}.earn-grid{display:grid;grid-template-columns:1fr auto;align-items:center;gap:45px;position:relative;z-index:1}.earn h2{font-size:clamp(30px,4vw,46px);max-width:650px;margin:0 0 14px}.earn p{max-width:630px;color:#c7dbd0;margin:0}.earn .button{background:var(--lime);color:var(--green);white-space:nowrap}.faq-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.faq{border:1px solid var(--line);border-radius:17px;padding:20px}.faq h3{font-size:16px;margin:0 0 8px;letter-spacing:-.01em}.faq p{color:var(--muted);font-size:14px;margin:0}.final-cta{padding:58px 30px;border-radius:28px;text-align:center;background:linear-gradient(135deg,#eaf5ed,#f6fae9)}.final-cta h2{font-size:clamp(28px,4vw,42px);color:var(--green);margin:0 0 12px}.final-cta p{color:var(--muted);margin:0 auto 22px;max-width:570px}.footer{padding:34px 0;background:#09271d;color:#d4e5db}.footer-top{display:flex;justify-content:space-between;align-items:center;gap:24px;padding-bottom:23px;border-bottom:1px solid rgba(255,255,255,.14)}.footer img{width:150px;filter:brightness(0) invert(1)}.footer-links{display:flex;gap:20px;flex-wrap:wrap;font-size:13px}.footer-bottom{padding-top:18px;display:flex;justify-content:space-between;gap:18px;color:#9bb4a6;font-size:12px}.mobile-nav{display:none}
        @media(max-width:850px){.nav{display:none}.menu-toggle{display:block}.hero{padding:38px 0 52px}.hero-grid{grid-template-columns:1fr;gap:12px}.hero-copy{font-size:16px}.hero-visual{min-height:460px;max-width:520px;width:100%;margin:auto}.phone{transform:rotate(2deg)}.float-a{left:5px}.float-b{right:0}.feature-grid,.steps{grid-template-columns:1fr 1fr}.earn-grid{grid-template-columns:1fr}.earn .button{justify-self:start}.topbar{height:70px}.mobile-nav.open{display:flex;position:absolute;z-index:5;left:0;right:0;top:70px;background:#fff;padding:18px 24px 22px;box-shadow:0 14px 24px rgba(9,48,34,.12);flex-direction:column;align-items:stretch;gap:14px}.mobile-nav a{padding:6px 0;font-weight:700;color:var(--green)}}
        @media(max-width:560px){.wrap{width:min(100% - 32px,1160px)}.topbar .nav-actions .text-link{display:none}.brand img{width:142px}.hero-actions .button{flex:1}.store-links{gap:8px}.store-badge{min-width:calc(50% - 4px)}.hero-visual{min-height:400px;transform:scale(.93);transform-origin:top center;margin-bottom:-25px}.phone{width:255px;min-height:405px}.float-a{top:44px}.float-b{bottom:30px}.trust-inner{gap:14px 22px;justify-content:flex-start}.trust-inner span{font-size:11px}section{padding:64px 0}.feature-grid,.steps,.faq-grid{grid-template-columns:1fr}.feature{padding:23px}.earn{padding:65px 0}.footer-top,.footer-bottom{align-items:flex-start;flex-direction:column}.final-cta{padding:38px 20px}}
    </style>
</head>
<body>
<header class="wrap topbar">
    <a class="brand" href="/" aria-label="TLStore home"><img src="{{ asset('images/logo-long.png') }}" alt="TLStore"></a>
    <nav class="nav" aria-label="Main navigation"><a href="#how-it-works">How it works</a><a href="#features">Explore</a><a href="#faq">FAQs</a></nav>
    <div class="nav-actions"><a class="text-link" href="{{ $webAppUrl }}/login">Log in</a><a class="button button-primary" href="{{ $webAppUrl }}/register">Get started <span aria-hidden="true">→</span></a></div>
    <button class="menu-toggle" type="button" aria-label="Open menu" onclick="document.querySelector('.mobile-nav').classList.toggle('open')">☰</button>
    <nav class="mobile-nav" aria-label="Mobile navigation"><a href="#how-it-works">How it works</a><a href="#features">Explore</a><a href="#faq">FAQs</a><a href="{{ $webAppUrl }}/login">Log in</a><a href="{{ $webAppUrl }}/register">Create account</a></nav>
</header>
<main>
    <section class="hero">
        <div class="wrap hero-grid">
            <div>
                <span class="eyebrow"><i></i> Your next task can pay</span>
                <h1>Review. Share.<br><span>Earn with TLStore.</span></h1>
                <p class="hero-copy">Discover apps and sponsored campaigns, complete simple tasks, and track your rewards in one place. Join a community where your time and feedback matter.</p>
                <div class="hero-actions"><a class="button button-primary" href="{{ $webAppUrl }}/register">Create your free account <span aria-hidden="true">→</span></a><a class="button button-outline" href="#how-it-works">See how it works</a></div>
                <div class="store-links" aria-label="Download TLStore">
                    @if($androidUrl)
                        <a class="store-badge" href="{{ $androidUrl }}" target="_blank" rel="noopener"><img src="{{ asset('images/pdf/google-play-badge.png') }}" alt="Get it on Google Play"></a>
                    @else
                        <span class="store-badge disabled" aria-label="Android app coming soon"><img src="{{ asset('images/pdf/google-play-badge.png') }}" alt="Google Play — coming soon"></span>
                    @endif
                    @if($iosUrl)
                        <a class="store-badge" href="{{ $iosUrl }}" target="_blank" rel="noopener"><img src="{{ asset('images/pdf/app-store-badge.png') }}" alt="Download on the App Store"></a>
                    @else
                        <span class="store-badge disabled" aria-label="iOS app coming soon"><img src="{{ asset('images/pdf/app-store-badge.png') }}" alt="App Store — coming soon"></span>
                    @endif
                    @if(!$androidUrl || !$iosUrl)<span class="store-note">App store links will activate when published. You can start using TLStore on the web now.</span>@endif
                </div>
            </div>
            <div class="hero-visual" aria-label="TLStore app preview">
                <div class="glow"></div>
                <div class="float-card float-a"><span>✨</span><div>Tasks that fit your day<br><small style="font-weight:500;color:#718179">Choose what works for you</small></div></div>
                <div class="phone">
                    <div class="phone-notch"></div>
                    <div class="phone-head"><img src="{{ asset('images/logo-long.png') }}" alt="TLStore"><span class="avatar">TL</span></div>
                    <div class="balance"><small>Total earnings</small><strong>₦ 24,850</strong><span>↑ Keep completing tasks</span></div>
                    <div class="phone-section">Available for you</div>
                    <div class="task-card"><span class="task-icon">★</span><span class="task-text"><b>Review an app</b><small>Google Play · 4 min</small></span><span class="reward">+₦210</span></div>
                    <div class="task-card"><span class="task-icon">↗</span><span class="task-text"><b>Share a campaign</b><small>Telegram · 2 min</small></span><span class="reward">+₦120</span></div>
                    <div class="task-card"><span class="task-icon">✓</span><span class="task-text"><b>Task submitted</b><small>Pending review</small></span><span class="reward">View</span></div>
                    <div class="phone-section">Your progress <span style="float:right;color:#27805e">See all →</span></div>
                </div>
                <div class="float-card float-b"><span>💸</span><div>Rewards tracked clearly<br><small style="font-weight:500;color:#718179">Wallet, status and history</small></div></div>
            </div>
        </div>
    </section>
    <div class="trust-row"><div class="wrap trust-inner"><span><b>✓</b> Clear task instructions</span><span><b>◎</b> Human-reviewed submissions</span><span><b>↗</b> Progress and earnings in one place</span></div></div>
    <section id="features"><div class="wrap"><div class="section-head"><span class="eyebrow"><i></i> A platform built around action</span><h2>Make your everyday clicks count</h2><p>TLStore brings app discovery, social campaigns and earning tools together in a straightforward experience.</p></div><div class="feature-grid">
        <article class="feature"><div class="feature-icon">▣</div><h3>Discover apps</h3><p>Explore listings from popular app stores and share useful feedback by completing review tasks.</p></article>
        <article class="feature"><div class="feature-icon">↗</div><h3>Share campaigns</h3><p>Find sponsored social tasks, follow their steps and submit proof for review.</p></article>
        <article class="feature"><div class="feature-icon">◉</div><h3>See your rewards</h3><p>Track task progress, wallet balances and transaction updates from your account.</p></article>
        <article class="feature"><div class="feature-icon">♧</div><h3>Invite your community</h3><p>Share your referral link and follow your invitations and eligible referral rewards.</p></article>
        <article class="feature"><div class="feature-icon">⌖</div><h3>Choose your plan</h3><p>Subscription packages explain the available task access and limits before you join.</p></article>
        <article class="feature"><div class="feature-icon">▤</div><h3>More useful services</h3><p>Access available account services, support information and platform updates in one app.</p></article>
    </div></div></section>
    <section class="how" id="how-it-works"><div class="wrap"><div class="section-head"><span class="eyebrow"><i></i> Get started in a few steps</span><h2>From sign-up to submitted task</h2><p>Set up your account once, then pick from tasks available to your package.</p></div><div class="steps">
        <article class="step"><span class="step-number">01</span><h3>Create your account</h3><p>Register, choose your country and set up the account details needed for your tasks.</p></article>
        <article class="step"><span class="step-number">02</span><h3>Choose a package</h3><p>Review the package access, price and task rules, then select the option that suits you.</p></article>
        <article class="step"><span class="step-number">03</span><h3>Complete and track</h3><p>Follow task instructions, submit your proof when required and check your status in the app.</p></article>
    </div></div></section>
    <section class="earn"><div class="wrap earn-grid"><div><h2>Start with a clear view of every task.</h2><p>TLStore shows the available task, reward information and submission status so you can decide what to do and follow it through.</p></div><a class="button" href="{{ $webAppUrl }}/register">Join TLStore <span aria-hidden="true">→</span></a></div></section>
    <section id="faq"><div class="wrap"><div class="section-head"><span class="eyebrow"><i></i> Quick answers</span><h2>Frequently asked questions</h2><p>Get a little more familiar with how TLStore works.</p></div><div class="faq-grid">
        <article class="faq"><h3>Is TLStore free to join?</h3><p>You can create an account and browse public listings. Some task actions require an active subscription package.</p></article>
        <article class="faq"><h3>How do I earn rewards?</h3><p>Complete eligible app review or sponsored sharing tasks and submit any required proof. Approved tasks are rewarded according to the applicable package and task rules.</p></article>
        <article class="faq"><h3>When will my task be approved?</h3><p>Task submissions can be reviewed before rewards are credited. You can check the status from your task and transaction history.</p></article>
        <article class="faq"><h3>Where can I use TLStore?</h3><p>Use the web app or download the Android and iOS apps when they are available in their stores.</p></article>
    </div></div></section>
    <section style="padding-top:0"><div class="wrap"><div class="final-cta"><h2>Ready to explore TLStore?</h2><p>Create your account, find tasks that fit your package and keep your progress in one place.</p><a class="button button-primary" href="{{ $webAppUrl }}/register">Get started now <span aria-hidden="true">→</span></a></div></div></section>
</main>
<footer class="footer"><div class="wrap"><div class="footer-top"><a href="/" aria-label="TLStore"><img src="{{ asset('images/logo-long.png') }}" alt="TLStore"></a><nav class="footer-links"><a href="{{ $webAppUrl }}/privacy">Privacy</a><a href="{{ $webAppUrl }}/terms">Terms</a><a href="{{ $webAppUrl }}/help">Help center</a><a href="/contact">Contact support</a></nav></div><div class="footer-bottom"><span>© {{ date('Y') }} {{ $appName }}. All rights reserved.</span><a href="{{ $webAppUrl }}">Open web app →</a></div></div></footer>
</body>
</html>
