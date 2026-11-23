<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ChangePinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'current_pin' => ['required', 'digits:4'],
            'pin' => ['required', 'digits:4', 'different:current_pin'],
            'pin_confirmation' => ['required', 'same:pin'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'pin.different' => 'Your new PIN must be different from your current PIN.',
            'pin_confirmation.same' => 'The PINs do not match.',
        ];
    }
}
