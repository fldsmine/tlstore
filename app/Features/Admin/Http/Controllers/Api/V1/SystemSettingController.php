<?php

namespace App\Features\Admin\Http\Controllers\Api\V1;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        if ($settingKey === 'payment.active_gateways') {
            $gateways = $data['value'];
            $allowed = ['korapay', 'paystack', 'flutterwave', 'bachs', 'manual'];
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
            $allowed = ['korapay', 'paystack', 'flutterwave', 'bachs'];
            $normalized = [];
            foreach ($options as $key => $label) {
                if (! is_string($key) || preg_match('/^(korapay|korapy|paystack|flutterwave|bachs)\.([A-Za-z]{3})$/i', $key, $matches) !== 1) {
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

        return response()->json(['data' => $setting]);
    }
}
