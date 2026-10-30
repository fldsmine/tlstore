@php
    // Embed local image files as data URIs so DomPDF does not depend on a remote asset URL,
    // host configuration, or an external network connection when generating the PDF.
    $brandLogoPath = public_path('images/public/logo-coloured.png');
    $brandLogoData = is_file($brandLogoPath) && is_readable($brandLogoPath)
        ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($brandLogoPath))
        : null;

    // Use the local management-signature image when configured; otherwise show a placeholder.
    $managementSignaturePath = public_path('images/public/signature.jpg');
    $managementSignatureData = is_file($managementSignaturePath) && is_readable($managementSignaturePath)
        ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($managementSignaturePath))
        : null;

    $applicantName = trim(implode(' ', array_filter([
        $applicant->first_name,
        $applicant->last_name,
    ], fn ($part) => filled($part)))) ?: $applicant->username;
    $guarantorName = filled($form['full_name'] ?? null) ? $form['full_name'] : 'Guarantor';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Loan Guarantor Form - {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { size: A4; margin: 12mm; }
        html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body {
            margin: 0;
            padding: 0;
            background: #fff;
            color: #202c28;
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            line-height: 1.55;
        }
        .document { width: 100%; margin: 0 auto; background: #fff; border: 1px solid #d9e2dc; }
        .document-header { padding: 22px 28px 20px; border-bottom: 4px solid #b99a58; background: #174b3f; color: #fff; }
        .brand-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .brand-logo-cell { width: 190px; padding: 0 18px 0 0; vertical-align: middle; }
        .brand-logo { display: block; width: 174px; height: auto; max-height: 48px; }
        .brand-logo-fallback { color: #fff; font-size: 13pt; font-weight: bold; }
        .brand-details { vertical-align: middle; color: #d4e4dc; font-size: 8pt; letter-spacing: 1.3px; text-align: right; text-transform: uppercase; }
        .document-label { margin-top: 20px; padding-top: 15px; border-top: 1px solid #638176; }
        .eyebrow { margin-bottom: 5px; color: #d6c18e; font-size: 7.5pt; font-weight: bold; letter-spacing: 1.7px; text-transform: uppercase; }
        h1 { margin: 0; color: #fff; font-size: 21pt; font-weight: bold; line-height: 1.3; }
        .document-label p { margin: 6px 0 0; color: #d5e3dc; font-size: 9pt; }
        .reference { margin-top: 14px; padding: 9px 12px; border: 1px solid #638176; background: #20574b; }
        .reference-label { display: block; margin-bottom: 2px; color: #c8dcd2; font-size: 7pt; letter-spacing: 1px; text-transform: uppercase; }
        .reference-value { color: #fff; font-size: 10pt; font-weight: bold; word-wrap: break-word; }
        .document-body { padding: 22px 28px 26px; }
        .notice { margin-bottom: 20px; padding: 11px 13px; border-left: 3px solid #b99a58; background: #f6f8f5; color: #58665f; font-size: 8pt; line-height: 1.75; }
        .section { margin: 21px 0 9px; page-break-inside: avoid; }
        .section-heading { width: 100%; padding-bottom: 7px; border-bottom: 1px solid #dce5df; }
        .section-heading table { width: 100%; border-collapse: collapse; }
        .section-heading td { padding: 0; vertical-align: middle; }
        .section-number { width: 28px; color: #b99a58; font-size: 9pt; font-weight: bold; }
        .section-title { color: #174b3f; font-size: 8.5pt; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        .section-description { margin: 4px 0 0 28px; color: #6d7b74; font-size: 8pt; }
        .details-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .details-table tr { page-break-inside: avoid; }
        .details-table td { padding: 9px 5px; border-bottom: 1px solid #edf1ee; vertical-align: bottom; }
        .details-table td:first-child { width: 37%; padding-right: 12px; color: #6d7b74; font-size: 8pt; }
        .details-table td:last-child { color: #202c28; text-align: left; word-wrap: break-word; }
        .handwritten { color: #233e64 !important; font-family: 'DejaVu Serif', serif; font-size: 12pt; font-style: italic; font-weight: normal; }
        .handwritten.compact { font-size: 10pt; }
        .regular-value { font-size: 9pt; font-weight: bold; }
        .money-value { color: #174b3f !important; font-size: 10.5pt; font-weight: bold; }
        .application-strip { width: 100%; margin-top: 14px; border: 1px solid #dce5df; border-collapse: collapse; background: #fafcf9; }
        .application-strip td { width: 50%; padding: 9px 12px; vertical-align: top; }
        .application-strip td + td { border-left: 1px solid #dce5df; }
        .strip-label { display: block; margin-bottom: 4px; color: #6d7b74; font-size: 7pt; letter-spacing: .6px; text-transform: uppercase; }
        .strip-value { color: #174b3f; font-size: 9.5pt; font-weight: bold; word-wrap: break-word; }
        .declaration { margin-top: 19px; padding: 12px 14px; border: 1px solid #dce5df; background: #fbfcfa; page-break-inside: avoid; }
        .declaration-title { margin: 0 0 6px; color: #174b3f; font-size: 8pt; font-weight: bold; letter-spacing: .8px; text-transform: uppercase; }
        .declaration p { margin: 0; color: #59665f; font-size: 8pt; line-height: 1.75; }
        .electronic-signature { margin-top: 15px; padding: 12px 14px; border: 1px dashed #b8c9bd; background: #f8fbf7; page-break-inside: avoid; }
        .signature-label { margin-bottom: 5px; color: #6d7b74; font-size: 7pt; font-weight: bold; letter-spacing: .8px; text-transform: uppercase; }
        .signature-name { min-height: 27px; color: #233e64; font-family: 'DejaVu Serif', serif; font-size: 19pt; font-style: italic; font-weight: bold; line-height: 1.2; word-wrap: break-word; }
        .signature-note { margin-top: 5px; color: #6d7b74; font-size: 7pt; line-height: 1.6; }
        .signature-footer { margin-top: 25px; page-break-inside: avoid; }
        .signature-footer-heading { margin-bottom: 12px; color: #174b3f; font-size: 8pt; font-weight: bold; letter-spacing: .9px; text-transform: uppercase; }
        .signature-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .signature-table td { width: 50%; padding: 0 6px; vertical-align: top; }
        .signature-box { min-height: 118px; padding: 10px; border: 1px solid #dce5df; background: #fff; }
        .signature-role { color: #174b3f; font-size: 7pt; font-weight: bold; letter-spacing: .5px; text-transform: uppercase; }
        .signature-image-area { height: 57px; margin-top: 3px; border-bottom: 1px solid #aebbb3; vertical-align: middle; }
        .management-signature-image { display: block; max-width: 145px; max-height: 50px; width: auto; height: auto; }
        .signature-placeholder { color: #9aa69f; font-size: 8pt; font-style: italic; }
        .user-signature-text { color: #233e64; font-family: 'DejaVu Serif', serif; font-size: 16pt; font-style: italic; font-weight: bold; word-wrap: break-word; }
        .signature-person { margin-top: 6px; color: #202c28; font-size: 8pt; font-weight: bold; word-wrap: break-word; }
        .signature-position { margin-top: 2px; color: #6d7b74; font-size: 7pt; }
        .signature-date { margin-top: 6px; color: #6d7b74; font-size: 7pt; }
        .document-footer { margin-top: 22px; padding-top: 11px; border-top: 2px solid #174b3f; color: #6d7b74; font-size: 7pt; line-height: 1.7; text-align: center; page-break-inside: avoid; }
        .footer-brand { margin-bottom: 2px; color: #174b3f; font-size: 8pt; font-weight: bold; }
        .footer-reference { margin-top: 3px; font-size: 6.5pt; word-wrap: break-word; }
        .preview-page { padding: 24px 16px 48px; background: #eef2f0; }
        .preview-actions { display: flex; align-items: center; justify-content: space-between; gap: 16px; width: min(100%, 900px); margin: 0 auto 18px; }
        .preview-copy { color: #53625c; font-family: Arial, Helvetica, sans-serif; font-size: 13px; line-height: 1.5; }
        .preview-copy strong { display: block; margin-bottom: 2px; color: #174b3f; font-size: 15px; }
        .preview-copy span { display: block; }
        .preview-buttons { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .preview-button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 42px; padding: 10px 16px; border: 1px solid transparent; border-radius: 10px; font-family: Arial, Helvetica, sans-serif; font-size: 13px; font-weight: 700; text-decoration: none; }
        .preview-button-primary { background: #174b3f; color: #fff; }
        .preview-button-secondary { border-color: #d4dfd9; background: #fff; color: #174b3f; }
        .preview-page .document { max-width: 900px; box-shadow: 0 14px 40px #102c2418; }
        @media (max-width: 620px) {
            .preview-page { padding: 16px 10px 28px; }
            .preview-actions { align-items: stretch; flex-direction: column; gap: 12px; }
            .preview-buttons { display: grid; grid-template-columns: 1fr 1fr; }
            .preview-button { padding: 9px 10px; text-align: center; }
            .preview-page .document-body { padding: 18px 18px 22px; }
            .preview-page .document-header { padding: 18px; }
            .preview-page .brand-logo-cell { width: 150px; padding-right: 10px; }
            .preview-page .brand-logo { width: 135px; }
        }
        @media print {
            .preview-page { padding: 0; background: #fff; }
            .preview-actions { display: none; }
            .preview-page .document { max-width: none; border: 0; box-shadow: none; }
        }
    </style>
</head>
<body class="{{ ($previewMode ?? false) ? 'preview-page' : '' }}">
    @if ($previewMode ?? false)
        <nav class="preview-actions" aria-label="Guarantor form preview actions">
            <div class="preview-copy">
                <strong>Guarantor form preview</strong>
                <span>Review the submitted form, download a PDF, or return to the previous page.</span>
            </div>
            <div class="preview-buttons">
                <a href="{{ route('loan') }}" class="preview-button preview-button-secondary" onclick="if (window.history.length > 1) { window.history.back(); return false; }">
                    <span aria-hidden="true">←</span> Back
                </a>
                <a href="{{ $pdfDownloadUrl }}" class="preview-button preview-button-primary">
                    <span aria-hidden="true">↓</span> Download PDF
                </a>
            </div>
        </nav>
    @endif
    <main class="document">
        <header class="document-header">
            <table class="brand-table">
                <tr>
                    <td class="brand-logo-cell">
                        @if ($brandLogoData)
                            <img class="brand-logo" src="{{ $brandLogoData }}" alt="{{ config('app.name') }} logo">
                        @else
                            <span class="brand-logo-fallback">{{ config('app.name') }}</span>
                        @endif
                    </td>
                    <td class="brand-details">Official Loan Documentation</td>
                </tr>
            </table>

            <div class="document-label">
                <div class="eyebrow">Loan Services / Supporting Documents</div>
                <h1>Guarantor Information Form</h1>
                <p>Guarantor details submitted in connection with a loan application.</p>
            </div>

            <div class="reference">
                <span class="reference-label">Loan Application Reference</span>
                <div class="reference-value">{{ $loan->id }}</div>
            </div>
        </header>

        <div class="document-body">
            <section class="section">
                <div class="section-heading">
                    <table><tr><td class="section-number">01</td><td class="section-title">Loan Applicant Details</td></tr></table>
                </div>
                <p class="section-description">Details identifying the applicant associated with this request.</p>
            </section>

            <table class="details-table">
                <tr><td>Applicant full name</td><td class="handwritten">{{ $applicantName }}</td></tr>
                <tr><td>Loan type</td><td class="regular-value">{{ $loan->loan_type?->label() ?? 'Loan' }}</td></tr>
                <tr><td>Loan amount requested</td><td class="money-value">{{ money((float) $loan->amount) }}</td></tr>
                <tr><td>Application ID</td><td class="regular-value">{{ $loan->id }}</td></tr>
            </table>

            <table class="application-strip">
                <tr>
                    <td><span class="strip-label">Guarantor form fee</span><div class="strip-value">NGN {{ number_format((float) $loan->guarantor_form_fee_amount, 2) }}</div></td>
                    <td><span class="strip-label">Submission date</span><div class="strip-value">{{ $loan->guarantor_form_submitted_at?->format('j M Y') ?? '—' }}</div></td>
                </tr>
            </table>

            <section class="section">
                <div class="section-heading">
                    <table><tr><td class="section-number">02</td><td class="section-title">Guarantor Personal Information</td></tr></table>
                </div>
                <p class="section-description">Personal and contact details provided for the named guarantor.</p>
            </section>

            <table class="details-table">
                <tr><td>Full name</td><td class="handwritten">{{ $form['full_name'] ?? '—' }}</td></tr>
                <tr><td>Relationship to applicant</td><td class="handwritten compact">{{ $form['relationship'] ?? '—' }}</td></tr>
                <tr><td>Phone number</td><td class="handwritten compact">{{ $form['phone'] ?? '—' }}</td></tr>
                @if (filled($form['email'] ?? null))
                    <tr><td>Email address</td><td class="handwritten compact">{{ $form['email'] }}</td></tr>
                @endif
                <tr><td>Residential address</td><td class="handwritten compact">{{ $form['residential_address'] ?? '—' }}</td></tr>
                <tr><td>Guarantor consent</td><td class="regular-value">{{ ($form['consent'] ?? false) ? 'Confirmed by applicant' : 'Not recorded' }}</td></tr>
            </table>

            <section class="section">
                <div class="section-heading">
                    <table><tr><td class="section-number">03</td><td class="section-title">Submission &amp; Fee Verification</td></tr></table>
                </div>
            </section>

            <table class="details-table">
                <tr><td>Form fee payment status</td><td class="regular-value">Verified</td></tr>
                <tr><td>Amount paid</td><td class="money-value">NGN {{ number_format((float) $loan->guarantor_form_fee_amount, 2) }}</td></tr>
                <tr><td>Date and time submitted</td><td class="regular-value">{{ $loan->guarantor_form_submitted_at?->format('j M Y, g:i A') ?? '—' }}</td></tr>
                <tr><td>Payment reference</td><td class="regular-value">{{ $loan->guarantor_form_fee_reference ?? '—' }}</td></tr>
            </table>

            <div class="electronic-signature">
                <div class="signature-label">Guarantor Electronic Signature — Applicant Submitted</div>
                <div class="signature-name">{{ $form['signature'] ?? '—' }}</div>
                <div class="signature-note">
                    Typed signature provided by the applicant as the guarantor’s electronic signature. Consent was confirmed at submission. This display is not, by itself, proof of a handwritten signature.
                </div>
            </div>

            <section class="signature-footer">
                <div class="signature-footer-heading">Authorisation &amp; Signature Record</div>
                <table class="signature-table">
                    <tr>
                        <td>
                            <div class="signature-box">
                                <div class="signature-role">For Management</div>
                                <table class="signature-image-area">
                                    <tr><td>
                                        @if ($managementSignatureData)
                                            <img class="management-signature-image" src="{{ $managementSignatureData }}" alt="Management signature">
                                        @else
                                            <span class="signature-placeholder">Authorised management signature</span>
                                        @endif
                                    </td></tr>
                                </table>
                                <div class="signature-person">Authorised Management</div>
                                <div class="signature-position">Company Representative</div>
                                <div class="signature-date">Date: ___<u>{{ $loan->created_at?->format('jS M Y') ?? '—' }}</u>___</div>
                            </div>
                        </td>
                        <td>
                            <div class="signature-box">
                                <div class="signature-role">Guarantor / Submitted Signature</div>
                                <table class="signature-image-area">
                                    <tr><td><span class="user-signature-text">{{ $form['signature'] ?? '' }}</span></td></tr>
                                </table>
                                <div class="signature-person">{{ $guarantorName }}</div>
                                <div class="signature-position">Electronic signature submitted by applicant</div>
                                <div class="signature-date">Submitted: {{ $loan->guarantor_form_submitted_at?->format('j M Y') ?? '—' }}</div>
                            </div>
                        </td>
                    </tr>
                </table>
            </section>

            <footer class="document-footer">
                <div class="footer-brand">{{ config('app.name') }}</div>
                This document contains personal and financial application information. It must be handled confidentially and shared only with authorised parties involved in the loan application.
                <div class="footer-reference">Application: {{ $loan->id }} &nbsp;|&nbsp; Generated: {{ now()->format('j M Y, g:i A') }}</div>
            </footer>
        </div>
    </main>
</body>
</html>
