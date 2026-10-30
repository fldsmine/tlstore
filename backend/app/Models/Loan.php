<?php

namespace App\Models;

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Loan extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:6',
            'total_repayment' => 'decimal:6',
            'due_date' => 'date',
            'loan_type' => LoanType::class,
            'agreement_accepted_at' => 'datetime',
            'application_fee_amount' => 'decimal:6',
            'application_fee_percentage' => 'decimal:4',
            'guarantor_form_fee_amount' => 'decimal:6',
            'guarantor_form_fee_paid_at' => 'datetime',
            'guarantor_form_data' => 'array',
            'guarantor_form_submitted_at' => 'datetime',
            'status' => LoanStatus::class,
            'paid_at' => 'datetime',
            'exported_at' => 'datetime',
        ];
    }

    /** The guarantor form is required after approval and before staff can record disbursement. */
    public function requiresGuarantorForm(): bool
    {
        return $this->status === LoanStatus::APPROVED && ! $this->hasCompletedGuarantorForm();
    }

    public function hasCompletedGuarantorForm(): bool
    {
        return $this->guarantor_form_fee_paid_at !== null
            && $this->guarantor_form_submitted_at !== null
            && is_array($this->guarantor_form_data)
            && $this->guarantor_form_data !== [];
    }

    /** Hide the internal approval until the user has completed the paid guarantor form. */
    public function userFacingStatusValue(): string
    {
        if ($this->requiresGuarantorForm()) {
            return 'guarantor_form_required';
        }

        return $this->status?->value ?? LoanStatus::PROCESSING->value;
    }

    public function userFacingStatusLabel(): string
    {
        if ($this->requiresGuarantorForm() && $this->isGuarantor()) {
            return 'Guarantor form required';
        }

        return $this->status?->label() ?? LoanStatus::PROCESSING->label();
    }

    public function isGuarantor(): bool
    {
        return (bool) config('settings.loan.guarantor_form_enabled', false);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function applicationFeeCoupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'application_fee_coupon_id');
    }

    public function applicationFeeTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'application_fee_reference', 'reference');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'paid_by');
    }
}
