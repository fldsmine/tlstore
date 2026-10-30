<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class VtuVerifyCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category' => ['required', 'string', \Illuminate\Validation\Rule::in(array_keys(\App\Services\Vas\VtuCatalog::categories()))],
            'service_id' => ['required', 'string', 'max:60'],
            'recipient' => ['required', 'string', 'max:60'],
            'meter_type' => ['nullable', 'string', \Illuminate\Validation\Rule::in(['prepaid', 'postpaid'])],
        ];
    }
}
