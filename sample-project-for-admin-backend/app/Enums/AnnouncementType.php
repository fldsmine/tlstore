<?php

namespace App\Enums;

enum AnnouncementType: string
{
    case INFO = 'info';
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case DANGER = 'danger';

    public function getName(): string
    {
        return match ($this) {
            self::INFO => 'Update',
            self::SUCCESS => 'Good News',
            self::WARNING => 'Heads Up',
            self::DANGER => 'Important',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::INFO => 'info',
            self::SUCCESS => 'success',
            self::WARNING => 'warning',
            self::DANGER => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::INFO => 'ri-information-fill',
            self::SUCCESS => 'ri-checkbox-circle-fill',
            self::WARNING => 'ri-alert-fill',
            self::DANGER => 'ri-error-warning-fill',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
