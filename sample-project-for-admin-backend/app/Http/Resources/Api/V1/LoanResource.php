<?php

namespace App\Http\Resources\Api\V1;

use App\Support\DownloadLinks;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Loan */
class LoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loan_type' => $this->loan_type?->value,
            'loan_type_label' => $this->loan_type?->label(),
            'amount_usd' => (float) $this->amount,
            'total_repayment_usd' => (float) $this->total_repayment,
            'due_date' => $this->due_date->toDateString(),
            'agreement_accepted_at' => $this->agreement_accepted_at?->toIso8601String(),
            'application_fee' => $this->application_fee_amount === null ? null : [
                'amount' => (float) $this->application_fee_amount,
                'currency' => $this->application_fee_currency,
                'type' => $this->application_fee_type ?? 'fixed',
                'percentage' => $this->application_fee_percentage === null ? null : (float) $this->application_fee_percentage,
                'non_refundable' => true,
                'reference' => $this->application_fee_reference,
            ],
            'disbursement_account' => [
                'account_name' => $this->disbursement_account_name,
                'account_number' => $this->disbursement_account_number,
                'bank_name' => $this->disbursement_bank_name,
            ],
            'status' => $this->userFacingStatusValue(),
            'status_label' => $this->userFacingStatusLabel(),
            'guarantor_form' => [
                'required' => $this->requiresGuarantorForm(),
                'fee_paid' => $this->guarantor_form_fee_paid_at !== null,
                'fee_amount_ngn' => $this->guarantor_form_fee_paid_at !== null
                    ? (float) $this->guarantor_form_fee_amount
                    : (float) config('settings.loan.guarantor_form_fee_ngn', 1500),
                'fee_currency' => 'NGN',
                'fee_paid_at' => $this->guarantor_form_fee_paid_at?->toIso8601String(),
                'form_submitted_at' => $this->guarantor_form_submitted_at?->toIso8601String(),
                'pending_payment' => $this->pendingGuarantorFormPayment(),
                'view_url' => $this->hasCompletedGuarantorForm()
                    ? DownloadLinks::signed('loan.guarantor.form.view', [
                        'loan' => $this->id,
                        'u' => $request->user()?->id,
                    ])
                    : null,
                'download_url' => $this->hasCompletedGuarantorForm()
                    ? DownloadLinks::signed('loan.guarantor.form.download', [
                        'loan' => $this->id,
                        'download' => 1,
                        'u' => $request->user()?->id,
                    ])
                    : null,
            ],
            'paid_at' => $this->paid_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'receipt_url' => DownloadLinks::signed('loan.receipt', [
                'loan' => $this->id,
                'download' => 1,
                'u' => $request->user()?->id,
            ]),
            'receipt_png_url' => DownloadLinks::signed('loan.receipt', [
                'loan' => $this->id,
                'download' => 1,
                'format' => 'png',
                'u' => $request->user()?->id,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** @return array{reference: string, checkout_url: ?string}|null */
    protected function pendingGuarantorFormPayment(): ?array
    {
        $payment = $this->resource->getAttribute('pending_guarantor_form_payment');
        if ($payment === null) {
            return null;
        }

        return [
            'reference' => (string) $payment->reference,
            'checkout_url' => filled($payment->meta['checkout_url'] ?? null)
                ? (string) $payment->meta['checkout_url']
                : null,
        ];
    }
}
