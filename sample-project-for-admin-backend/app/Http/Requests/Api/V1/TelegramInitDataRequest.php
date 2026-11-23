<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class TelegramInitDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Raw WebApp.initData string (urlencoded query). Content is only
            // trusted after server-side HMAC validation in TelegramAuthService.
            'init_data' => ['required', 'string', 'max:8192'],
        ];
    }
}
