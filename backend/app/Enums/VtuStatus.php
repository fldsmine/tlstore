<?php

namespace App\Enums;

enum VtuStatus: string
{
    case PENDING = 'pending';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';
    case REVERSED = 'reversed';

    public function getName(): string
    {
        return match ($this) {
            self::PENDING => 'Processing',
            self::DELIVERED => 'Successful',
            self::FAILED => 'Failed',
            self::REVERSED => 'Reversed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::DELIVERED => 'success',
            self::FAILED => 'danger',
            self::REVERSED => 'info',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PENDING => 'ri-loader-4-line',
            self::DELIVERED => 'ri-checkbox-circle-fill',
            self::FAILED => 'ri-close-circle-fill',
            self::REVERSED => 'ri-arrow-go-back-line',
        };
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isRefunded(): bool
    {
        return in_array($this, [self::FAILED, self::REVERSED], true);
    }

    public static function fromVtpass(string $code, ?string $transactionStatus): self
    {
        $status = strtolower((string) $transactionStatus);

        return match (true) {
            $code === '040' || $status === 'reversed' => self::REVERSED,
            $code === '016' || in_array($status, ['failed', 'rejected', 'unsuccessful'], true) => self::FAILED,
            $code === '000' && $status === 'delivered' => self::DELIVERED,
            default => self::PENDING,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
