<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPayoutProfile extends Model
{
    protected $fillable = ['user_id', 'withdrawal_option_id', 'details'];
    protected $casts = ['details' => 'array'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function option(): BelongsTo { return $this->belongsTo(WithdrawalOption::class, 'withdrawal_option_id'); }
}
