<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['nullable', 'string', 'max:50'],
            'phone' => $this->phoneRules(),
            'telegram' => ['nullable', 'string', 'max:120'],
            'biography' => ['nullable', 'string', 'max:300'],
            // Display currency code (e.g. NGN) or null/"USD" for USD — mirrors
            // the web currency switcher. USD is the base currency and has no
            // row in the currencies table, so it is accepted explicitly.
            'display_currency' => ['nullable', 'string', 'max:3', \Illuminate\Validation\Rule::in(array_merge(['USD'], \App\Models\Currency::active()->pluck('code')->all()))],
        ];
    }

    /** @return array<int|string, mixed> */
    private function phoneRules(): array
    {
        // Enforce uniqueness only when a phone is provided (mirrors the web form).
        $rules = ['nullable', 'string', 'max:20'];

        if (trim((string) $this->input('phone')) !== '') {
            $rules[] = \Illuminate\Validation\Rule::unique('users', 'phone')->ignore($this->user()->id);
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.unique' => 'That phone number is already linked to another account.',
        ];
    }
}
