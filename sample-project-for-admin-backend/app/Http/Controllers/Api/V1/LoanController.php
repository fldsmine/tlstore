<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoanApplyRequest;
use App\Http\Resources\Api\V1\LoanResource;
use App\Models\Loan;
use App\Services\LoanService;
use App\Services\Payments\LoanApplicationFeeService;
use App\Services\Payments\LoanGuarantorFormService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function index(Request $request, LoanService $loans, LoanApplicationFeeService $fees, LoanGuarantorFormService $guarantorForms): JsonResponse
    {
        $user = $request->user();
        $fee = $loans->feeForUser($user);
        $history = $user->loans()->latest()->take(15)->get();
        $pendingGuarantorPayments = $guarantorForms->pendingPaymentsForLoans($user, $history);
        $history->each(function (Loan $loan) use ($pendingGuarantorPayments): void {
            $loan->setAttribute('pending_guarantor_form_payment', $pendingGuarantorPayments->get((string) $loan->id));
        });

        return ApiResponse::success('Loans retrieved successfully.', [
            'enabled' => $loans->isEnabled(),
            'max_amount_usd' => $loans->maxAmount(),
            'min_amount_usd' => $loans->minAmount(),
            'repayment_days' => $loans->repaymentDays(),
            'service_fee_percent' => $loans->serviceFeePercent(),
            'application_fee' => $fee,
            'application_fee_payment' => $fees->availability(),
            'guarantor_form_payment' => $guarantorForms->availability(),
            'pending_fee_payment' => $fees->openCheckout($user),
            'loan_types' => $loans->availableTypes(),
            'has_processing' => $loans->hasProcessingLoan($user),
            'has_unresolved_application' => $loans->hasUnresolvedApplication($user),
            'loans' => LoanResource::collection($history),
        ]);
    }

    public function store(LoanApplyRequest $request, LoanService $loans, LoanApplicationFeeService $fees): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $application = $loans->prepareApplication(
            $user,
            (string) $data['loan_type'],
            (float) $data['amount'],
            (string) $data['due_date'],
            (bool) $data['agreed'],
            checkUnresolved: false,
            accountName: (string) $data['disbursement_account_name'],
            accountNumber: (string) $data['disbursement_account_number'],
            bankName: (string) $data['disbursement_bank_name'],
        );

        if (filled($data['fee_coupon_code'] ?? null)) {
            $loan = $loans->applyWithCoupon(
                $user,
                $application['loan_type'],
                $application['amount_usd'],
                $application['due_date'],
                true,
                (string) $data['fee_coupon_code'],
                'fee_coupon_code',
                (string) $data['disbursement_account_name'],
                (string) $data['disbursement_account_number'],
                (string) $data['disbursement_bank_name'],
            );

            return ApiResponse::created('Fee coupon accepted — your loan application is now under review.', [
                'loan' => new LoanResource($loan),
            ]);
        }

        $payment = $fees->start(
            $user,
            $application['loan_type'],
            $application['amount_usd'],
            $application['due_date'],
            true,
            $application['disbursement_account_name'],
            $application['disbursement_account_number'],
            $application['disbursement_bank_name'],
        );

        if (! $payment['ok']) {
            return ApiResponse::error($payment['error'] ?? 'Could not start the application-fee payment.', 422);
        }

        return ApiResponse::success('Loan application fee started. The loan is submitted only after payment verification.', [
            'checkout_url' => $payment['checkout_url'],
            'reference' => $payment['reference'],
            'application_fee' => $fees->availability($application['amount_usd']),
        ]);
    }

    public function startGuarantorFormPayment(Request $request, string $loan, LoanGuarantorFormService $forms): JsonResponse
    {
        $payment = $forms->start($request->user(), $loan);
        if (! $payment['ok']) {
            return ApiResponse::error($payment['error'] ?? 'Could not start the guarantor-form payment.', 422);
        }

        return ApiResponse::success('Guarantor-form fee checkout is ready.', [
            'checkout_url' => $payment['checkout_url'],
            'reference' => $payment['reference'],
            'fee_ngn' => $forms->feeNgn(),
            'currency' => 'NGN',
        ]);
    }

    public function refreshGuarantorFormPayment(Request $request, string $loan, string $reference, LoanGuarantorFormService $forms): JsonResponse
    {
        $status = $forms->refreshPendingPayment($request->user(), $loan, $reference);
        $message = match ($status) {
            'completed' => 'Payment verified — the guarantor form is unlocked.',
            'pending' => 'Payment is still pending. Continue checkout or check again shortly.',
            'unavailable' => 'The provider could not confirm payment right now. Try again shortly.',
            default => 'This payment request is no longer pending.',
        };

        return ApiResponse::success($message, ['status' => $status]);
    }

    public function cancelGuarantorFormPayment(Request $request, string $loan, string $reference, LoanGuarantorFormService $forms): JsonResponse
    {
        $status = $forms->cancelPendingPayment($request->user(), $loan, $reference);
        $message = match ($status) {
            'cancelled' => 'Provider confirmed the guarantor-form payment failed or expired. The request was cancelled; you can start a new payment.',
            'completed' => 'Payment verified — the guarantor form is unlocked and the payment was kept.',
            'pending' => 'Payment is still pending with the provider. The request was left unchanged; continue checkout or check again shortly.',
            'unavailable' => 'Payment status could not be verified. The request was left unchanged; try again shortly.',
            'needs_review' => 'This payment needs staff review. It cannot be deleted or replaced yet.',
            default => 'This payment request is no longer pending.',
        };

        return ApiResponse::success($message, ['status' => $status]);
    }

    public function submitGuarantorForm(Request $request, string $loan, LoanGuarantorFormService $forms): JsonResponse
    {
        $application = $forms->submit($request->user(), $loan, $request->all());

        return ApiResponse::success('Guarantor form submitted successfully.', [
            'loan' => new LoanResource($application),
        ]);
    }
}
