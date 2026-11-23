<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\User;
use App\Support\DownloadLinks;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class LoanReceiptController
{
    /** Render or download a loan receipt, scoped to the signed-in/link-owning user. */
    public function __invoke(string $loan): Response
    {
        $owner = auth()->user() ?? DownloadLinks::signedUser();
        abort_unless($owner instanceof User, 403);

        $application = Loan::query()
            ->where('user_id', $owner->id)
            ->with('applicationFeeCoupon')
            ->findOrFail($loan);

        $receipt = [
            'loan' => $application,
            'applicant' => $owner,
            'loanType' => $application->loan_type?->label() ?? 'Loan',
            'loanAmount' => money((float) $application->amount),
            'repaymentAmount' => money((float) $application->total_repayment),
            'feeMethod' => $application->application_fee_coupon_id ? 'Vendor coupon' : 'Payment channel',
            'statusValue' => $application->userFacingStatusValue(),
            'statusLabel' => $application->userFacingStatusLabel(),
            'rejectionReason' => $application->rejection_reason ?: config('settings.loan.rejection_reason'),
        ];

        if (request()->query('format') === 'png') {
            return response()->view('pdf.loan-receipt-png', $receipt);
        }

        $pdf = Pdf::loadView('pdf.loan-receipt', $receipt)->setPaper('a4');
        $filename = 'Nextel-Loan-Receipt-'.$application->id.'.pdf';

        return request()->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }
}
