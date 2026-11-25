<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        static::saved(function(){
            \Cache::forget('GeneralSetting');
        });
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by_admin_id');
    }
}
