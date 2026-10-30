<?php

namespace App\Models;

use App\Casts\AsHtmlEntity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'value' => AsHtmlEntity::class,
        ];
    }
}
