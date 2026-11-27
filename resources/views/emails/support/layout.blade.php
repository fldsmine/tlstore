<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject ?? ($mailSubject ?? 'TLStore support') }}</title>
</head>
<body style="margin:0;background:#f1f5f2;color:#132d23;font:15px/1.65 Arial,Helvetica,sans-serif;padding:28px 14px">
<main style="max-width:620px;margin:auto;background:#fff;border:1px solid #dce6df;border-radius:16px;padding:30px">
    <div style="font-weight:800;font-size:18px;color:#08734f;margin-bottom:20px">{{ $app_name ?? config('app.name', 'TLStore') }}</div>
    @yield('body')
    <div style="border-top:1px solid #e6ece8;margin-top:24px;padding-top:14px;color:#64756d;font-size:12px">
        {{ $app_name ?? config('app.name', 'TLStore') }} · Review · Share · Earn
        @isset($support_email)
            @if($support_email !== '')
                <br>Need anything else? Reply to this email or write to {{ $support_email }}.
            @endif
        @endisset
    </div>
</main>
</body>
</html>
