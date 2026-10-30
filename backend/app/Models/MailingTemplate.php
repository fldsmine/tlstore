<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailingTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'admin_id',
        'name',
        'subject',
        'html_body',
        'text_body',
        'reply_to',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
