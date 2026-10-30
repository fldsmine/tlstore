<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Services\Payments\LoanGuarantorFormService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LoanGuarantorFormFeeController extends Controller
{
    public function callback(Request $request, LoanGuarantorFormService $fees): RedirectResponse
    {
        $reference = (string) $request->query('reference', '');
        $transaction = $reference !== ''
            ? Transaction::query()
                ->where('reference', $reference)
                ->where('user_id', $request->user()->id)
                ->where('type', TransactionType::LOAN_GUARANTOR_FORM_FEE->value)
                ->first()
            : null;
        $status = $transaction !== null ? $fees->finalize($reference) : 'failed';

        [$message, $type] = match ($status) {
            'completed' => ['Payment verified — your guarantor form is unlocked. Complete it before any disbursement.', 'success'],
            'pending', 'unavailable' => ['Your guarantor-form payment is still being verified. Return here shortly to continue.', 'info'],
            default => ['The guarantor-form payment could not be verified. If it expired or was not made, start a new payment request from your loan page.', 'warning'],
        };

        return redirect()->route('loan')->with('toast', ['type' => $type, 'message' => $message]);
    }
}
