<?php

namespace App\Services\Payments;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Services\Payments\DTO\VerifyResult;

/**
 * Routes settlement to the transaction's type, which is authoritative and read from the row.
 * Normal callbacks/webhooks/sweeps use finalize(); the backoffice uses the same domain settlement
 * services with an auditable provider recheck or explicit manual verification context.
 *
 * A reference without a Transactions row belongs to a guest product-page order and is handled by
 * ProductOrderService.
 */
class ChargeSettlement
{
    public function __construct(
        private BillTopUpService $bills,
        private WebsiteRenewalService $renewals,
        private StoragePurchaseService $storage,
        private PackagePurchaseService $packages,
        private ShareAccessPurchaseService $shareAccess,
        private MinuteTopUpPurchaseService $minuteTopUps,
        private SpecialCouponPurchaseService $specialCoupons,
        private LoanApplicationFeeService $loanApplicationFees,
        private LoanGuarantorFormService $loanGuarantorForms,
        private ProductOrderService $productOrders,
    ) {}

    public function finalize(string $reference): string
    {
        $transaction = Transaction::query()->where('reference', $reference)->first();

        if ($transaction === null) {
            return $this->productOrders->settle($reference);
        }

        return $this->serviceFor($transaction)?->finalize($reference) ?? 'failed';
    }

    /**
     * Re-query the provider from the backoffice. This may recover a failed transaction only if the
     * original pinned provider now returns a successful, reconciled payment result.
     *
     * @param  array<string, mixed>  $resolution  Audit context for the administrator action.
     */
    public function reverifyForBackoffice(string $reference, array $resolution): string
    {
        $transaction = Transaction::query()->where('reference', $reference)->first();

        if ($transaction === null) {
            return $this->productOrders->reverifyForBackoffice($reference, $resolution);
        }

        return $this->serviceFor($transaction)?->reverifyForBackoffice($reference, $resolution) ?? 'failed';
    }

    /**
     * Apply an explicitly documented backoffice verification through the original settlement
     * service. No status is changed directly here; all reconciliation and duplicate guards remain
     * in the relevant payment service.
     *
     * @param  array<string, mixed>  $resolution  Audit context for the administrator action.
     */
    public function finalizeManually(string $reference, VerifyResult $result, array $resolution): string
    {
        $transaction = Transaction::query()->where('reference', $reference)->first();

        if ($transaction === null) {
            return $this->productOrders->settleManually($reference, $result, $resolution);
        }

        return $this->serviceFor($transaction)?->finalizeManually($reference, $result, $resolution) ?? 'failed';
    }

    private function serviceFor(Transaction $transaction): object|null
    {
        $type = $transaction->type instanceof TransactionType
            ? $transaction->type
            : TransactionType::tryFrom((string) $transaction->type);

        return match ($type) {
            TransactionType::BILLS_TOPUP => $this->bills,
            TransactionType::WEBSITE_RENEWAL => $this->renewals,
            TransactionType::STORAGE_PURCHASE => $this->storage,
            TransactionType::PACKAGE_PURCHASE => $this->packages,
            TransactionType::SHARE_ACCESS_PURCHASE => $this->shareAccess,
            TransactionType::MINUTES_TOPUP => $this->minuteTopUps,
            TransactionType::SPECIAL_COUPON_PURCHASE => $this->specialCoupons,
            TransactionType::LOAN_APPLICATION_FEE => $this->loanApplicationFees,
            TransactionType::LOAN_GUARANTOR_FORM_FEE => $this->loanGuarantorForms,
            default => null,
        };
    }
}
