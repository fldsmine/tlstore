<?php

namespace App\Features\Vas\Application;

use App\Features\Vas\Infrastructure\VtpassClient;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

final class VasCatalogService
{
    /** @var array<string,array<string,mixed>> */
    private const CATALOG = [
        'airtime' => [
            'label' => 'Airtime', 'pricing' => 'amount', 'recipient_label' => 'Phone number', 'requires_verification' => false,
            'providers' => [
                ['id' => 'mtn', 'label' => 'MTN'], ['id' => 'glo', 'label' => 'Glo'],
                ['id' => 'airtel', 'label' => 'Airtel'], ['id' => '9mobile', 'label' => '9mobile'],
            ],
        ],
        'data' => [
            'label' => 'Data', 'pricing' => 'plan', 'recipient_label' => 'Phone number', 'requires_verification' => false,
            'providers' => [
                ['id' => 'mtn-data', 'label' => 'MTN'], ['id' => 'glo-data', 'label' => 'Glo'],
                ['id' => 'airtel-data', 'label' => 'Airtel'], ['id' => 'etisalat-data', 'label' => '9mobile'],
                ['id' => 'smile-direct', 'label' => 'Smile'], ['id' => 'spectranet', 'label' => 'Spectranet'],
            ],
        ],
        'tv' => [
            'label' => 'TV', 'pricing' => 'plan', 'recipient_label' => 'Smartcard number', 'requires_verification' => true,
            'subscription_actions' => [['id' => 'renew', 'label' => 'Renew'], ['id' => 'change', 'label' => 'Change plan']],
            'providers' => [
                ['id' => 'dstv', 'label' => 'DStv'], ['id' => 'gotv', 'label' => 'GOtv'],
                ['id' => 'startimes', 'label' => 'StarTimes'], ['id' => 'showmax', 'label' => 'Showmax'],
            ],
        ],
        'electricity' => [
            'label' => 'Electricity', 'pricing' => 'amount', 'recipient_label' => 'Meter number', 'requires_verification' => true,
            'meter_types' => [['id' => 'prepaid', 'label' => 'Prepaid'], ['id' => 'postpaid', 'label' => 'Postpaid']],
            'providers' => [
                ['id' => 'ikeja-electric', 'label' => 'Ikeja (IKEDC)'], ['id' => 'eko-electric', 'label' => 'Eko (EKEDC)'],
                ['id' => 'abuja-electric', 'label' => 'Abuja (AEDC)'], ['id' => 'kano-electric', 'label' => 'Kano (KEDCO)'],
                ['id' => 'portharcourt-electric', 'label' => 'Port Harcourt (PHED)'], ['id' => 'jos-electric', 'label' => 'Jos (JED)'],
                ['id' => 'ibadan-electric', 'label' => 'Ibadan (IBEDC)'], ['id' => 'kaduna-electric', 'label' => 'Kaduna (KAEDCO)'],
                ['id' => 'enugu-electric', 'label' => 'Enugu (EEDC)'], ['id' => 'benin-electric', 'label' => 'Benin (BEDC)'],
            ],
        ],
        'education' => [
            'label' => 'Education', 'pricing' => 'plan', 'recipient_label' => 'Phone number', 'requires_verification' => false,
            'providers' => [
                ['id' => 'waec', 'label' => 'WAEC Result Checker'],
                ['id' => 'waec-registration', 'label' => 'WAEC Registration'],
                ['id' => 'jamb', 'label' => 'JAMB', 'recipient_label' => 'Profile ID', 'requires_verification' => true],
            ],
        ],
    ];

    public function __construct(private readonly VtpassClient $vtpass) {}

    public function enabled(): bool
    {
        return $this->setting('vas.enabled', false) === true;
    }

    /** @return array<string,mixed> */
    public function catalog(): array
    {
        $flags = $this->serviceFlags();
        $services = [];
        foreach (self::CATALOG as $key => $definition) {
            if (($flags[$key] ?? false) !== true) {
                continue;
            }
            $services[] = ['key' => $key, ...$definition];
        }

        return [
            'enabled' => $this->enabled(),
            'provider' => 'vtpass',
            'provider_ready' => $this->vtpass->configured(),
            'currency' => 'NGN',
            'services' => $this->enabled() ? $services : [],
        ];
    }

    /** @return array<string,mixed> */
    public function service(string $key): array
    {
        $this->assertEnabled();
        if (! isset(self::CATALOG[$key]) || ($this->serviceFlags()[$key] ?? false) !== true) {
            throw ValidationException::withMessages(['service' => 'This VAS service is unavailable.']);
        }
        $this->assertConfigured();

        return $this->definition($key);
    }

    /** @return array<string,mixed> */
    public function definition(string $key): array
    {
        if (! isset(self::CATALOG[$key])) {
            throw ValidationException::withMessages(['service' => 'Choose a valid VAS service.']);
        }

        return self::CATALOG[$key];
    }

    /** @return list<array{code:string,name:string,amount_minor:int,fixed_price:bool}> */
    public function plans(string $service, string $serviceId): array
    {
        $definition = $this->service($service);
        if (($definition['pricing'] ?? null) !== 'plan') {
            throw ValidationException::withMessages(['service_id' => 'This service uses an amount instead of a plan.']);
        }
        $this->assertProviderBelongs($definition, $serviceId);

        $cacheKey = 'vas:vtpass:variations:'.hash('sha256', $serviceId);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $result = $this->vtpass->variations($serviceId);
        if (! $result['ok']) {
            throw ValidationException::withMessages(['service_id' => $result['message']]);
        }

        $minutes = max(5, min(1440, (int) $this->setting('vas.catalog_cache_minutes', 360)));
        Cache::put($cacheKey, $result['variations'], now()->addMinutes($minutes));

        return $result['variations'];
    }

    /** @return array{ok:bool,customer_name:?string,message:string} */
    public function verify(string $service, string $serviceId, string $customerId, ?string $type = null): array
    {
        $definition = $this->service($service);
        $provider = $this->assertProviderBelongs($definition, $serviceId);
        $requiresVerification = (bool) ($provider['requires_verification'] ?? $definition['requires_verification'] ?? false);
        if (! $requiresVerification) {
            throw ValidationException::withMessages(['service_id' => 'Recipient verification is not required for this service.']);
        }

        if ($service === 'electricity' && ! in_array($type, ['prepaid', 'postpaid'], true)) {
            throw ValidationException::withMessages(['type' => 'Select prepaid or postpaid meter type before verification.']);
        }
        if ($service === 'education' && $serviceId === 'jamb' && blank($type)) {
            throw ValidationException::withMessages(['type' => 'Select a JAMB item before verification.']);
        }

        // VTpass uses meter type for electricity, variation/type for JAMB, and the smartcard alone for TV.
        $vtpassType = match ($service) {
            'electricity', 'education' => $type,
            default => null,
        };

        return $this->vtpass->verify($serviceId, trim($customerId), $vtpassType);
    }

    /** @param array<string,mixed> $definition @return array<string,mixed> */
    private function assertProviderBelongs(array $definition, string $serviceId): array
    {
        foreach ($definition['providers'] as $provider) {
            if (($provider['id'] ?? null) === $serviceId) {
                return $provider;
            }
        }

        throw ValidationException::withMessages(['service_id' => 'Choose a valid provider for this service.']);
    }

    private function assertEnabled(): void
    {
        abort_unless($this->enabled(), 503, 'Value Added Services are temporarily unavailable.');
    }

    private function assertConfigured(): void
    {
        abort_unless($this->vtpass->configured(), 503, 'VTpass is not configured. Please contact support.');
    }

    /** @return array<string,bool> */
    private function serviceFlags(): array
    {
        $value = $this->setting('vas.services', []);
        $value = is_array($value) ? $value : [];

        return array_merge(array_fill_keys(array_keys(self::CATALOG), true), $value);
    }

    private function setting(string $key, mixed $default): mixed
    {
        return SystemSetting::query()->where('setting_key', $key)->first()?->value ?? $default;
    }
}
