<?php

namespace App\Enums;

enum MailingRecipientStatus: string
{
    case PENDING = 'pending';
    case QUEUED = 'queued';
    case SENDING = 'sending';
    case RETRYING = 'retrying';
    case SENT = 'sent';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending dispatch',
            self::QUEUED => 'Queued',
            self::SENDING => 'Sending',
            self::RETRYING => 'Retrying',
            self::SENT => 'Sent',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::SENT => 'success',
            self::FAILED => 'danger',
            self::SENDING, self::RETRYING => 'warning',
            self::CANCELLED => 'muted',
            self::PENDING, self::QUEUED => 'info',
        };
    }
}
