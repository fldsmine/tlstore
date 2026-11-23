<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class AsHtmlEntity implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {

        return $value === null ? null : html_entity_decode((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : htmlentities((string) $value);
    }
}
