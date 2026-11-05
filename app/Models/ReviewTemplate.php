<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewTemplate extends Model
{
    protected $table = 'review_templates';
    protected $fillable = ['template_key', 'name', 'description', 'status'];

    public function versions(): HasMany
    {
        return $this->hasMany(ReviewTemplateVersion::class);
    }
}
