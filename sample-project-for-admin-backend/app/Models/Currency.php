<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'usd_exchange_rate' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function countries(): HasMany
    {
        return $this->hasMany(Country::class, 'currency_code', 'code');
    }
}
