<?php

namespace App\Enums;

enum WithdrawalStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';

    public function getName(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PROCESSING => 'Processing',
            self::COMPLETED => 'Successful',
            self::REJECTED => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::PROCESSING => 'info',
            self::COMPLETED => 'success',
            self::REJECTED => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PENDING, self::PROCESSING => 'ri-loader-4-line',
            self::COMPLETED => 'ri-checkbox-circle-fill',
            self::REJECTED => 'ri-close-circle-fill',
        };
    }

    public function isInProgress(): bool
    {
        return in_array($this, [self::PENDING, self::PROCESSING], true);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
