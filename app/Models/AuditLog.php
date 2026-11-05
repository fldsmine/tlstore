<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use TrustLensMorphMap;

    protected $table = 'audit_logs';
    public $timestamps = false;
    protected $fillable = [
        'audit_key', 'actor_user_id', 'actor_admin_id', 'action', 'entity_type', 'entity_id', 'before_values',
        'after_values', 'request_id', 'ip_hash', 'created_at',
    ];
    protected $casts = [
        'before_values' => 'array',
        'after_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function adminActor(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'actor_admin_id');
    }

    public function entity(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'entity_type', 'entity_id');
    }
}
