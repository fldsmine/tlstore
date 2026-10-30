<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class MintTokensRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'plan_key' => ['required', 'string', 'max:80'],
            'quantity' => ['required', 'integer', 'min:1', 'max:500'],
        ];
    }
}
