<?php

namespace App\Http\Requests\Api\V1;

use App\Services\BankAccountService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Nigerian users: bank_id + 10-digit account number (the account name is
 * resolved server-side via the bank verifier). Foreign users: bank_id +
 * mobile-money number + self-confirmed account name.
 */
class StoreBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $bankRule = app(BankAccountService::class)->countryBankRule($this->user());

        if ($this->user()->isNigerian()) {
            return [
                'bank_id' => ['required', 'string', $bankRule],
                'account_number' => ['required', 'digits:10'],
            ];
        }

        return [
            'bank_id' => ['required', 'string', $bankRule],
            'account_number' => ['required', 'string', 'min:6', 'max:20', 'regex:/^\+?[0-9 ]{6,20}$/'],
            'account_name' => ['required', 'string', 'max:120'],
            'confirmed' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'account_number.required' => 'Enter your mobile-money number.',
            'account_number.regex' => 'Enter a valid mobile-money number.',
            'account_name.required' => 'Enter the account name exactly as registered on the wallet.',
            'confirmed.accepted' => 'Please confirm your mobile-money details are correct.',
        ];
    }
}
