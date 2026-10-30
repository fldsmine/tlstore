<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class VerifyBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'bank_id' => ['required', 'string', 'exists:banks,id'],
            'account_number' => ['required', 'digits:10'],
        ];
    }
}
