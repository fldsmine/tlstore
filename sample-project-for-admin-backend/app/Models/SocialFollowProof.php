<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialFollowProof extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'user_social_follow_proofs';

    protected $fillable = [
        'user_id',
        'social_handle_id',
        'path',
        'original_name',
        'target_url',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function socialHandle(): BelongsTo
    {
        return $this->belongsTo(SocialHandle::class);
    }
}
