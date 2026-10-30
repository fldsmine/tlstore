<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\User;
use App\Support\DownloadLinks;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class LoanGuarantorFormPdfController
{
    /** Render the submitted guarantor form as a web preview with back and PDF actions. */
    public function view(string $loan): View
    {
        [$owner, $application] = $this->submittedLoan($loan);

        return view('pdf.loan-guarantor-form', [
            'loan' => $application,
            'applicant' => $owner,
            'form' => (array) $application->guarantor_form_data,
            'previewMode' => true,
            'pdfDownloadUrl' => DownloadLinks::signed('loan.guarantor.form.download', [
                'loan' => $application->id,
                'download' => 1,
                'u' => $owner->id,
            ]),
        ]);
    }

    /** Download a submitted guarantor form, scoped to the signed-in/link-owning user. */
    public function __invoke(string $loan): Response
    {
        [$owner, $application] = $this->submittedLoan($loan);

        $pdf = Pdf::loadView('pdf.loan-guarantor-form', [
            'loan' => $application,
            'applicant' => $owner,
            'form' => (array) $application->guarantor_form_data,
        ])->setPaper('a4');
        $filename = 'Nextel-Guarantor-Form-'.$application->id.'.pdf';

        return request()->boolean('download', true)
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }

    /** @return array{0: User, 1: Loan} */
    private function submittedLoan(string $loan): array
    {
        $owner = auth()->user() ?? DownloadLinks::signedUser();
        abort_unless($owner instanceof User, 403);

        $application = Loan::query()
            ->where('user_id', $owner->id)
            ->findOrFail($loan);
        abort_unless($application->hasCompletedGuarantorForm(), 404);

        return [$owner, $application];
    }
}
