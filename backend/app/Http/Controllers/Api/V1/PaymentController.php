<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BillTopUpRequest;
use App\Models\Transaction;
use App\Services\CurrencyService;
use App\Services\Payments\BillTopUpService;
use App\Services\Payments\ChargeSettlement;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Start a card top-up of the bills (VTU) wallet. The mobile app opens the
     * returned checkout_url in a browser/webview; settlement happens via the
     * provider webhook or the finalize endpoint below.
     */
    public function topUp(BillTopUpRequest $request, BillTopUpService $topUps, CurrencyService $currency): JsonResponse
    {
        $amountNgn = (float) $request->validated('amount');

        $result = $topUps->start($request->user(), $amountNgn);

        if (! $result['ok']) {
            return ApiResponse::error($result['error'] ?? 'Could not start the top-up.', 422);
        }

        return ApiResponse::success('Top-up started. Complete the payment in the checkout.', [
            'checkout_url' => $result['checkout_url'],
            'reference' => $result['reference'],
            'amount_ngn' => $amountNgn,
            'amount_usd' => round($currency->toUsd($amountNgn, 'NG'), 6),
        ]);
    }

    /**
     * Server-side settlement of a gateway charge by OUR reference (the same
     * path the redirect callback + webhook router use). Never trusts client
     * amounts — the gateway response is re-verified and reconciled.
     */
    public function finalize(Request $request, string $reference, ChargeSettlement $settlement): JsonResponse
    {
        $status = $settlement->finalize($reference);
        $isLoanFee = Transaction::query()
            ->where('reference', $reference)
            ->where('type', TransactionType::LOAN_APPLICATION_FEE)
            ->exists();
        $isGuarantorFormFee = Transaction::query()
            ->where('reference', $reference)
            ->where('type', TransactionType::LOAN_GUARANTOR_FORM_FEE)
            ->exists();

        $message = match (true) {
            $isGuarantorFormFee => match ($status) {
                'completed' => 'Payment confirmed — the guarantor form is unlocked.',
                'pending', 'unavailable' => 'Guarantor-form payment is still being verified.',
                default => 'That guarantor-form payment did not go through.',
            },
            $isLoanFee => match ($status) {
                'completed' => 'Fee payment confirmed — your loan application is under review.',
                'pending', 'unavailable' => 'Fee payment received — your loan application will be submitted after verification.',
                default => 'That fee payment did not go through. No loan application was created.',
            },
            default => match ($status) {
                'completed' => 'Payment confirmed — your wallet has been credited.',
                'pending', 'unavailable' => 'Payment received — your wallet will be credited shortly.',
                default => 'That payment did not go through. You were not charged.',
            },
        };

        return ApiResponse::success($message, ['status' => $status]);
    }
}
