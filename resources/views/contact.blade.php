<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0c3528">
    <meta name="robots" content="index,follow">
    <title>Contact support — {{ $appName }}</title>
    <meta name="description" content="Ask the {{ $appName }} support team a question. No account needed.">
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--green:#0c3528;--green-2:#126b4f;--mint:#eaf5ef;--lime:#c5ec77;--ink:#17251f;--muted:#63716a;--line:#e5ece7;--paper:#fff;--wash:#f6faf7}
        *{box-sizing:border-box}
        body{margin:0;background:var(--wash);color:var(--ink);font-family:"DM Sans",sans-serif;line-height:1.55}
        a{color:var(--green-2);text-decoration:none}
        a:hover{text-decoration:underline}
        .wrap{width:min(760px,calc(100% - 32px));margin:auto}
        header{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:22px 0}
        header img{width:160px;height:auto;display:block}
        header .back{font-size:14px;font-weight:700;color:var(--green)}
        h1{font-family:Manrope,sans-serif;font-size:clamp(28px,4vw,38px);letter-spacing:-.03em;color:var(--green);margin:18px 0 8px}
        .lede{color:var(--muted);margin:0 0 22px;font-size:16px}
        .card{background:var(--paper);border:1px solid var(--line);border-radius:22px;padding:26px;box-shadow:0 18px 46px rgba(9,48,34,.06);margin-bottom:26px}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        .field{display:flex;flex-direction:column;gap:6px;margin-bottom:16px}
        .field.full{grid-column:1/-1}
        label{font-size:13px;font-weight:700;color:var(--green)}
        input,textarea,select{width:100%;padding:12px 14px;border:1px solid #d7e3db;border-radius:12px;font:inherit;color:var(--ink);background:#fff}
        input:focus,textarea:focus,select:focus{outline:2px solid #9ed4b6;outline-offset:1px;border-color:#7cc39b}
        textarea{min-height:150px;resize:vertical}
        .hint{font-size:12px;color:var(--muted)}
        .error{font-size:12px;color:#a3271f;font-weight:600}
        .banner{border-radius:14px;padding:14px 16px;margin-bottom:18px;font-size:14px;font-weight:600}
        .banner.ok{background:#eaf7ee;border:1px solid #bfe4cb;color:#14663f}
        .banner.bad{background:#fdf0ef;border:1px solid #f2c8c4;color:#8f211a}
        .banner ul{margin:8px 0 0;padding-left:18px;font-weight:500}
        button{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:13px 22px;border-radius:14px;border:0;background:var(--green);color:#fff;font:700 14px "DM Sans",sans-serif;cursor:pointer;transition:background .18s}
        button:hover{background:#14543f}
        button[disabled]{opacity:.6;cursor:progress}
        .aside{font-size:14px;color:var(--muted)}
        .aside strong{color:var(--ink)}
        .honeypot{position:absolute!important;left:-10000px!important;top:auto!important;width:1px;height:1px;overflow:hidden}
        footer{padding:10px 0 40px;color:#839088;font-size:12px;text-align:center}
        @media(max-width:560px){.grid{grid-template-columns:1fr}.card{padding:20px}header img{width:140px}}
    </style>
</head>
<body>
<header class="wrap">
    <a href="/" aria-label="{{ $appName }} home"><img src="{{ asset('images/logo-long.png') }}" alt="{{ $appName }}"></a>
    <a class="back" href="/">← Back to home</a>
</header>

<main class="wrap">
    <h1>Contact support</h1>
    <p class="lede">Ask us anything — no account needed. We reply by email, usually within one business day.</p>

    @if(session('contact_status'))
        <div class="banner ok" role="status">{{ session('contact_status') }}</div>
    @endif

    @if($errors->any())
        <div class="banner bad" role="alert">
            Please check the highlighted fields.
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="card">
        <form method="POST" action="{{ route('contact.store') }}" novalidate>
            @csrf
            <div class="grid">
                <div class="field">
                    <label for="name">Your name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" maxlength="120" required autocomplete="name" @error('name') aria-invalid="true" @enderror>
                    @error('name')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="191" required autocomplete="email" @error('email') aria-invalid="true" @enderror>
                    @error('email')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field full">
                    <label for="subject">Subject</label>
                    <input id="subject" name="subject" type="text" value="{{ old('subject') }}" maxlength="160" required @error('subject') aria-invalid="true" @enderror>
                    @error('subject')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field full">
                    <label for="category">What is it about? <span class="hint">(optional)</span></label>
                    <select id="category" name="category">
                        <option value="">Choose a topic</option>
                        @foreach($categories as $category)
                            <option value="{{ $category }}" @selected(old('category') === $category)>{{ str($category)->replace('_',' ')->title() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field full">
                    <label for="message">Your message</label>
                    <textarea id="message" name="message" maxlength="5000" required @error('message') aria-invalid="true" @enderror>{{ old('message') }}</textarea>
                    <span class="hint">Include the email address on your account, a ticket or payment reference, or screenshots' details — it helps us answer faster.</span>
                    @error('message')<span class="error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="honeypot" aria-hidden="true">
                <label for="website">Website</label>
                <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>

            <button type="submit">Send message</button>
        </form>
    </section>

    <p class="aside">
        <strong>Already have an account?</strong> Open the app to see every ticket and reply in one place —
        <a href="{{ $webAppUrl }}">go to {{ $appName }}</a>.
        @if($supportEmail)
            You can also email us at <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.
        @endif
    </p>
</main>

<footer class="wrap">© {{ date('Y') }} {{ $appName }}. All rights reserved.</footer>
</body>
</html>
