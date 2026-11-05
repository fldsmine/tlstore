<?php

namespace App\Application\Actions\Identity;

use App\Models\Country;
use App\Models\User;

final class UpdateProfile
{
    public function handle(User $user, array $data): User
    {
        if (isset($data['country_code'])) {
            $country = Country::query()->where('code', strtoupper($data['country_code']))->where('is_available', true)->firstOrFail();
            $data['country_code'] = $country->code;
        }

        $user->fill($data)->save();

        return $user->refresh();
    }
}
