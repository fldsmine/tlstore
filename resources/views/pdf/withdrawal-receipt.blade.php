<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Withdrawal Receipt</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; color: #142c24; font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .top-rule { height: 8px; background: #e5413b; }
        .page { padding: 34px 42px 28px; }
        .header { width: 100%; margin-bottom: 35px; }
        .brand-cell, .generated-cell { vertical-align: middle; }
        .brand-mark { width: 54px; height: 54px; border-radius: 14px; background: #e8752e; color: white; text-align: center; font-size: 27px; font-weight: bold; line-height: 54px; display: inline-block; }
        .logo { width: 54px; height: 54px; object-fit: cover; border-radius: 14px; }
        .brand-name { display: inline-block; margin-left: 13px; color: #14664a; font-size: 25px; font-style: italic; font-weight: bold; vertical-align: middle; }
        .generated-cell { color: #68746f; text-align: right; font-size: 11px; }
        .title { color: #68746f; font-size: 20px; margin: 0 0 4px; }
        .amount { color: #147251; font-size: 30px; font-weight: bold; margin: 0; }
        .status { display: inline-block; min-width: 120px; padding: 10px 14px; border-radius: 20px; background: #bd6500; color: #fff; text-align: center; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .status-completed, .status-paid, .status-approved { background: #147251; }
        .status-failed, .status-rejected, .status-cancelled { background: #bd3434; }
        .status-processing { background: #bd6500; }
        .amount-row { width: 100%; margin-top: 28px; margin-bottom: 34px; }
        .watermark { text-align: right; color: #edf2ee; font-size: 32px; letter-spacing: 8px; }
        .receipt-box { background: #f1f2f0; border-radius: 20px; padding: 25px 28px; border-left: 5px solid #e5413b; }
        .receipt-table { width: 100%; border-collapse: collapse; }
        .receipt-table td { padding: 8px 6px; vertical-align: top; font-size: 11px; }
        .receipt-table td:first-child { color: #737a77; width: 33%; }
        .receipt-table td:last-child { color: #202522; text-align: right; font-weight: 500; word-wrap: break-word; }
        .status-text { color: #bd6500 !important; font-weight: bold !important; }
        .processing { width: 100%; margin-top: 33px; }
        .processing-copy { width: 72%; vertical-align: middle; }
        .processing-copy h2 { margin: 0 0 7px; font-size: 15px; font-weight: normal; color: #202522; }
        .processing-copy p { margin: 0; color: #68746f; line-height: 1.5; font-size: 11px; }
        .time-box { width: 100px; border: 3px solid #147251; border-radius: 15px; padding: 14px 5px 9px; text-align: center; color: #147251; }
        .time-box strong { font-size: 19px; }
        .time-box small { display: block; color: #68746f; margin-top: 5px; font-size: 9px; }
        .time-box b { display: block; color: #147251; margin-top: 10px; font-size: 8px; }
        .footer { border-top: 1px solid #d8ddda; margin-top: 29px; padding-top: 18px; }
        .footer h3 { color: #147251; font-size: 15px; margin: 0 0 9px; }
        .footer p { color: #68746f; line-height: 1.5; font-size: 10px; margin: 0 0 9px; }
        .fine-print { font-size: 8px !important; color: #89918d !important; margin-top: 15px !important; }
        .red { color: #d84b45; }
    </style>
</head>
<body>
    <div class="top-rule"></div>
    <div class="page">
        <table class="header">
            <tr>
                <td class="brand-cell">
                    @if($logo)
                        <img class="logo" src="{{ $logo }}" alt="{{ $brand }}">
                    @else
                        <span class="brand-mark">TL</span>
                    @endif
                    <span class="brand-name">{{ $brand }}</span>
                </td>
                <td class="generated-cell">Generated on {{ $generatedAt->format('M d, Y') }}</td>
            </tr>
        </table>

        <table class="amount-row">
            <tr>
                <td>
                    <p class="title">Withdrawal Receipt</p>
                    <p class="amount">{{ $amount }}</p>
                </td>
                <td style="text-align:right; vertical-align:middle;">
                    <span class="status status-{{ strtolower($status) }}">{{ str_replace('_', ' ', $status) }}</span>
                </td>
            </tr>
        </table>

        <div class="receipt-box">
            <table class="receipt-table">
                @foreach($details as $row)
                    <tr><td>{{ $row['label'] }}</td><td>{{ $row['value'] }}</td></tr>
                @endforeach
                <tr><td>Withdrawn From</td><td>{{ $walletName }}</td></tr>
                <tr><td>Withdrawal Fee</td><td>{{ $fee }}</td></tr>
                <tr><td>Reference No.</td><td>{{ $reference }}</td></tr>
                <tr><td>Date</td><td>{{ $requestedAt?->format('d M Y, h:i A') ?? 'Date unavailable' }}</td></tr>
                <tr><td>Status</td><td class="status-text">{{ ucfirst(str_replace('_', ' ', $status)) }}</td></tr>
                @if($processedAt)
                    <tr><td>Processed</td><td>{{ $processedAt->format('d M Y, h:i A') }}</td></tr>
                @endif
                @if($failureReason)
                    <tr><td>Reason</td><td>{{ $failureReason }}</td></tr>
                @endif
            </table>
        </div>

        <table class="processing">
            <tr>
                <td class="processing-copy">
                    <h2>Processing time</h2>
                    <p>{{ $status === 'completed' || $status === 'paid' ? 'Your withdrawal has been completed.' : 'Your withdrawal request was submitted successfully. You will be notified once it is completed.' }}</p>
                </td>
                <td style="text-align:right; vertical-align:middle;">
                    <div class="time-box">
                        <strong>{{ $status === 'completed' || $status === 'paid' ? 'Done' : '1–24 hrs' }}</strong>
                        <small>business days</small>
                        <b>{{ strtoupper(str_replace('_', ' ', $status)) }}</b>
                    </div>
                </td>
            </tr>
        </table>

        <div class="footer">
            <h3>THANK YOU FOR USING {{ strtoupper($brand) }}</h3>
            <p>For questions about this withdrawal, visit <span class="red">{{ $appUrl }}</span>@if($supportEmail) or contact {{ $supportEmail }}@endif support.</p>
            <p class="fine-print">{{ $brand }}. This receipt confirms a withdrawal request submitted through the platform. Funds may be subject to processing.</p>
        </div>
    </div>
</body>
</html>
