<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualDepositSubmission extends Model
{
    protected $table = 'manual_deposit_submissions';
    protected $fillable = [
        'payment_intent_id', 'user_id', 'amount_minor', 'currency', 'bank_reference',
        'proof_storage_path', 'status', 'reviewed_by', 'review_note', 'submitted_at', 'reviewed_at',
        'reviewed_by_admin_id',
    ];
    protected $casts = [
        'amount_minor' => 'integer',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class, 'payment_intent_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reviewedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }
}
