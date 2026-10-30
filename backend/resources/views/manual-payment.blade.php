<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manual bank transfer — Nurture Calls</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body { margin:0; font-family:'DM Sans',sans-serif; background:#f6f7f9; color:#171821; }
    .wrap { max-width:560px; margin:60px auto; padding:0 20px; }
    .card { background:#fff; border-radius:16px; padding:32px; box-shadow:0 12px 32px rgba(16,35,63,.09); }
    h1 { font-size:24px; margin:0 0 8px; }
    .row { display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #e7eaf0; }
    .row:last-child { border-bottom:0; }
    .label { color:#6f737b; font-size:13px; }
    .value { font-weight:700; font-size:14px; }
    .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; margin-top:24px; padding:14px 22px; border-radius:999px; background:#2375d1; color:#fff; font-weight:700; text-decoration:none; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>Manual bank transfer</h1>
      <p style="color:#6f737b;font-size:14px;margin:0 0 20px;">Pay the exact amount into the account below, then click "I have paid" to share your proof with support.</p>
      <div class="row"><span class="label">Account number</span><span class="value">{{ config('settings.payment.manual_bank_account_number') }}</span></div>
      <div class="row"><span class="label">Bank name</span><span class="value">{{ config('settings.payment.manual_bank_name') }}</span></div>
      <div class="row"><span class="label">Account name</span><span class="value">{{ config('settings.payment.manual_bank_account_name') }}</span></div>
      <div class="row"><span class="label">SWIFT code</span><span class="value">{{ config('settings.payment.manual_bank_swift_code') }}</span></div>
      @if(! empty($itemName))
      <div class="row"><span class="label">Item</span><span class="value">{{ $itemName }}</span></div>
      @endif
      @if($amount !== null)
      <div class="row"><span class="label">Amount to pay</span><span class="value">{{ $currency }} {{ number_format((float) $amount, 2) }}</span></div>
      @endif
      <div class="row"><span class="label">Reference</span><span class="value">{{ $reference }}</span></div>
      <a class="btn" href="{{ config('settings.contact.telegram_revenue', config('settings.contact.telegram_general')) }}" target="_blank" rel="noopener">I have paid — chat support</a>
    </div>
  </div>
</body>
</html>
