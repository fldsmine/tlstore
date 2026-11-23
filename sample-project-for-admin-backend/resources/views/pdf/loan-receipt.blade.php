<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; font-family: 'DejaVu Sans', sans-serif; }
        body { margin: 0; padding: 28px; color: #17211e; background: #eef2f0; font-size: 12px; }
        .card { width: 100%; max-width: 720px; margin: 0 auto; background: #fff; border: 1px solid #dfe8e4; border-radius: 18px; overflow: hidden; }
        .header { padding: 25px 30px; background: #18443e; color: #fff; }
        .brand { font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #cce0d8; }
        h1 { margin: 10px 0 4px; font-size: 23px; }
        .sub { color: #d1e0dc; font-size: 11px; }
        .body { padding: 25px 30px 30px; }
        .status { display: inline-block; padding: 7px 13px; border-radius: 20px; font-weight: bold; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; background: #fff6dd; color: #a56a00; }
        .status.approved, .status.paid { background: #e7f8ed; color: #16803d; }
        .status.rejected { background: #fff0f0; color: #b42318; }
        .summary { margin: 22px 0 20px; padding: 17px 19px; background: #f4f8f6; border: 1px solid #e2ebe7; border-radius: 14px; }
        .label { color: #708078; font-size: 9px; text-transform: uppercase; letter-spacing: 1.5px; }
        .amount { margin-top: 7px; color: #18443e; font-size: 28px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 11px 5px; border-bottom: 1px solid #edf1ef; vertical-align: top; }
        td:first-child { width: 42%; color: #708078; }
        td:last-child { text-align: right; font-weight: bold; color: #17211e; word-break: break-word; }
        .notice { margin-top: 19px; padding: 13px 15px; border-radius: 11px; background: #fff8e6; color: #76591b; line-height: 1.55; font-size: 10px; }
        .rejection { margin-top: 16px; padding: 13px 15px; border-radius: 11px; background: #fff0f0; color: #a12626; line-height: 1.55; }
        .footer { margin-top: 22px; padding-top: 14px; border-top: 1px solid #e8eeeb; color: #7b8983; text-align: center; font-size: 9px; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <div class="brand">{{ config('app.name') }}</div>
            <h1>Loan application receipt</h1>
            <div class="sub">Application {{ $loan->id }}</div>
        </div>
        <div class="body">
            @php $statusClass = in_array($loan->status->value, ['approved', 'paid'], true) ? $loan->status->value : ($loan->status->value === 'rejected' ? 'rejected' : ''); @endphp
            <span class="status {{ $statusClass }}">{{ $loan->status->label() }}</span>

            <div class="summary">
                <div class="label">Requested loan amount</div>
                <div class="amount">{{ $loanAmount }}</div>
            </div>

            <table>
                <tr><td>Applicant</td><td>{{ $applicant->first_name ?: $applicant->username }}</td></tr>
                <tr><td>Loan type</td><td>{{ $loanType }}</td></tr>
                <tr><td>Total repayment</td><td>{{ $repaymentAmount }}</td></tr>
                <tr><td>Repayment date</td><td>{{ $loan->due_date?->format('j M Y') ?? '—' }}</td></tr>
                <tr><td>Insurance fee</td><td>{{ $loan->application_fee_currency }} {{ number_format((float) $loan->application_fee_amount, 2) }}@if ($loan->application_fee_type === 'percentage') ({{ number_format((float) $loan->application_fee_percentage, 2) }}% )@else (fixed)@endif</td></tr>
                <tr><td>Fee payment method</td><td>{{ $feeMethod }}</td></tr>
                @if ($loan->application_fee_reference)
                    <tr><td>Payment reference</td><td>{{ $loan->application_fee_reference }}</td></tr>
                @endif
                <tr><td>Agreement accepted</td><td>{{ $loan->agreement_accepted_at?->format('j M Y, g:i A') ?? '—' }}</td></tr>
                <tr><td>Submitted</td><td>{{ $loan->created_at?->format('j M Y, g:i A') ?? '—' }}</td></tr>
                <tr><td>Application state</td><td>{{ $loan->status->label() }}</td></tr>
                @if ($loan->paid_at)
                    <tr><td>Marked paid</td><td>{{ $loan->paid_at->format('j M Y, g:i A') }}</td></tr>
                @endif
            </table>

            @if ($loan->status->value === 'rejected')
                <div class="rejection"><strong>Decision note:</strong> {{ $rejectionReason }}</div>
            @endif

            <div class="notice">
                Loan applications are reviewed by Nextel staff. Keep this receipt for your records.
            </div>

            <div class="footer">This receipt confirms the application details and current review state shown above.<br>{{ config('app.name') }}</div>
        </div>
    </div>
</body>
</html>
