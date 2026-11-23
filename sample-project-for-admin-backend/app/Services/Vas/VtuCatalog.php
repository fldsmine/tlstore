<?php

namespace App\Services\Vas;

use App\Models\VtuOrder;

class VtuCatalog
{
    public static function categories(): array
    {
        return [
            VtuOrder::SERVICE_AIRTIME => [
                'label' => 'Airtime', 'icon' => 'ri-smartphone-line', 'blurb' => 'Top up any network instantly.',
                'pricing' => 'amount', 'verify' => false, 'subscription' => false, 'recipient' => 'Phone number',
                'providers' => [
                    ['id' => 'mtn', 'label' => 'MTN', 'logo' => 'mtn.jpg'],
                    ['id' => 'glo', 'label' => 'Glo', 'logo' => 'glo.jpg'],
                    ['id' => 'airtel', 'label' => 'Airtel', 'logo' => 'airtel.jpg'],
                    ['id' => '9mobile', 'label' => '9mobile', 'logo' => '9mobile.png'],
                ],
            ],
            VtuOrder::SERVICE_DATA => [
                'label' => 'Data', 'icon' => 'ri-wifi-line', 'blurb' => 'Buy data bundles for any line.',
                'pricing' => 'variation', 'verify' => false, 'subscription' => false, 'recipient' => 'Phone number',
                'providers' => [
                    ['id' => 'mtn-data', 'label' => 'MTN', 'logo' => 'mtn.jpg'],
                    ['id' => 'glo-data', 'label' => 'Glo', 'logo' => 'glo.jpg'],
                    ['id' => 'airtel-data', 'label' => 'Airtel', 'logo' => 'airtel.jpg'],
                    ['id' => 'etisalat-data', 'label' => '9mobile', 'logo' => '9mobile.png'],
                    ['id' => 'smile-direct', 'label' => 'Smile'],
                    ['id' => 'spectranet', 'label' => 'Spectranet'],
                ],
            ],
            VtuOrder::SERVICE_TV => [
                'label' => 'Cable TV', 'icon' => 'ri-tv-2-line', 'blurb' => 'Renew DStv, GOtv & Startimes.',
                'pricing' => 'variation', 'verify' => true, 'subscription' => true, 'recipient' => 'Smartcard number',
                'providers' => [
                    ['id' => 'dstv', 'label' => 'DStv'],
                    ['id' => 'gotv', 'label' => 'GOtv'],
                    ['id' => 'startimes', 'label' => 'StarTimes'],
                    ['id' => 'showmax', 'label' => 'Showmax'],
                ],
            ],
            VtuOrder::SERVICE_ELECTRICITY => [
                'label' => 'Electricity', 'icon' => 'ri-flashlight-line', 'blurb' => 'Pay prepaid & postpaid bills.',
                'pricing' => 'amount', 'verify' => true, 'subscription' => false, 'recipient' => 'Meter number',
                'providers' => [
                    ['id' => 'ikeja-electric', 'label' => 'Ikeja (IKEDC)'],
                    ['id' => 'eko-electric', 'label' => 'Eko (EKEDC)'],
                    ['id' => 'abuja-electric', 'label' => 'Abuja (AEDC)'],
                    ['id' => 'kano-electric', 'label' => 'Kano (KEDCO)'],
                    ['id' => 'portharcourt-electric', 'label' => 'Port Harcourt (PHED)'],
                    ['id' => 'jos-electric', 'label' => 'Jos (JED)'],
                    ['id' => 'ibadan-electric', 'label' => 'Ibadan (IBEDC)'],
                    ['id' => 'kaduna-electric', 'label' => 'Kaduna (KAEDCO)'],
                    ['id' => 'enugu-electric', 'label' => 'Enugu (EEDC)'],
                    ['id' => 'benin-electric', 'label' => 'Benin (BEDC)'],
                ],
            ],
            VtuOrder::SERVICE_EDUCATION => [
                'label' => 'Education', 'icon' => 'ri-graduation-cap-line', 'blurb' => 'WAEC & JAMB pins.',
                'pricing' => 'variation', 'verify' => false, 'subscription' => false, 'recipient' => 'Phone number',
                'providers' => [
                    ['id' => 'waec', 'label' => 'WAEC Result Checker'],
                    ['id' => 'waec-registration', 'label' => 'WAEC Registration'],
                    ['id' => 'jamb', 'label' => 'JAMB', 'verify' => true, 'recipient' => 'Profile ID'],
                ],
            ],
        ];
    }

    public static function category(string $key): ?array
    {
        return self::categories()[$key] ?? null;
    }

    public static function exists(string $key): bool
    {
        return isset(self::categories()[$key]);
    }

    public static function providers(string $key): array
    {
        return self::category($key)['providers'] ?? [];
    }

    public static function provider(string $key, string $serviceId): ?array
    {
        foreach (self::providers($key) as $provider) {
            if ($provider['id'] === $serviceId) {
                return $provider;
            }
        }

        return null;
    }

    public static function meterTypes(): array
    {
        return [
            ['code' => 'prepaid', 'label' => 'Prepaid'],
            ['code' => 'postpaid', 'label' => 'Postpaid'],
        ];
    }

    public static function variations(string $serviceId): array
    {
        $hours = (int) config('services.vtpass.cache_hours', 6);

        return CatalogCache::remember("vtu:variations:{$serviceId}", $hours, function () use ($serviceId): array {
            $result = app(VtpassClient::class)->variations($serviceId);

            return collect($result['variations'] ?? [])
                ->map(fn (array $v): array => [
                    'code' => (string) ($v['variation_code'] ?? ''),
                    'label' => (string) ($v['name'] ?? ''),
                    'amount' => (float) ($v['variation_amount'] ?? 0),
                ])
                ->filter(fn (array $v): bool => $v['code'] !== '')
                ->values()
                ->all();
        });
    }
}
