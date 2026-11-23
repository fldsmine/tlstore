<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Loan receipt PNG — {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 28px 16px 40px; background: #eef2f0; color: #17211e; font-family: Arial, Helvetica, sans-serif; }
        main { width: min(100%, 760px); margin: 0 auto; }
        .actions { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin: 0 0 18px; }
        .actions p { margin: 0; color: #53625c; font-size: 14px; line-height: 1.5; }
        button { flex: 0 0 auto; border: 0; border-radius: 10px; padding: 12px 18px; background: #18443e; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        button:disabled { opacity: .55; cursor: wait; }
        .preview { overflow: hidden; border-radius: 16px; box-shadow: 0 14px 40px #102c2418; }
        svg { display: block; width: 100%; height: auto; background: #eef2f0; }
        @media (max-width: 540px) { .actions { align-items: stretch; flex-direction: column; } button { width: 100%; } }
    </style>
</head>
<body>
    @php
        $statusValue = $loan->status?->value ?? 'processing';
        $statusColor = match ($statusValue) {
            'approved' => '#16803d',
            'rejected' => '#b42318',
            default => '#a56a00',
        };
        $receiptRows = [
            ['Applicant', $applicant->first_name ?: $applicant->username],
            ['Loan type', $loanType],
            ['Total repayment', $repaymentAmount],
            ['Repayment date', $loan->due_date?->format('j M Y') ?? '—'],
            ['Insurance fee', ($loan->application_fee_currency ?: '—').' '.number_format((float) $loan->application_fee_amount, 2).($loan->application_fee_type === 'percentage' ? ' ('.number_format((float) $loan->application_fee_percentage, 2).'% )' : ' (fixed)')],
            ['Fee payment method', $feeMethod],
        ];
        if ($loan->application_fee_reference) {
            $receiptRows[] = ['Payment reference', $loan->application_fee_reference];
        }
        $receiptRows[] = ['Agreement accepted', $loan->agreement_accepted_at?->format('j M Y, g:i A') ?? '—'];
        $receiptRows[] = ['Submitted', $loan->created_at?->format('j M Y, g:i A') ?? '—'];
        $receiptRows[] = ['Application state', $loan->status?->label() ?? 'Processing'];
        $lastRowY = 500 + (max(0, count($receiptRows) - 1) * 64);
        $decisionY = $lastRowY + 60;
        $noticeY = $decisionY + ($statusValue === 'rejected' ? 70 : 35);
        $footerY = $noticeY + 150;
        $canvasHeight = $footerY + 55;
    @endphp

    <main>
        <div class="actions">
            <p id="png-status" role="status">Prepare a high-resolution PNG receipt for application {{ $loan->id }}.</p>
            <button id="download-png" type="button" data-filename="Nextel-Loan-Receipt-{{ $loan->id }}.png" disabled>Preparing PNG…</button>
        </div>
        <div class="preview">
            <svg id="loan-receipt" xmlns="http://www.w3.org/2000/svg" width="900" height="{{ $canvasHeight }}" viewBox="0 0 900 {{ $canvasHeight }}" role="img" aria-label="Loan application receipt">
                <rect width="900" height="{{ $canvasHeight }}" fill="#eef2f0"/>
                <rect x="40" y="32" width="820" height="{{ $canvasHeight - 64 }}" rx="24" fill="#ffffff" stroke="#dfe8e4" stroke-width="2"/>
                <path d="M64 32h772a24 24 0 0 1 24 24v178H40V56a24 24 0 0 1 24-24z" fill="#18443e"/>
                <text x="88" y="91" fill="#cce0d8" font-size="18" font-weight="700" letter-spacing="3">{{ config('app.name') }}</text>
                <text x="88" y="148" fill="#ffffff" font-size="38" font-weight="700">Loan application receipt</text>
                <text x="88" y="187" fill="#d1e0dc" font-size="18">Application {{ $loan->id }}</text>

                <rect x="82" y="250" width="736" height="52" rx="26" fill="{{ $statusColor }}" fill-opacity=".10"/>
                <text x="108" y="284" fill="{{ $statusColor }}" font-size="18" font-weight="700" letter-spacing="1.5">APPLICATION STATE: {{ strtoupper($loan->status?->label() ?? 'Processing') }}</text>

                <text x="88" y="356" fill="#708078" font-size="15" font-weight="700" letter-spacing="2">REQUESTED LOAN AMOUNT</text>
                <text x="88" y="414" fill="#18443e" font-size="42" font-weight="700">{{ $loanAmount }}</text>
                <line x1="88" y1="450" x2="812" y2="450" stroke="#e8eeeb" stroke-width="2"/>

                @foreach ($receiptRows as $index => $row)
                    @php $rowY = 500 + ($index * 64); @endphp
                    <text x="92" y="{{ $rowY }}" fill="#708078" font-size="17">{{ $row[0] }}</text>
                    <text x="808" y="{{ $rowY }}" fill="#17211e" font-size="17" font-weight="700" text-anchor="end">{{ $row[1] }}</text>
                    <line x1="92" y1="{{ $rowY + 25 }}" x2="808" y2="{{ $rowY + 25 }}" stroke="#edf1ef" stroke-width="1.5"/>
                @endforeach

                @if ($statusValue === 'rejected')
                    <text x="92" y="{{ $decisionY }}" fill="#a12626" font-size="15" font-weight="700">Decision note: {{ \Illuminate\Support\Str::limit($rejectionReason, 72) }}</text>
                @endif

                <rect x="82" y="{{ $noticeY }}" width="736" height="54" rx="16" fill="#fff8e6"/>
                <text x="106" y="{{ $noticeY + 41 }}" fill="#76591b" font-size="15">Nextel Staff review every application.</text>
                <text x="450" y="{{ $footerY }}" fill="#7b8983" font-size="14" text-anchor="middle">Keep this receipt for your records · {{ config('app.name') }}</text>
            </svg>
        </div>
    </main>

    <script>
        (() => {
            const svg = document.getElementById('loan-receipt');
            const button = document.getElementById('download-png');
            const status = document.getElementById('png-status');
            const filename = button.dataset.filename;
            const image = new Image();
            const source = new Blob([new XMLSerializer().serializeToString(svg)], { type: 'image/svg+xml;charset=utf-8' });
            const sourceUrl = URL.createObjectURL(source);
            let pngUrl = null;

            image.onload = () => {
                const scale = 2;
                const canvas = document.createElement('canvas');
                canvas.width = svg.viewBox.baseVal.width * scale;
                canvas.height = svg.viewBox.baseVal.height * scale;
                const context = canvas.getContext('2d');
                context.fillStyle = '#eef2f0';
                context.fillRect(0, 0, canvas.width, canvas.height);
                context.drawImage(image, 0, 0, canvas.width, canvas.height);
                canvas.toBlob((blob) => {
                    URL.revokeObjectURL(sourceUrl);
                    if (!blob) {
                        status.textContent = 'PNG export is not supported by this browser. Use the PDF receipt instead.';
                        button.textContent = 'PNG unavailable';
                        return;
                    }
                    pngUrl = URL.createObjectURL(blob);
                    button.disabled = false;
                    button.textContent = 'Download PNG';
                    status.textContent = 'Your high-resolution PNG receipt is ready to download.';
                }, 'image/png');
            };
            image.onerror = () => {
                URL.revokeObjectURL(sourceUrl);
                status.textContent = 'PNG export failed in this browser. Use the PDF receipt instead.';
                button.textContent = 'PNG unavailable';
            };
            image.src = sourceUrl;

            button.addEventListener('click', () => {
                if (!pngUrl) return;
                const link = document.createElement('a');
                link.href = pngUrl;
                link.download = filename;
                link.click();
            });
        })();
    </script>
</body>
</html>
