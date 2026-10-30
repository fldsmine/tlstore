<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Immutable audit trail for backoffice payment rechecks and manual decisions. */
class PaymentResolutionLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'admin_id', 'transaction_id', 'product_order_id', 'reference', 'provider', 'action',
        'from_status', 'to_status', 'verification_source', 'evidence_reference',
        'gateway_reference', 'amount', 'currency', 'note', 'details', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'details' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function productOrder(): BelongsTo
    {
        return $this->belongsTo(ProductOrder::class, 'product_order_id');
    }
}
