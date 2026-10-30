<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Where a sign-in came from. The three entry points that call
 * LoginService::touchLastLogin() each stamp one of these, so the backoffice can
 * report not just that someone signed in, but through which surface.
 */
enum LoginChannel: string
{
    case WEB = 'web';               // the website (Livewire login page)

    case APP = 'app';               // the Android app's API login

    case TELEGRAM = 'telegram';     // the Telegram Mini App's initData exchange

    public function getName(): string
    {
        return match ($this) {
            self::WEB => 'Website',
            self::APP => 'Mobile app',
            self::TELEGRAM => 'Telegram Mini App',
        };
    }

    /** Icon shown next to the channel in the backoffice (a Remix Icon class). */
    public function getIcon(): string
    {
        return match ($this) {
            self::WEB => 'ri-global-line',
            self::APP => 'ri-smartphone-line',
            self::TELEGRAM => 'ri-telegram-line',
        };
    }

    /** Badge tone for the channel pill. */
    public function getTone(): string
    {
        return match ($this) {
            self::WEB => 'info',
            self::APP => 'purple',
            self::TELEGRAM => 'blue',
        };
    }

    public function label(): string
    {
        return $this->getName();
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Resolve a stored value to a case, or null when it is unknown/legacy. */
    public static function parse(?string $value): ?self
    {
        return $value === null ? null : self::tryFrom(Str::lower(trim($value)));
    }

    /** Human label for a raw stored value, falling back to the raw value itself. */
    public static function labelFor(?string $value): string
    {
        return self::parse($value)?->getName() ?? Str::headline((string) $value);
    }
}
