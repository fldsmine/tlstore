<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A promotional popup shown over the user dashboard.
 *
 * Managed from the backoffice (Marketing → Popups) and rendered by the dashboard.popup Livewire
 * component. Only `body` is required; every other piece of the card is optional and disappears
 * from the dialog when it is blank — including the media panel, which needs a header image.
 */
class Popup extends Model
{
    use HasFactory, HasUuids;

    /**
     * The brand gradient the backoffice uses as its stand-in for "this popup has no header image".
     *
     * It is deliberately NOT rendered in the dashboard dialog: a popup without a header image
     * shows no media panel at all (see resources/views/components/app/popup-card.blade.php).
     */
    public const PANEL_STYLE = 'background-image: radial-gradient(58% 50% at 50% 44%, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0) 100%), linear-gradient(155deg, #E9F2E7 0%, #D6E7D8 48%, #C2D9C7 100%);';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_recursive' => 'boolean',
        ];
    }

    public function views(): HasMany
    {
        return $this->hasMany(PopupView::class);
    }

    /**
     * The header image as a browser URL.
     *
     * Note the signature: it reads the raw attribute explicitly instead of taking an accessor
     * `$value`. Laravel hands an accessor the value of the attribute *named after the accessor* —
     * here `header_image_url`, which is not a column — so `getHeaderImageUrlAttribute($value)`
     * would silently be handed null and no image would ever render.
     */
    public function getHeaderImageUrlAttribute(): ?string
    {
        return media_url($this->attributes['header_image'] ?? null);
    }

    /** The background image as a browser URL — same accessor rule as above. */
    public function getBackgroundImageUrlAttribute(): ?string
    {
        return media_url($this->attributes['background_image'] ?? null);
    }

    /** Popups the dashboard may show at all. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Narrow to the popups this user has not already used up.
     *
     * A recursive popup comes back on every visit; a one-off is spent the first time it is
     * displayed, which is recorded in popup_views.
     */
    public function scopeUnseenBy(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user): void {
            $q->where('is_recursive', true)
                ->orWhereDoesntHave('views', fn (Builder $v) => $v->where('user_id', $user->id));
        });
    }

    public function hasPrimaryAction(): bool
    {
        return filled($this->primary_label) && filled($this->primary_url);
    }

    public function hasSecondaryAction(): bool
    {
        return filled($this->secondary_label) && filled($this->secondary_url);
    }
}