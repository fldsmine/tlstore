<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\LoanType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoanApplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'loan_type' => ['required', 'string', Rule::in(LoanType::values())],
            'amount' => ['required', 'numeric', 'gt:0'],
            'due_date' => ['required', 'date', 'after:today'],
            'agreed' => ['accepted'],
            'disbursement_account_name' => ['required', 'string', 'max:120'],
            'disbursement_account_number' => ['required', 'string', 'min:4', 'max:34', 'regex:/^[A-Za-z0-9][A-Za-z0-9 -]{3,33}$/'],
            'disbursement_bank_name' => ['required', 'string', 'max:120'],
            'fee_coupon_code' => ['nullable', 'string', 'max:32'],
        ];
    }
}
