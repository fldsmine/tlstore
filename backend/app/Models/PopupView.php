<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one user has seen of one popup.
 *
 * The row's existence is the "already shown" marker a non-recursive popup is filtered on; the
 * timestamps and counters are what the backoffice lists under each popup.
 */
class PopupView extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'shown_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'shown_count' => 'integer',
            'dismissed_count' => 'integer',
        ];
    }

    public function popup(): BelongsTo
    {
        return $this->belongsTo(Popup::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Record that this popup has just been put in front of the user. */
    public static function recordShown(Popup $popup, User $user): void
    {
        $row = static::query()->firstOrCreate(
            ['popup_id' => $popup->id, 'user_id' => $user->id],
            ['shown_count' => 0, 'dismissed_count' => 0],
        );

        $row->increment('shown_count', 1, ['shown_at' => now()]);
    }

    /** Record that the user closed it (or moved past it to the next popup). */
    public static function recordDismissed(Popup $popup, User $user): void
    {
        $row = static::query()
            ->where('popup_id', $popup->id)
            ->where('user_id', $user->id)
            ->first();

        $row?->increment('dismissed_count', 1, ['dismissed_at' => now()]);
    }
}
