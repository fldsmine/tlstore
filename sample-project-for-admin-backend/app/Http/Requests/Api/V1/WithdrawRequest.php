<?php

namespace App\Http\Requests\Api\V1;

use App\Services\WithdrawalService;
use Illuminate\Foundation\Http\FormRequest;

class WithdrawRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // The authorization code is only mandatory for N-Cloud wallets while the
        // feature is enabled (Backoffice → Settings → Features).
        $requiresCode = app(WithdrawalService::class)->walletRequiresAuthorizationCode((string) $this->input('wallet'));

        return [
            'wallet' => ['required', 'string', \Illuminate\Validation\Rule::in(array_keys(\App\Models\User::WALLET_COLUMNS))],
            'amount' => ['required', 'numeric', 'gt:0'],
            'pin' => ['required', 'digits:4'],
            'withdrawal_code' => [$requiresCode ? 'required' : 'nullable', 'string', 'min:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'withdrawal_code.required' => 'A withdrawal authorization code is required for N-Cloud withdrawals.',
            'withdrawal_code.min' => 'The withdrawal authorization code must be at least 6 characters.',
        ];
    }
}
