<?php

namespace App\Models;

use App\Enums\LoginChannel;
use App\Support\DeviceInfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single sign-in: who, when, from where and through which surface.
 *
 * Appended by LoginService::touchLastLogin(), which is the one place all three
 * login paths (website, Android app API, Telegram Mini App) funnel through.
 * There is no updated_at — an event is never edited.
 */
class UserLoginEvent extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'channel' => LoginChannel::class,
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** "Android · Chrome" — a readable summary of the stored user agent. */
    public function device(): string
    {
        return DeviceInfo::describe($this->user_agent);
    }

    /** The human label for this sign-in's surface (Website / Mobile app / Telegram Mini App). */
    public function channelLabel(): string
    {
        return $this->channel instanceof LoginChannel
            ? $this->channel->getName()
            : LoginChannel::labelFor(is_string($this->channel) ? $this->channel : null);
    }

    public function channelIcon(): string
    {
        return $this->channel instanceof LoginChannel ? $this->channel->getIcon() : 'ri-login-circle-line';
    }

    public function channelTone(): string
    {
        return $this->channel instanceof LoginChannel ? $this->channel->getTone() : 'muted';
    }
}
