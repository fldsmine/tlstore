<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    protected $fillable = [
        'act', 'name', 'subject', 'email_body', 'sms_body', 'shortcodes',
        'email_status', 'sms_status', 'firebase_status', 'firebase_body',
    ];

    protected $casts = [
        'shortcodes' => 'array',
        'email_status' => 'boolean',
        'sms_status' => 'boolean',
        'firebase_status' => 'boolean',
    ];
}
