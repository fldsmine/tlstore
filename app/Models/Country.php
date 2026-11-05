<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $table = 'countries';
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['code', 'name', 'default_currency', 'is_available'];
    protected $casts = ['is_available' => 'boolean'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'country_code', 'code');
    }

    public function regionMemberships(): HasMany
    {
        return $this->hasMany(RegionCountry::class, 'country_code', 'code');
    }

    public function regions(): BelongsToMany
    {
        return $this->belongsToMany(Region::class, 'region_countries', 'country_code', 'region_id', 'code', 'id')
            ->withTimestamps();
    }
}
