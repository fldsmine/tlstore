@php
    /*
    |--------------------------------------------------------------------------
    | Withdrawal receipt (PDF)
    |--------------------------------------------------------------------------
    |
    | Rendered by dompdf. Pass a `$receipt` array — either one record straight
    | from the withdrawals API (``payout_details``, ``option``, ``amount_minor``
    | ...) or an already flattened array with the keys used below. Everything is
    | optional; sensible brand defaults keep the page looking finished.
    |
    | Optional: $brand = [
    |     'name' => 'TL Store',
    |     'domain' => 'tlstore.org',
    |     'logo' => public_path('images/logo-long.png'),
    |     'google_badge' => public_path('images/pdf/google-play-badge.png'),
    |     'apple_badge' => public_path('images/pdf/app-store-badge.png'),
    |     'colors' => ['green' => '#0F6B4A', 'red' => '#E2432E', 'amber' => '#D9822B'],
    | ];
    |
    | Quick start (controller):
    |
    |     return Pdf::loadView('pdf.withdrawal-receipt', [
    |         'receipt' => $withdrawal->toArray(),
    |     ])->setPaper('a4');
    |
    | A4 portrait, one sheet. Records carrying more than seven payout rows switch
    | the detail card to a denser row rhythm so the receipt still fits on a page.
    */

    $receipt = $receipt ?? [];

    if (is_object($receipt)) {
        $receipt = method_exists($receipt, 'toArray') ? $receipt->toArray() : (array) $receipt;
    }

    $receipt = is_array($receipt) ? $receipt : [];

    $brand = array_merge([
        'name' => 'TL Store',
        'domain' => 'tlstore.org',
        'legal' => null,
        'logo' => public_path('images/logo-long.png'),
        'google_badge' => public_path('images/pdf/google-play-badge.png'),
        'apple_badge' => public_path('images/pdf/app-store-badge.png'),
    ], $brand ?? []);

    $colors = array_merge([
        'green' => '#0F6B4A',
        'green_dark' => '#0A4B34',
        'green_soft' => '#E8F3EC',
        'red' => '#E2432E',
        'amber' => '#D9822B',
        'paper' => '#F7FBF8',
        'card' => '#EFEFEF',
        'ink' => '#1B211E',
        'muted' => '#7C837D',
        'hairline' => '#DDE6E0',
    ], is_array($brand['colors'] ?? null) ? $brand['colors'] : []);

    $payout = $receipt['payout_details'] ?? [];
    $payout = is_object($payout) ? (array) $payout : (is_array($payout) ? $payout : []);

    $option = $receipt['option'] ?? [];
    $option = is_object($option) ? (array) $option : (is_array($option) ? $option : []);

    $currency = strtoupper((string) ($receipt['currency'] ?? 'NGN'));

    $precision = \App\Application\Money\CurrencyMinorUnits::precision($currency);
    $amount = $receipt['amount'] ?? null;

    if ($amount === null && isset($receipt['amount_minor'])) {
        $amount = ((float) $receipt['amount_minor']) / (10 ** $precision);
    }

    $amount = (float) $amount;
    $amountText = $currency.' '.number_format($amount, 2);

    $status = strtolower((string) ($receipt['status'] ?? 'pending'));

    $tone = 'amber';

    if (in_array($status, ['completed', 'paid', 'approved', 'success', 'successful'], true)) {
        $tone = 'green';
    } elseif (in_array($status, ['failed', 'rejected', 'cancelled', 'declined', 'reversed'], true)) {
        $tone = 'red';
    }

    $toneColor = $colors[$tone === 'green' ? 'green' : ($tone === 'red' ? 'red' : 'amber')];

    $statusLabels = [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'paid' => 'Completed',
        'approved' => 'Approved',
        'failed' => 'Failed',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
        'declined' => 'Declined',
        'reversed' => 'Reversed',
    ];

    $statusLabel = $statusLabels[$status] ?? ucfirst(str_replace('_', ' ', $status));
    $bandLabel = strtoupper($tone === 'green' ? 'Completed' : ($tone === 'red' ? 'Failed' : 'Processing'));

    $formatDate = static function ($value, string $format) {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format($format);
        } catch (\Throwable $exception) {
            return is_string($value) ? $value : null;
        }
    };

    $dateText = $formatDate($receipt['date'] ?? ($receipt['requested_at'] ?? null), 'd M Y, h:i A') ?? '—';
    $generatedText = $formatDate($receipt['generated_at'] ?? null, 'M d, Y')
        ?? \Illuminate\Support\Carbon::now()->format('M d, Y');

    $reference = $receipt['reference'] ?? ($receipt['provider_reference'] ?? null);

    if (! $reference && ! empty($receipt['idempotency_key'])) {
        $segments = explode('-', (string) $receipt['idempotency_key']);
        $tail = trim((string) end($segments));

        if ($tail !== '') {
            $reference = 'WD-'.strtoupper($tail);
        }
    }

    if (! $reference) {
        $reference = 'WD-'.($receipt['id'] ?? '—');
    }

    $name = $receipt['name'] ?? ($payout['account_name'] ?? ($payout['name'] ?? null));
    $bank = $receipt['bank'] ?? ($payout['bank_name'] ?? null);
    $accountNumber = $receipt['account_number'] ?? ($payout['account_number'] ?? null);
    $wallet = $receipt['wallet'] ?? [];
    $wallet = is_object($wallet) ? (array) $wallet : (is_array($wallet) ? $wallet : []);
    $walletType = (string) ($wallet['wallet_type'] ?? $wallet['type'] ?? '');
    $walletLabels = [
        'share' => 'Share Wallet',
        'review' => 'Review Wallet',
        'review_group_b' => 'Play Store Review Wallet',
        'review_group_c' => 'Apple Store Review Wallet',
        'referral' => 'Referral Wallet',
        'bonus' => 'Bonus Wallet',
        'topup_fund' => 'Top-up Fund Wallet',
    ];
    $withdrawnFrom = $receipt['withdrawn_from']
        ?? ($receipt['wallet_name'] ?? null)
        ?? ($walletLabels[$walletType] ?? null)
        ?? (($receipt['wallet_id'] ?? null) ? 'Wallet #'.$receipt['wallet_id'] : null);

    $fieldLabels = [
        'phone_number' => 'Phone number',
        'provider' => 'Network',
        'email' => 'Email',
        'wallet_address' => 'Wallet address',
        'crypto_address' => 'Wallet address',
        'network' => 'Network',
        'iban' => 'IBAN',
        'swift' => 'SWIFT',
        'sort_code' => 'Sort code',
        'bank_code' => 'Bank code',
        'address' => 'Address',
        'country' => 'Country',
    ];

    $rows = [];

    if (is_array($receipt['rows'] ?? null)) {
        foreach ($receipt['rows'] as $row) {
            $rows[] = [
                'label' => (string) ($row['label'] ?? ''),
                'value' => (string) ($row['value'] ?? ''),
                'tone' => $row['tone'] ?? null,
            ];
        }
    } else {
        if ($name) {
            $rows[] = ['label' => 'Name', 'value' => (string) $name, 'tone' => null];
        }
        if ($bank) {
            $rows[] = ['label' => 'Bank', 'value' => (string) $bank, 'tone' => null];
        }
        if ($accountNumber) {
            $rows[] = ['label' => 'Account No.', 'value' => (string) $accountNumber, 'tone' => null];
        }
        if ($withdrawnFrom) {
            $rows[] = ['label' => 'Withdrawn From', 'value' => (string) $withdrawnFrom, 'tone' => null];
        }

        $alreadyShown = ['account_name', 'name', 'bank_name', 'account_number'];

        foreach ($payout as $key => $value) {
            if (in_array((string) $key, $alreadyShown, true)) {
                continue;
            }

            $payment = (is_string($value) || is_numeric($value)) ? trim((string) $value) : '';

            if ($payment !== '') {
                $rows[] = [
                    'label' => $fieldLabels[(string) $key] ?? ucfirst(str_replace('_', ' ', (string) $key)),
                    'value' => $payment,
                    'tone' => null,
                ];
            }
        }

        $rows[] = ['label' => 'Reference No.', 'value' => (string) $reference, 'tone' => null];
        $rows[] = ['label' => 'Date', 'value' => $dateText, 'tone' => null];
        $rows[] = ['label' => 'Status', 'value' => $statusLabel, 'tone' => $tone];
    }

    $processingWindow = (string) ($receipt['processing_window'] ?? '1-24hrs');

    $defaultNote = $tone === 'green'
        ? 'Your withdrawal has been completed. Thank you for using '.$brand['name'].'.'
        : ($tone === 'red'
            ? ((string) ($receipt['failure_reason'] ?? '') !== ''
                ? (string) $receipt['failure_reason']
                : 'This withdrawal could not be completed. Nothing was taken from your balance.')
            : 'Your withdrawal request was submitted successfully. You will be notified once it is completed.');

    $processingNote = (string) ($receipt['processing_note'] ?? $defaultNote);

    $image = static function ($source) {
        if (! is_string($source) || trim($source) === '') {
            return null;
        }

        if (str_starts_with($source, 'data:') || str_starts_with($source, 'http://') || str_starts_with($source, 'https://')) {
            return $source;
        }

        if (! is_file($source)) {
            return null;
        }

        $mime = @mime_content_type($source) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($source));
    };

    $logoUri = $image($brand['logo']);
    $playUri = $image($brand['google_badge']);
    $appleUri = $image($brand['apple_badge']);

    $bars = [50, 84, 99, 114, 65, 91, 57, 46];
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $brand['name'] }} withdrawal receipt {{ $reference }}</title>
    <style>
        @page {
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: "DejaVu Sans", Arial, sans-serif;
            color: {{ $colors['ink'] }};
            background: {{ $colors['paper'] }};
            font-size: 12pt;
            line-height: 1.5;
        }

        .page {
            width: 100%;
            height: 100%;
            background: {{ $colors['paper'] }};
        }

        .bar-top {
            height: 7pt;
            background: {{ $colors['red'] }};
        }

        .sheet {
            padding: 24pt 30pt 15pt 30pt;
        }

        /* Pinned to the bottom edge of the page (dompdf footer idiom). */
        .page-end {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            padding-top: 10pt;
            background: {{ $colors['paper'] }};
        }

        .head {
            width: 100%;
            border-collapse: collapse;
        }

        .head td {
            vertical-align: middle;
            padding: 0;
        }

        .mark {
            display: inline-block;
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: {{ $colors['green'] }};
            color: #FFFFFF;
            font-size: 18pt;
            font-weight: bold;
            line-height: 50px;
            text-align: center;
            vertical-align: middle;
        }

        .brand-logo {
            width: 250px;
            height: 56px;
            object-fit: contain;
            vertical-align: middle;
        }

        .word {
            margin-left: 15pt;
            vertical-align: middle;
            font-size: 25pt;
            font-style: italic;
            font-weight: bold;
            letter-spacing: 0.3pt;
            text-transform: uppercase;
            color: {{ $colors['green'] }};
        }

        .generated {
            text-align: right;
            font-size: 11pt;
            color: {{ $colors['muted'] }};
        }

        .hero {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12pt;
        }

        .hero td {
            vertical-align: bottom;
            padding: 0;
        }

        .hero-label {
            font-size: 15pt;
            color: {{ $colors['muted'] }};
        }

        .amount {
            margin-top: 2pt;
            font-size: 22pt;
            font-weight: bold;
            letter-spacing: -0.8pt;
            line-height: 1;
            white-space: nowrap;
            color: {{ $colors['green'] }};
        }

        .bars {
            border-collapse: collapse;
        }

        .hero .bars-cell {
            width: 160pt;
        }

        .bars td {
            width: 20pt;
            vertical-align: bottom;
            padding: 0 3pt;
        }

        .bars div {
            width: 15pt;
            border-radius: 3pt;
            background: {{ $colors['green_soft'] }};
        }

        .pill-cell {
            width: 100pt;
            text-align: right;
        }

        .pill {
            border-collapse: collapse;
            border-radius: 14pt;
            background: {{ $toneColor }};
        }

        .pill td {
            padding: 6pt 15pt;
            color: #FFFFFF;
            font-size: 9pt;
            font-weight: bold;
            letter-spacing: 1.8pt;
            text-transform: uppercase;
        }

        .card {
            margin-top: 20pt;
            background: {{ $colors['card'] }};
            border-radius: 10pt;
        }

        .card-table {
            width: 100%;
            border-collapse: collapse;
        }

        .card-table td {
            padding: 0;
            vertical-align: top;
        }

        .card-rail {
            width: 4pt;
            background: {{ $colors['red'] }};
            border-radius: 10pt 0 0 10pt;
        }

        .card-table td.card-body {
            padding: 7pt 24pt 7pt 22pt;
        }

        .rows {
            width: 100%;
            border-collapse: collapse;
        }

        .rows td {
            padding: 5.5pt 0;
            vertical-align: middle;
        }

        .rows .label {
            width: 40%;
            color: {{ $colors['muted'] }};
            font-size: 11.5pt;
        }

        .rows .value {
            text-align: right;
            font-size: 11.5pt;
            color: {{ $colors['ink'] }};
        }

        .rows .value.tone-green {
            color: {{ $colors['green'] }};
        }

        .rows .value.tone-amber {
            color: {{ $colors['amber'] }};
        }

        .rows .value.tone-red {
            color: {{ $colors['red'] }};
        }

        .card.dense .rows td {
            padding: 3.5pt 0;
        }

        .card.dense .rows .label,
        .card.dense .rows .value {
            font-size: 10.5pt;
        }

        .mid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 17pt;
        }

        .mid td {
            vertical-align: top;
            padding: 0;
        }

        .mid-copy {
            width: 330pt;
            padding-right: 12pt;
        }

        .mid .sla-cell {
            width: 145pt;
        }

        .h {
            font-size: 15pt;
            font-weight: bold;
            color: {{ $colors['ink'] }};
            margin: 0 0 4pt 0;
        }

        .h.green {
            color: {{ $colors['green'] }};
            margin-top: 11pt;
        }

        .copy {
            margin: 0;
            font-size: 12pt;
            color: {{ $colors['muted'] }};
        }

        .badges {
            border-collapse: collapse;
            margin-top: 6pt;
        }

        .badges td {
            padding: 0 11pt 0 0;
            vertical-align: middle;
        }

        .badges img {
            height: 38px;
        }

        .badges .play {
            width: 127px;
        }

        .badges .apple {
            width: 114px;
        }

        .sla {
            width: 128pt;
            border: 4.5pt solid {{ $colors['green'] }};
            border-radius: 12pt;
            text-align: center;
            background: #FFFFFF;
        }

        .sla-body {
            padding: 13pt 8pt 10pt 8pt;
        }

        .sla-value {
            font-size: 24pt;
            font-weight: bold;
            color: {{ $colors['green'] }};
            letter-spacing: -0.6pt;
        }

        .sla-note {
            font-size: 10.5pt;
            color: {{ $colors['muted'] }};
        }

        .sla-band {
            background: {{ $colors['green'] }};
            color: #FFFFFF;
            font-size: 10pt;
            font-weight: bold;
            letter-spacing: 1.6pt;
            padding: 6pt 0 7pt 0;
            border-bottom-left-radius: 8pt;
            border-bottom-right-radius: 8pt;
        }

        .rule {
            margin: 17pt 0 11pt 0;
            border-top: 1pt solid {{ $colors['hairline'] }};
            height: 0;
            line-height: 0;
            font-size: 0;
        }

        .thanks {
            font-size: 15pt;
            font-weight: bold;
            letter-spacing: 0.8pt;
            color: {{ $colors['green'] }};
            text-transform: uppercase;
            margin: 0 0 5pt 0;
        }

        .support {
            margin: 0;
            font-size: 12pt;
            color: {{ $colors['muted'] }};
        }

        .link {
            color: {{ $colors['red'] }};
            font-weight: bold;
        }

        .fine {
            margin: 11pt 0 0 0;
            font-size: 8pt;
            color: {{ $colors['muted'] }};
            line-height: 1.5;
        }

        .bar-bottom {
            height: 11pt;
            border-collapse: collapse;
            width: 100%;
        }

        .bar-bottom td {
            padding: 0;
            height: 11pt;
        }

        .bar-bottom .red {
            width: 90pt;
            background: {{ $colors['red'] }};
        }

        .bar-bottom .green {
            background: {{ $colors['green'] }};
        }
    </style>
</head>
<body>
<div class="page">
    <div class="bar-top"></div>

    <div class="sheet">
        <table class="head">
            <tr>
                <td class="brand">
                    @if ($logoUri)
                        <img src="{{ $logoUri }}" width="250" height="56" class="brand-logo" alt="{{ $brand['name'] }}">
                    @else
                        <span class="mark">TL</span>
                        <span class="word">{{ $brand['name'] }}</span>
                    @endif
                </td>
                <td class="generated">Generated on {{ $generatedText }}</td>
            </tr>
        </table>

        <table class="hero">
            <tr>
                <td>
                    <div class="hero-label">Withdrawal Receipt</div>
                    <div class="amount">{{ $amountText }}</div>
                </td>
                <td class="bars-cell" align="right">
                    <table class="bars" align="right">
                        <tr>
                            @foreach ($bars as $bar)
                                <td><div style="height: {{ $bar }}pt;"></div></td>
                            @endforeach
                        </tr>
                    </table>
                </td>
                <td class="pill-cell">
                    <table class="pill" align="right">
                        <tr>
                            <td>{{ $statusLabel }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div class="card{{ count($rows) > 7 ? ' dense' : '' }}">
            <table class="card-table">
                <tr>
                    <td class="card-rail"></td>
                    <td class="card-body">
                        <table class="rows">
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="label">{{ $row['label'] }}</td>
                                    <td class="value {{ $row['tone'] ? 'tone-'.$row['tone'] : '' }}">{{ $row['value'] }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        <table class="mid">
            <tr>
                <td class="mid-copy">
                    <p class="h">Processing time</p>
                    <p class="copy">{{ $processingNote }}</p>

                    @if ($playUri || $appleUri)
                        <p class="h green">Find us in stores</p>
                        <table class="badges">
                            <tr>
                                @if ($playUri)
                                    <td><img src="{{ $playUri }}" width="177" height="53" class="play" alt="Get it on Google Play"></td>
                                @endif
                                @if ($appleUri)
                                    <td><img src="{{ $appleUri }}" width="158" height="53" class="apple" alt="Download on the App Store"></td>
                                @endif
                            </tr>
                        </table>
                    @endif
                </td>
                <td class="sla-cell" align="right">
                    <div class="sla">
                        <div class="sla-body">
                            <div class="sla-value">{{ $processingWindow }}</div>
                            <div class="sla-note">business days</div>
                        </div>
                        <div class="sla-band">{{ $bandLabel }}</div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="rule"></div>

        <p class="thanks">Thank you for using {{ $brand['name'] }}</p>
        <p class="support">
            For questions about your withdrawal, visit
            <span class="link">{{ $brand['domain'] }}</span> or contact
            {{ $brand['name'] }} support.
        </p>
        <p class="fine">
            {{ $brand['legal'] ?? ($brand['name'].'. All rights reserved.') }}
            This receipt confirms a withdrawal request submitted to {{ $brand['name'] }}.
            Funds are subject to processing. Keep this document for your records.
        </p>
    </div>

    <div class="page-end">
        <table class="bar-bottom">
            <tr>
                <td class="red"></td>
                <td class="green"></td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>
