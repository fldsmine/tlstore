<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalDocument extends Model
{
    protected $fillable = [
        'slug', 'title', 'eyebrow', 'subtitle', 'hero_text', 'sections',
        'version', 'last_updated', 'is_published',
    ];

    protected $casts = [
        'sections' => 'array',
        'last_updated' => 'date',
        'is_published' => 'boolean',
    ];
}
