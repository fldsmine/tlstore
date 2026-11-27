<?php

namespace App\Features\Admin\Http\Controllers\Api\V1;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

final class SystemSettingController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => SystemSetting::query()
            ->where('setting_key', 'not like', '%secret%')
            ->where('setting_key', 'not like', '%credential%')
            ->where('setting_key', 'not like', '%private_key%')
            ->orderBy('setting_key')->get()]);
    }

    public function update(Request $request, string $settingKey): JsonResponse
    {
        $data = $request->validate([
            'value' => ['present'],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        if ($settingKey === 'account_validation.provider') {
            $provider = is_array($data['value']) ? ($data['value']['provider'] ?? null) : $data['value'];
            if (! is_string($provider) || ! in_array(strtolower($provider), ['paystack', 'flutterwave', 'korapay', 'bachs', 'fincra'], true)) {
                return response()->json(['message' => 'Account validation provider must be Paystack, Flutterwave, Korapay, Bachs, or Fincra.'], 422);
            }
            $data['value'] = ['provider' => strtolower($provider)];
        }

        if ($settingKey === 'campaign.services') {
            $services = $data['value'];
            $platforms = ['tiktok', 'youtube', 'twitter', 'instagram', 'facebook', 'whatsapp', 'telegram'];
            $actions = [
                'tiktok' => ['followers'], 'youtube' => ['subscribers'], 'twitter' => ['follower'],
                'instagram' => ['followers', 'likes'], 'facebook' => ['page_like', 'post_share'],
                'whatsapp' => ['channel'], 'telegram' => ['join'],
            ];
            if (! is_array($services) || array_is_list($services) || array_diff(array_keys($services), $platforms) !== []) {
                return response()->json(['message' => 'Campaign services must map supported platform names to their action rates and minimum quantity.'], 422);
            }
            foreach ($services as $platform => $definition) {
                if (! is_array($definition) || array_is_list($definition) || ! isset($definition['min']) || filter_var($definition['min'], FILTER_VALIDATE_INT) === false || (int) $definition['min'] < 1) {
                    return response()->json(['message' => "{$platform} needs a positive integer min quantity."], 422);
                }
                if (array_diff(array_keys($definition), [...$actions[$platform], 'min']) !== []) {
                    return response()->json(['message' => "{$platform} contains an unsupported service action."], 422);
                }
                foreach ($actions[$platform] as $action) {
                    if (array_key_exists($action, $definition) && (! is_numeric($definition[$action]) || (float) $definition[$action] <= 0)) {
                        return response()->json(['message' => "{$platform}.{$action} must be a positive amount."], 422);
                    }
                }
                $data['value'][$platform] = array_merge(
                    array_intersect_key($definition, array_flip($actions[$platform])),
                    ['min' => (int) $definition['min']],
                );
            }
        }

        if ($settingKey === 'campaign.service_fee_percent') {
            if (! is_numeric($data['value']) || (float) $data['value'] < 0 || (float) $data['value'] > 100) {
                return response()->json(['message' => 'Campaign service fee must be a number from 0 to 100.'], 422);
            }
            $data['value'] = (float) $data['value'];
        }

        if ($settingKey === 'campaign.currency') {
            if (! is_string($data['value']) || preg_match('/^[A-Za-z]{3}$/', $data['value']) !== 1) {
                return response()->json(['message' => 'Campaign currency must be a three-letter currency code.'], 422);
            }
            $data['value'] = strtoupper($data['value']);
        }

        if ($settingKey === 'payment.active_gateways') {
            $gateways = $data['value'];
            $allowed = ['korapay', 'paystack', 'flutterwave', 'bachs', 'fincra', 'manual'];
            if (! is_array($gateways) || ($gateways !== [] && array_is_list($gateways)) || array_diff(array_keys($gateways), $allowed) !== []) {
                return response()->json(['message' => 'Active gateways must map gateway names to supported currency codes.'], 422);
            }
            foreach ($gateways as $gateway => $currencies) {
                if (! is_array($currencies) || ! array_is_list($currencies)) {
                    return response()->json(['message' => "Supported currencies for {$gateway} must be a list."], 422);
                }
                if ($gateway !== 'manual' && $currencies === []) {
                    return response()->json(['message' => "At least one supported currency is required for {$gateway}."], 422);
                }
                foreach ($currencies as $currency) {
                    if (! is_string($currency) || preg_match('/^[A-Za-z]{3}$/', $currency) !== 1) {
                        return response()->json(['message' => "Supported currencies for {$gateway} must be three-letter currency codes."], 422);
                    }
                }
                $data['value'][$gateway] = array_values(array_unique(array_map('strtoupper', $currencies)));
            }
        }

        if ($settingKey === 'payment.active_payment_currency') {
            $options = $data['value'];
            if (! is_array($options) || ($options !== [] && array_is_list($options))) {
                return response()->json(['message' => 'Active payment currencies must map gateway.currency keys to display labels.'], 422);
            }
            $allowed = ['korapay', 'paystack', 'flutterwave', 'bachs', 'fincra'];
            $normalized = [];
            foreach ($options as $key => $label) {
                if (! is_string($key) || preg_match('/^(korapay|korapy|paystack|flutterwave|bachs|fincra)\.([A-Za-z]{3})$/i', $key, $matches) !== 1) {
                    return response()->json(['message' => 'Each payment currency key must use gateway.CURRENCY format.'], 422);
                }
                if (! is_string($label) || trim($label) === '' || mb_strlen($label) > 32) {
                    return response()->json(['message' => 'Each payment currency option needs a display label of 1 to 32 characters.'], 422);
                }
                $gateway = strtolower($matches[1]) === 'korapy' ? 'korapay' : strtolower($matches[1]);
                if (! in_array($gateway, $allowed, true)) {
                    return response()->json(['message' => 'This payment gateway is not supported.'], 422);
                }
                $normalized[$gateway.'.'.strtoupper($matches[2])] = trim($label);
            }
            $data['value'] = $normalized;
        }

        if ($settingKey === 'vas.enabled' && ! is_bool($data['value'])) {
            return response()->json(['message' => 'VAS enabled must be true or false.'], 422);
        }

        if ($settingKey === 'vas.services') {
            $services = $data['value'];
            $allowedServices = ['airtime', 'data', 'tv', 'electricity', 'education'];
            if (! is_array($services) || array_is_list($services) || array_diff(array_keys($services), $allowedServices) !== []) {
                return response()->json(['message' => 'VAS services must map supported service names to enabled flags.'], 422);
            }
            foreach ($services as $service => $enabled) {
                if (! is_bool($enabled)) {
                    return response()->json(['message' => "The {$service} VAS flag must be true or false."], 422);
                }
            }
            $data['value'] = array_merge(array_fill_keys($allowedServices, false), $services);
        }

        if ($settingKey === 'vas.catalog_cache_minutes') {
            if (filter_var($data['value'], FILTER_VALIDATE_INT) === false || (int) $data['value'] < 5 || (int) $data['value'] > 1440) {
                return response()->json(['message' => 'VAS catalog cache duration must be an integer from 5 to 1440 minutes.'], 422);
            }
            $data['value'] = (int) $data['value'];
        }

        $normalizedKey = strtolower($settingKey);
        if (str_contains($normalizedKey, 'secret') || str_contains($normalizedKey, 'credential') || str_contains($normalizedKey, 'private_key')) {
            return response()->json(['message' => 'Provider secrets must be configured in server environment, not stored as editable settings.'], 422);
        }

        $setting = DB::transaction(function () use ($request, $settingKey, $data): SystemSetting {
            $setting = SystemSetting::query()->firstOrNew(['setting_key' => $settingKey]);
            $before = $setting->exists ? ['value' => $setting->value, 'is_public' => $setting->is_public] : null;
            $setting->fill([
                'value' => $data['value'],
                'is_public' => $data['is_public'] ?? $setting->is_public ?? false,
                'updated_by_admin_id' => $request->user()->id,
            ])->save();

            AuditLog::query()->create([
                'actor_admin_id' => $request->user()->id,
                'action' => 'system_setting.updated',
                'entity_type' => 'system_setting',
                'entity_id' => $settingKey,
                'before_values' => $before,
                'after_values' => ['value' => $setting->value, 'is_public' => $setting->is_public],
                'created_at' => now(),
            ]);

            return $setting;
        });

        Cache::forget('api:frontend:v2');

        return response()->json(['data' => $setting]);
    }
}
