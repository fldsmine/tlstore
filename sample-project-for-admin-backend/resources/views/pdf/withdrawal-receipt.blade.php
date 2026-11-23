<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'DejaVu Sans', sans-serif; }
        body { background: #eef1f0; color: #16211e; padding: 8px; }
        .card { background: #ffffff; border: 1px solid #e3e8e6; border-radius: 20px; overflow: hidden; }
        .header { background-color: #18443E; padding: 20px 26px; }
        .header table { width: 100%; }
        .header .logo { height: 22px; }
        .pill { color: #E6B34A; border: 1px solid rgba(230,179,74,0.55); border-radius: 20px; font-size: 8.5px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; padding: 4px 10px; }
        .perf { border-top: 2px dashed #cfdad6; margin: 0 20px; }
        .body { padding: 0 28px 8px; text-align: center; }
        .check-wrap { margin-top: -24px; text-align: center; }
        .title { font-size: 19px; font-weight: bold; margin-top: 12px; }
        .subtitle { font-size: 11.5px; color: #6b7772; margin-top: 4px; }
        .hair { width: 40px; height: 2px; background: #E6B34A; margin: 12px auto 0; }
        .amt-label { color: #6b7772; font-size: 9.5px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; margin-top: 16px; }
        .amt { color: #18443E; font-size: 30px; font-weight: bold; margin-top: 5px; }
        .ref { border: 1px solid #dbe7e2; background: #f3f7f5; border-radius: 14px; padding: 11px 12px; margin-top: 18px; text-align: left; }
        .ref .l { color: #2E7266; font-size: 9px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; }
        .ref .v { color: #123A34; font-size: 15px; font-weight: bold; letter-spacing: 1.4px; margin-top: 4px; }
        table.rows { width: 100%; border-collapse: collapse; margin-top: 16px; }
        table.rows td { padding: 9px 0; border-bottom: 1px solid #eef1f0; font-size: 12.5px; vertical-align: top; }
        table.rows td.l { color: #6b7772; text-align: left; }
        table.rows td.v { color: #16211e; font-weight: bold; text-align: right; }
        .ok { color: #16a34a; }
        .delivery { background: #eafaf0; color: #16a34a; border-radius: 20px; padding: 8px 12px; font-size: 11.5px; font-weight: bold; margin-top: 16px; }
        .foot { border-top: 1px solid #eef1f0; margin-top: 18px; padding-top: 14px; padding-bottom: 6px; text-align: center; }
        .foot .a { color: #16211e; font-size: 11.5px; font-weight: bold; }
        .foot .b { color: #8a938f; font-size: 10px; margin-top: 3px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <table>
                <tr>
                    <td style="text-align:left;"><img class="logo" src="{{ public_path('images/public/logo-coloured.png') }}" alt="{{ config('app.name') }}"></td>
                    <td style="text-align:right;"><span class="pill">Withdrawal Receipt</span></td>
                </tr>
            </table>
        </div>

        <div class="perf"></div>

        <div class="body">
            <div class="check-wrap">
                <img width="48" height="48" alt="Success" src="data:image/svg+xml;base64,{{ base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48" height="48"><circle cx="24" cy="24" r="21" fill="#16a34a" stroke="#ffffff" stroke-width="4"/><path d="M15 24.5l6 6 12-13" fill="none" stroke="#ffffff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>') }}">
            </div>

            <div class="title">Withdrawal Complete</div>
            <div class="subtitle">Transaction processed successfully</div>
            <div class="hair"></div>

            <div class="amt-label">Amount Received</div>
            <div class="amt">{{ $amountPaid }}</div>

            <div class="ref">
                <div class="l">Reference</div>
                <div class="v">{{ $withdrawal->reference }}</div>
            </div>

            <table class="rows">
                <tr><td class="l">Method</td><td class="v">Bank Transfer</td></tr>
                @if ($withdrawal->wallet_label)<tr><td class="l">Earned via</td><td class="v">{{ $withdrawal->wallet_label }}</td></tr>@endif
                @if ($withdrawal->account_name)<tr><td class="l">Account name</td><td class="v">{{ $withdrawal->account_name }}</td></tr>@endif
                @if ($withdrawal->bank_name)<tr><td class="l">Beneficiary bank</td><td class="v">{{ $withdrawal->bank_name }}</td></tr>@endif
                @if ($maskedAccount)<tr><td class="l">Account number</td><td class="v">{{ $maskedAccount }}</td></tr>@endif
                <tr><td class="l">Date &amp; time</td><td class="v">{{ $withdrawal->created_at->format('M jS Y · g:i A') }}</td></tr>
                @if ($fee)<tr><td class="l">Processing fee</td><td class="v">{{ $fee }}</td></tr>@endif
                @if (data_get($withdrawal->meta, 'narration'))<tr><td class="l">Narration</td><td class="v">{{ data_get($withdrawal->meta, 'narration') }}</td></tr>@endif
                <tr><td class="l">Status</td><td class="v ok">&bull; Completed</td></tr>
            </table>

            <div class="delivery">Funds arrive within 24&ndash;48 hours</div>

            <div class="foot">
                <div class="a">Secured transaction</div>
                <div class="b">Keep this receipt for your records &middot; {{ config('app.name') }}</div>
            </div>
        </div>
    </div>
</body>
</html>
