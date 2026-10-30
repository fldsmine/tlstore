<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportDailyStat extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'day' => 'date',
            'cnt' => 'integer',
            'amount' => 'decimal:6',
        ];
    }
}
