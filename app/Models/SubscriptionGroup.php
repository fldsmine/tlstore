<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionGroup extends Model
{
    protected $fillable = ['group_key', 'name', 'description', 'sort_order', 'is_active'];

    protected $casts = ['sort_order' => 'integer', 'is_active' => 'boolean'];

    public function plans(): HasMany
    {
        return $this->hasMany(SubscriptionPlan::class)->orderBy('price_minor');
    }
}
