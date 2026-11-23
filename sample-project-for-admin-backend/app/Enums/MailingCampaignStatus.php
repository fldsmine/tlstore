<?php

namespace App\Enums;

enum MailingCampaignStatus: string
{
    case DRAFT = 'draft';
    case BUILDING = 'building';
    case SCHEDULED = 'scheduled';
    case QUEUED = 'queued';
    case SENDING = 'sending';
    case COMPLETED = 'completed';
    case COMPLETED_WITH_ERRORS = 'completed_with_errors';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::BUILDING => 'Preparing audience',
            self::SCHEDULED => 'Scheduled',
            self::QUEUED => 'Queued',
            self::SENDING => 'Sending',
            self::COMPLETED => 'Completed',
            self::COMPLETED_WITH_ERRORS => 'Completed with errors',
            self::CANCELLED => 'Cancelled',
            self::FAILED => 'Failed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::COMPLETED => 'success',
            self::COMPLETED_WITH_ERRORS, self::FAILED => 'danger',
            self::BUILDING, self::SCHEDULED, self::QUEUED, self::SENDING => 'warning',
            self::CANCELLED => 'muted',
            self::DRAFT => 'info',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::BUILDING, self::SCHEDULED, self::QUEUED, self::SENDING], true);
    }
}
