<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserCampaignOrder extends Model
{
    protected $fillable = [
        'user_id', 'reviewed_by_admin_id', 'campaign_name', 'platform', 'action', 'quantity',
        'target_url', 'instructions', 'currency', 'unit_price_minor', 'subtotal_minor',
        'service_fee_percent', 'service_fee_minor', 'total_minor', 'debited_amount_minor',
        'debited_currency', 'status', 'admin_note', 'reviewed_at', 'refunded_at', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer', 'unit_price_minor' => 'integer', 'subtotal_minor' => 'integer',
            'service_fee_percent' => 'decimal:2', 'service_fee_minor' => 'integer', 'total_minor' => 'integer',
            'debited_amount_minor' => 'integer', 'reviewed_at' => 'datetime', 'refunded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    public function ad(): HasOne
    {
        return $this->hasOne(Ad::class, 'user_campaign_order_id');
    }
}
