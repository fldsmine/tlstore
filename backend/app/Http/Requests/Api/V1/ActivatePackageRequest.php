<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ActivatePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'package_id' => ['required', 'string', 'exists:packages,id'],
            'token' => ['required', 'string', 'min:6', 'max:32'],
        ];
    }
}
