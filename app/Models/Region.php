<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    protected $table = 'regions';
    protected $fillable = ['code', 'name', 'is_available'];
    protected $casts = ['is_available' => 'boolean'];

    public function countryMemberships(): HasMany
    {
        return $this->hasMany(RegionCountry::class);
    }

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'region_countries', 'region_id', 'country_code', 'id', 'code')
            ->withTimestamps();
    }
}
