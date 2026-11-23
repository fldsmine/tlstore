<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Services\Payments\LoanApplicationFeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LoanApplicationFeeController extends Controller
{
    public function callback(Request $request, LoanApplicationFeeService $fees): RedirectResponse
    {
        $reference = (string) $request->query('reference', '');
        $transaction = $reference !== ''
            ? Transaction::query()
                ->where('reference', $reference)
                ->where('user_id', $request->user()->id)
                ->where('type', TransactionType::LOAN_APPLICATION_FEE->value)
                ->first()
            : null;
        $status = $transaction !== null ? $fees->finalize($reference) : 'failed';
        $transaction?->refresh();

        if ($status === 'completed' && filled($transaction?->meta['loan_id'] ?? null)) {
            session()->flash('loan_application_id', (string) $transaction->meta['loan_id']);
        }

        [$message, $type] = match ($status) {
            'completed' => ['Payment verified — your loan application has been submitted for review.', 'success'],
            'pending', 'unavailable' => ['Your application fee is still being verified. The loan will be submitted only after confirmation.', 'info'],
            default => ['The application fee could not be verified. No loan application was submitted.', 'error'],
        };

        return redirect()->route('loan')->with('toast', ['type' => $type, 'message' => $message]);
    }
}
