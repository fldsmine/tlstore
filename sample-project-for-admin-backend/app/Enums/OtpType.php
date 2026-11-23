<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum OtpType: string
{
    case EMAIL_VERIFICATION = 'email_verification';
    case PASSWORD_RESET = 'password_reset';
    case PHONE_VERIFICATION = 'phone_verification';

    public function getName(): string
    {
        return Str::headline($this->value);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
