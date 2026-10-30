<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class RedeemMinutesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'string', 'exists:additional_minute_plans,id'],
            'token' => ['required', 'string', 'min:6', 'max:32'],
        ];
    }
}
