<?php

namespace App\Models;

use App\Enums\NotificationDeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    protected $table = 'notification_deliveries';
    protected $fillable = [
        'notification_id', 'user_device_id', 'channel', 'provider', 'status',
        'provider_message_id', 'failure_reason', 'sent_at',
    ];
    protected $casts = ['status' => NotificationDeliveryStatus::class, 'sent_at' => 'datetime'];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'user_device_id');
    }
}
