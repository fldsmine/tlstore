<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'country' => ['required', 'string', \Illuminate\Validation\Rule::exists('countries', 'code')->where('is_active', true)],
            'referral_code' => ['nullable', 'string', 'exists:users,username'],
            'password' => ['required', 'string', \Illuminate\Validation\Rules\Password::defaults()],
            'agree_terms' => ['accepted'],
            // Present when registering from the Telegram Mini App — enables
            // Telegram code delivery + auto-verification (email stays backup).
            'init_data' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'agree_terms.accepted' => 'Please agree to the Terms & Conditions to continue.',
        ];
    }
}
