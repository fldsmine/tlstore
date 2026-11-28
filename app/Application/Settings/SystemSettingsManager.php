<?php

namespace App\Application\Settings;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Reads, validates and writes `system_settings` through the friendly schema in
 * {@see SystemSettingsSchema}. Every write keeps the exact JSON shape the rest
 * of the application already expects (see the notes on each normaliser).
 */
final class SystemSettingsManager
{
    /** @return Collection<string, SystemSetting> */
    public function settings(): Collection
    {
        return SystemSetting::query()->with('updatedBy')->orderBy('setting_key')->get()->keyBy('setting_key');
    }

    /**
     * Tabs in display order, with the number of editable settings each holds.
     *
     * @return array<string, array{label: string, icon: string, description: string, count: int, link: array<string,string>|null}>
     */
    public function tabs(Collection $settings): array
    {
        $tabs = [];
        foreach (SystemSettingsSchema::groups() as $id => $group) {
            $tabs[$id] = [
                'label' => $group['label'],
                'icon' => $group['icon'],
                'description' => $group['description'],
                'count' => count($group['settings']),
                'link' => $group['link'] ?? null,
            ];
        }

        // Credentials live in .env, not in system_settings, but they belong to
        // the same console: insert the tab before the catch-all JSON editor.
        $advanced = $tabs['advanced'] ?? null;
        unset($tabs['advanced']);
        $tabs['environment'] = [
            'label' => 'API keys & secrets',
            'icon' => '🔑',
            'description' => 'Provider credentials, portal IDs and switches written to the deployment .env file.',
            'count' => EnvironmentSettingsSchema::keyCount(),
            'link' => null,
        ];
        if ($advanced !== null) {
            $tabs['advanced'] = $advanced;
            $tabs['advanced']['count'] = count($this->unmanagedKeys($settings));
        }

        return $tabs;
    }

    /**
     * Settings stored in the database that no dedicated editor covers.
     *
     * @return list<string>
     */
    public function unmanagedKeys(Collection $settings): array
    {
        $keys = [];
        foreach ($settings->keys() as $key) {
            $key = (string) $key;
            if (SystemSettingsSchema::isManagedElsewhere($key)) {
                continue;
            }
            if (SystemSettingsSchema::isSensitive($key)) {
                continue;
            }
            $keys[] = $key;
        }

        return $keys;
    }

    /** @return list<string> */
    public function sensitiveKeys(Collection $settings): array
    {
        $keys = [];
        foreach ($settings->keys() as $key) {
            $key = (string) $key;
            if (SystemSettingsSchema::isSensitive($key) && ! SystemSettingsSchema::isManagedElsewhere($key)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Resolved field definitions for one tab, ready for the Blade view.
     *
     * @return list<array<string, mixed>>
     */
    public function fields(string $tab, Collection $settings): array
    {
        $fields = [];

        foreach (SystemSettingsSchema::groups()[$tab]['settings'] ?? [] as $key => $definition) {
            $fields[] = $this->resolveField($key, $definition, $settings);
        }

        if ($tab === 'advanced') {
            foreach ($this->unmanagedKeys($settings) as $key) {
                $fields[] = $this->resolveField($key, [
                    'label' => $key,
                    'section' => 'Unmapped settings',
                    'type' => 'json',
                ], $settings);
            }
        }

        return $fields;
    }

    /**
     * Validation rules for a tab, keyed by the submitted field name.
     *
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    public function rules(array $fields): array
    {
        // Laravel drops unvalidated array keys from validated(); both containers
        // need an explicit rule so the submitted fields survive validation.
        $rules = ['values' => ['array'], 'public' => ['array']];

        foreach ($fields as $field) {
            $name = $field['rule_name'];
            $rules[$name] = match ($field['type']) {
                'toggle' => ['nullable', 'boolean'],
                'integer' => ['nullable', 'integer', 'min:'.$field['min'], 'max:'.$field['max']],
                'decimal' => ['nullable', 'numeric', 'min:'.$field['min'], 'max:'.$field['max']],
                'email' => ['nullable', 'email', 'max:190'],
                'url' => ['nullable', 'string', 'max:500', 'regex:/^(https?:\/\/\S+|\/\S*)?$/i'],
                'select' => ['nullable', 'in:'.implode(',', array_keys($field['options']))],
                // The textarea submits one string; newlines are split on save.
                'lines' => ['nullable', 'string', 'max:8000'],
                'textarea' => ['nullable', 'string', 'max:'.($field['max'] ?? 5000)],
                'key_value' => ['nullable', 'array'],
                'toggle_map' => ['nullable', 'array'],
                'gateways' => ['nullable', 'array'],
                'campaign_services' => ['nullable', 'array'],
                'json' => ['nullable', 'string', 'max:20000', $this->jsonRule()],
                default => ['nullable', 'string', 'max:'.($field['max'] ?? 190)],
            };

            if ($field['required'] ?? false) {
                $rules[$name] = array_map(
                    static fn ($rule) => $rule === 'nullable' ? 'required' : $rule,
                    $rules[$name],
                );
            }

            if ($field['type'] === 'text' && ($field['uppercase'] ?? false)) {
                $rules[$name][] = 'regex:/^[A-Za-z]{3}$/';
            }

            if ($field['type'] === 'lines') {
                $rules[$name.'.*'] = ['nullable', 'string', 'max:300'];
            }

            if ($field['type'] === 'key_value') {
                $rules[$name.'.names'] = ['nullable', 'array'];
                $rules[$name.'.urls'] = ['nullable', 'array'];
                $rules[$name.'.names.*'] = ['nullable', 'string', 'max:60'];
                $rules[$name.'.urls.*'] = ['nullable', 'string', 'max:300'];
            }

            if ($field['type'] === 'toggle_map') {
                foreach (array_keys($field['options']) as $option) {
                    $rules[$name.'.'.$option] = ['nullable', 'boolean'];
                }
            }

            if ($field['type'] === 'gateways') {
                $rules[$name.'.manual'] = ['nullable', 'boolean'];
                $rules[$name.'.gateways'] = ['nullable', 'array'];
                $rules[$name.'.gateways.*.currencies'] = ['nullable', 'array'];
                $rules[$name.'.gateways.*.currencies.*'] = ['nullable', 'boolean'];
            }

            if ($field['type'] === 'campaign_services') {
                $rules[$name.'.rows'] = ['nullable', 'array'];
                $rules[$name.'.rows.*.platform'] = ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9_]*$/i'];
                $rules[$name.'.rows.*.action'] = ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9_]*$/i'];
                $rules[$name.'.rows.*.value'] = ['nullable', 'numeric', 'min:0', 'max:100000000'];
                $rules[$name.'.rows.*.remove'] = ['nullable', 'boolean'];
            }

            if ($field['editable_public'] ?? true) {
                $rules['public.'.$field['form_key']] = ['nullable', 'boolean'];
            }
        }

        return $rules;
    }

    /**
     * Persist a validated submission and return the settings that changed.
     *
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $validated
     * @return list<array{key: string, before: mixed, after: mixed}>
     */
    public function save(array $fields, array $validated, Collection $settings, ?int $adminId): array
    {
        $changes = [];
        $publicFlags = (array) ($validated['public'] ?? []);
        $values = (array) ($validated['values'] ?? []);

        foreach ($fields as $field) {
            $key = $field['key'];
            if (! array_key_exists($field['form_key'], $values)) {
                // Field was not on the screen (disabled) — leave its value alone.
                continue;
            }
            $raw = $values[$field['form_key']] ?? null;
            $isPublic = (bool) ($publicFlags[$field['form_key']] ?? ($field['is_public'] ?? false));

            // Derived settings are written together with their host field.
            if ($field['type'] === 'gateways') {
                foreach ($this->gatewaySettings($raw, $settings) as $derivedKey => $derivedValue) {
                    // The chip controls the field's own row; the derived option
                    // list keeps the visibility it already had.
                    $derivedPublic = $derivedKey === $key
                        ? $isPublic
                        : (bool) ($settings->get($derivedKey)?->is_public ?? $isPublic);

                    $changes = array_merge($changes, $this->persist($derivedKey, $derivedValue, $derivedPublic, $settings->get($derivedKey), $adminId));
                }

                continue;
            }

            $normalised = $this->normalise($field, $raw, $settings->get($key));
            $changes = array_merge($changes, $this->persist($key, $normalised, $isPublic, $settings->get($key), $adminId));
        }

        return $changes;
    }

    /**
     * @param  list<array{key: string, before: mixed, after: mixed}>  $changes
     * @param  list<string>  $sensitiveKeys
     */
    public function audit(array $changes, array $sensitiveKeys, ?int $adminId, ?string $ip): void
    {
        foreach ($changes as $change) {
            AuditLog::create([
                'actor_admin_id' => $adminId,
                'action' => 'system_setting.updated',
                'entity_type' => 'system_setting',
                'entity_id' => $change['key'],
                'before_values' => ['key' => $change['key'], 'value' => $this->redact($change['key'], $change['before'], $sensitiveKeys)],
                'after_values' => ['key' => $change['key'], 'value' => $this->redact($change['key'], $change['after'], $sensitiveKeys)],
                'ip_hash' => $ip ? hash('sha256', $ip) : null,
                'created_at' => now(),
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function resolveField(string $key, array $definition, Collection $settings): array
    {
        $formKey = str_replace('.', '::', $key);
        $row = $settings->get($key);
        /** @var mixed $raw */
        $raw = $row?->value;

        $field = [
            'key' => $key,
            'form_key' => $formKey,
            'name' => 'values['.$formKey.']',
            // Dot notation for the validator; the HTML name above uses brackets.
            'rule_name' => 'values.'.$formKey,
            'label' => $definition['label'] ?? $key,
            'section' => $definition['section'] ?? 'General',
            'type' => $definition['type'] ?? 'text',
            'help' => $definition['help'] ?? null,
            'max' => $definition['max'] ?? null,
            'min' => $definition['min'] ?? null,
            'step' => $definition['step'] ?? null,
            'rows' => $definition['rows'] ?? 4,
            'suffix' => $definition['suffix'] ?? null,
            'uppercase' => (bool) ($definition['uppercase'] ?? false),
            'disabled' => (bool) ($definition['disabled'] ?? false),
            'required' => (bool) ($definition['required'] ?? false),
            'link_tab' => $definition['link_tab'] ?? null,
            'key_label' => $definition['key_label'] ?? 'Key',
            'value_label' => $definition['value_label'] ?? 'Value',
            'options' => $definition['options'] ?? [],
            'wrap' => $definition['wrap'] ?? null,
            'is_public' => (bool) ($row?->is_public ?? false),
            'exists' => $row !== null,
            'editable_public' => true,
            'updated_by' => $row?->updatedBy?->name,
            'updated_at' => $row?->updated_at?->diffForHumans(),
        ];

        $field['value'] = $this->present($field, $raw);

        if ($field['type'] === 'gateways') {
            $field = array_merge($field, $this->gatewayState($settings));
        }

        if ($field['type'] === 'campaign_services') {
            $field['rows'] = $this->campaignRows($field['value']);
        }

        if ($field['type'] === 'json') {
            $field['json_text'] = is_array($raw) || is_object($raw)
                ? (string) json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : (is_scalar($raw) ? (string) json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '');
        }

        return $field;
    }

    /** Current database value, shaped for its editor. */
    private function present(array $field, mixed $raw): mixed
    {
        return match ($field['type']) {
            'toggle' => $this->boolean($raw),
            'toggle_map' => $this->booleanMap(is_array($raw) ? $raw : []),
            'integer' => is_numeric($raw) ? (int) $raw : null,
            'decimal' => is_numeric($raw) ? (float) $raw : null,
            'lines' => $this->lines($raw),
            'key_value' => $this->stringMap($raw),
            'select' => is_scalar($raw) ? (string) $raw : '',
            'select_wrapped' => $this->unwrap($raw, (string) $field['wrap']),
            'gateways' => is_array($raw) ? $raw : [],
            'campaign_services' => is_array($raw) ? $raw : [],
            'json' => $raw,
            default => is_scalar($raw) ? (string) $raw : '',
        };
    }

    /**
     * @return array{gateways: list<array<string, mixed>>, currencies: list<string>}
     */
    private function gatewayState(Collection $settings): array
    {
        /** @var array<string, mixed> $active */
        $active = $settings->get('payment.active_payment_currency')?->value;
        $active = is_array($active) ? $active : [];
        /** @var array<string, mixed> $legacy */
        $legacy = $settings->get('payment.active_gateways')?->value;
        $legacy = is_array($legacy) ? $legacy : [];

        $implemented = array_map('strval', (array) config('trustlens.implemented_payment_gateways', []));
        $selected = [];
        $currencies = SystemSettingsSchema::CURRENCIES;

        foreach (array_keys($active) as $compound) {
            if (! is_string($compound) || ! str_contains($compound, '.')) {
                continue;
            }
            [$gateway, $currency] = explode('.', $compound, 2);
            $gateway = $this->normaliseGateway((string) $gateway);
            $currency = strtoupper($currency);
            if ($gateway === null || preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
                continue;
            }
            $selected[$gateway][$currency] = true;
            if (! in_array($currency, $currencies, true)) {
                $currencies[] = $currency;
            }
        }

        // Fall back to the legacy matrix when the derived map is missing, so a
        // fresh install still opens with the seeded gateways ticked.
        if ($active === [] && $legacy !== [] && ! array_is_list($legacy)) {
            foreach ($legacy as $gateway => $list) {
                if (! isset(SystemSettingsSchema::GATEWAYS[$gateway]) || ! is_array($list)) {
                    continue;
                }
                foreach ($list as $currency) {
                    $currency = strtoupper((string) $currency);
                    if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
                        continue;
                    }
                    $selected[$gateway][$currency] = true;
                    if (! in_array($currency, $currencies, true)) {
                        $currencies[] = $currency;
                    }
                }
            }
        }

        $manual = array_key_exists('manual', $selected)
            || ($legacy !== [] && ! array_is_list($legacy) && array_key_exists('manual', $legacy));

        $gateways = [];
        foreach (SystemSettingsSchema::GATEWAYS as $gateway => $label) {
            $row = [
                'key' => $gateway,
                'label' => $label,
                'manual' => $gateway === 'manual',
                'configured' => in_array($gateway, $implemented, true),
                'enabled' => $gateway === 'manual' ? $manual : array_key_exists($gateway, $selected),
                'currencies' => [],
            ];

            foreach ($currencies as $currency) {
                $row['currencies'][$currency] = isset($selected[$gateway][$currency]);
            }

            $gateways[] = $row;
        }

        return ['gateways' => $gateways, 'currencies' => $currencies];
    }

    /** @return list<array{platform: string, action: string, label: string, value: float|int|string}> */
    private function campaignRows(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $platform => $services) {
            if (! is_array($services)) {
                continue;
            }
            foreach ($services as $action => $price) {
                if (! is_scalar($price)) {
                    continue;
                }
                $rows[] = [
                    'platform' => (string) $platform,
                    'action' => (string) $action,
                    'label' => Str::headline((string) $platform).' · '.Str::headline((string) $action),
                    'value' => is_numeric($price) ? (float) $price : (string) $price,
                ];
            }
        }

        return $rows;
    }

    /** Convert one submitted value into the shape stored in the database. */
    private function normalise(array $field, mixed $raw, ?SystemSetting $current): mixed
    {
        $existing = $current?->value;

        return match ($field['type']) {
            'toggle' => $this->boolean($raw),
            'integer' => $raw === null || $raw === '' ? null : (int) $raw,
            'decimal' => $raw === null || $raw === '' ? null : (static function () use ($raw) {
                $number = round((float) $raw, 4);

                return fmod($number, 1.0) === 0.0 ? (int) $number : $number;
            })(),
            'lines' => $this->lines($raw),
            'key_value' => $this->pairMap((array) $raw),
            'toggle_map' => $this->mergeBooleanMap(is_array($existing) ? $existing : [], (array) $raw, array_keys($field['options'])),
            'select' => $raw === null ? '' : (string) $raw,
            'select_wrapped' => [$field['wrap'] => (string) ($raw ?? '')],
            'campaign_services' => $this->buildCampaign((array) $raw),
            'json' => $this->decodeJson($raw),
            default => $raw === null ? '' : ($field['uppercase'] ? strtoupper(trim((string) $raw)) : trim((string) $raw)),
        };
    }

    /**
     * Build the two gateway settings from the matrix submission. The derived
     * map powers the checkout picker, the legacy map keeps
     * `PaymentGatewaySettings::manualEnabled()` working.
     *
     * @return array{payment.active_gateways: array<string, list<string>>, payment.active_payment_currency: array<string, string>}
     */
    private function gatewaySettings(mixed $raw, Collection $settings): array
    {
        $raw = is_array($raw) ? $raw : [];
        $submitted = is_array($raw['gateways'] ?? null) ? $raw['gateways'] : [];

        // Reuse the labels already shown to customers (e.g. "NGN-2") so a save
        // never rewrites the checkout copy.
        $existingLabels = [];
        $stored = $settings->get('payment.active_payment_currency')?->value;
        if (is_array($stored)) {
            $existingLabels = $this->optionLabels($stored);
        }

        $legacy = [];
        $active = [];

        foreach (SystemSettingsSchema::GATEWAYS as $gateway => $label) {
            if ($gateway === 'manual') {
                if ($this->boolean($raw['manual'] ?? false)) {
                    $legacy['manual'] = [];
                }

                continue;
            }

            $row = is_array($submitted[$gateway] ?? null) ? $submitted[$gateway] : [];
            $currencies = [];

            foreach ((array) ($row['currencies'] ?? []) as $currency => $ticked) {
                $currency = strtoupper((string) $currency);
                if (preg_match('/^[A-Z]{3}$/', $currency) !== 1 || ! $this->boolean($ticked)) {
                    continue;
                }
                $currencies[] = $currency;
            }

            $currencies = array_values(array_unique($currencies));
            sort($currencies);

            if ($currencies === []) {
                continue;
            }

            $legacy[$gateway] = $currencies;

            foreach ($currencies as $currency) {
                $compound = $gateway.'.'.$currency;
                $active[$compound] = $existingLabels[$compound] ?? $currency;
            }
        }

        return [
            'payment.active_gateways' => $legacy,
            'payment.active_payment_currency' => $active,
        ];
    }

    /** @return array<string, list<string>|int> */
    private function buildCampaign(array $raw): array
    {
        $rows = is_array($raw['rows'] ?? null) ? $raw['rows'] : [];
        $built = [];

        foreach ($rows as $row) {
            if (! is_array($row) || $this->boolean($row['remove'] ?? false)) {
                continue;
            }

            $platform = strtolower(trim((string) ($row['platform'] ?? '')));
            $action = strtolower(trim((string) ($row['action'] ?? '')));
            $value = $row['value'] ?? null;

            if ($platform === '' || $action === '' || $value === '' || $value === null || ! is_numeric($value)) {
                continue;
            }

            $number = (float) $value;
            $built[$platform][$action] = $action === 'min'
                ? (int) $number
                : (fmod($number, 1.0) === 0.0 ? (int) $number : round($number, 2));
        }

        return $built;
    }

    /**
     * @param  list<string>  $sensitiveKeys
     */
    private function redact(string $key, mixed $value, array $sensitiveKeys): mixed
    {
        if (in_array($key, $sensitiveKeys, true)) {
            return '[redacted]';
        }

        return $value;
    }

    /** @return list<array{key: string, before: mixed, after: mixed}> */
    private function persist(string $key, mixed $value, bool $isPublic, ?SystemSetting $current, ?int $adminId): array
    {
        $before = $current?->value;
        $beforePublic = (bool) ($current?->is_public ?? false);

        if ($current !== null && $this->sameValue($before, $value) && $beforePublic === $isPublic) {
            return [];
        }

        $setting = $current ?? new SystemSetting(['setting_key' => $key]);
        $setting->fill([
            'value' => $value,
            'is_public' => $isPublic,
            'updated_by_admin_id' => $adminId,
        ])->save();

        return [['key' => $key, 'before' => $before, 'after' => $setting->value]];
    }

    /**
     * Arrays compare by content (key order is irrelevant for JSON settings),
     * scalars by type and value.
     */
    private function sameValue(mixed $before, mixed $after): bool
    {
        if (is_array($before) && is_array($after)) {
            return $before == $after;
        }

        return $before === $after;
    }

    private function jsonRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }
            json_decode((string) $value);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $fail('This value must be valid JSON.');
            }
        };
    }

    /** Stored gateway name → schema key, accepting the historical `korapy` typo. */
    private function normaliseGateway(string $gateway): ?string
    {
        $gateway = strtolower(trim($gateway));
        if ($gateway === 'korapy') {
            $gateway = 'korapay';
        }

        return isset(SystemSettingsSchema::GATEWAYS[$gateway]) ? $gateway : null;
    }

    /**
     * Normalised `gateway.CURRENCY => label` map from the stored option list.
     *
     * @param  array<string, mixed>  $stored
     * @return array<string, string>
     */
    private function optionLabels(array $stored): array
    {
        $labels = [];

        foreach ($stored as $compound => $label) {
            if (! is_string($compound) || ! str_contains($compound, '.') || ! is_scalar($label)) {
                continue;
            }
            [$gateway, $currency] = explode('.', $compound, 2);
            $gateway = $this->normaliseGateway((string) $gateway);
            $currency = strtoupper((string) $currency);
            if ($gateway === null || preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
                continue;
            }
            $label = trim((string) $label);
            $labels[$gateway.'.'.$currency] = $label !== '' ? $label : $currency;
        }

        return $labels;
    }

    private function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_array($value)) {
            return $value !== [];
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /** @return array<string, bool> */
    private function booleanMap(array $value): array
    {
        $map = [];
        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                continue;
            }
            $map[$key] = $this->boolean($item);
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $submitted
     * @param  list<string>  $known
     * @return array<string, bool>
     */
    private function mergeBooleanMap(array $existing, array $submitted, array $known): array
    {
        $map = $this->booleanMap($existing);

        foreach ($known as $key) {
            $map[$key] = $this->boolean($submitted[$key] ?? false);
        }

        return $map;
    }

    /** @return array<string, string> */
    private function stringMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $map = [];
        foreach ($value as $key => $item) {
            if (! is_string($key) || is_array($item) || is_object($item)) {
                continue;
            }
            $map[$key] = (string) $item;
        }

        return $map;
    }

    /**
     * Turn the `names[]` / `urls[]` rows of a key/value editor into a map.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, string>
     */
    private function pairMap(array $raw): array
    {
        $names = is_array($raw['names'] ?? null) ? array_values($raw['names']) : [];
        $urls = is_array($raw['urls'] ?? null) ? array_values($raw['urls']) : [];
        $map = [];

        foreach ($names as $index => $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $map[$name] = trim((string) ($urls[$index] ?? ''));
        }

        return $map;
    }

    /** @return list<string> */
    private function lines(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(
                static fn ($item): string => trim((string) $item),
                array_filter($value, static fn ($item): bool => is_scalar($item)),
            ), static fn (string $line): bool => $line !== ''));
        }

        if (! is_scalar($value)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $value) ?: []), static fn (string $line): bool => $line !== ''));
    }

    private function unwrap(mixed $value, string $key): string
    {
        if (is_array($value)) {
            return is_scalar($value[$key] ?? null) ? (string) $value[$key] : '';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private function decodeJson(mixed $raw): mixed
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $decoded = json_decode((string) $raw, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
