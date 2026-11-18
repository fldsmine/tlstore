<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WithdrawalOption extends Model
{
    protected $table = 'withdrawal_options';
    protected $fillable = ['option_id', 'name', 'icon', 'description', 'fields', 'currency', 'tax', 'min_withdrawal', 'is_available', 'order'];
    protected $casts = [
        'fields' => 'array',
        'tax' => 'decimal:2',
        'min_withdrawal' => 'decimal:2',
        'is_available' => 'boolean',
        'order' => 'integer',
    ];

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class);
    }
}
