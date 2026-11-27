<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $fillable = ['setting_key', 'value', 'is_public', 'updated_by_admin_id'];

    protected $casts = ['value' => 'array', 'is_public' => 'boolean'];

    public static function get($key, $default = null)
    {
        $setting = config("settings.$key");
        return $setting ?? $default;
    }

    public static function set($key, $value, $isPublic = false, $updatedBy = null)
    {
        return self::updateOrCreate(
            ['setting_key' => $key],
            ['value' => $value, 'is_public' => $isPublic, 'updated_by_admin_id' => $updatedBy]
        );
    }

    protected static function boot()
    {
        parent::boot();

        // Any write invalidates the helper's cache. Failures are swallowed: a
        // `database` cache store without its `cache` table must not stop a
        // setting from being saved.
        $forget = static function (): void {
            try {
                Cache::forget('GeneralSetting');
            } catch (\Throwable) {
                // Nothing to invalidate if the cache backend is unavailable.
            }
        };

        static::saved($forget);
        static::deleted($forget);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }
}
