<?php

namespace App\Http\Requests\Api\V1;

use App\Services\Vas\VtuCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VtuPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category' => ['required', 'string', Rule::in(array_keys(VtuCatalog::categories()))],
            'service_id' => ['required', 'string', 'max:60'],
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'variation_code' => ['nullable', 'string', 'max:60'],
            'recipient' => ['required', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'max:20'],
            'meter_type' => ['nullable', 'string', Rule::in(['prepaid', 'postpaid'])],
            'subscription_type' => ['nullable', 'string', Rule::in(['renew', 'new'])],
            // Required for verify-first billers: the name returned by /vas/verify.
            'customer_name' => ['nullable', 'string', 'max:120'],
            'pin' => ['required', 'digits:4'],
        ];
    }
}
