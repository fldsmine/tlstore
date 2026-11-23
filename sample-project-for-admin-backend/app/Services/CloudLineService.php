<?php

namespace App\Services;

use App\Models\User;

class CloudLineService
{
    private const AREA_CODE = '698';

    public function ensureLine(User $user): string
    {
        if (filled($user->cloud_line_number)) {
            return $user->cloud_line_number;
        }

        $number = $this->generateUniqueNumber();
        $user->forceFill(['cloud_line_number' => $number])->save();

        return $number;
    }

    private function generateUniqueNumber(): string
    {
        do {

            $subscriber = str_pad((string) random_int(0, 9_999_999), 7, '0', STR_PAD_LEFT);
            $number = '1'.self::AREA_CODE.$subscriber;
        } while (User::where('cloud_line_number', $number)->exists());

        return $number;
    }
}
