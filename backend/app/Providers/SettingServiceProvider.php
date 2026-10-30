<?php

namespace App\Providers;

use App\Models\Setting;
use App\Support\SettingMapper;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class SettingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        try {
            DB::connection()->getPdo();

            if (Schema::hasTable('settings')) {
                $settings = Cache::remember('settings', 60 * 60 * 24, fn (): array => transform_setting(Setting::all()));

                if ($settings) {
                    config(SettingMapper::toConfigArray($settings));
                }
            }
        } catch (Exception $e) {
            Log::error('Settings load failed: '.$e->getMessage());
        }
    }
}
