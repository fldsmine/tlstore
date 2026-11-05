<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRateSnapshot extends Model
{
    protected $table = 'exchange_rate_snapshots';
    protected $fillable = [
        'base_currency', 'quote_currency', 'rate', 'source', 'is_fallback', 'fetched_at', 'source_published_at',
    ];
    protected $casts = [
        'rate' => 'decimal:12',
        'is_fallback' => 'boolean',
        'fetched_at' => 'datetime',
        'source_published_at' => 'datetime',
    ];
}
