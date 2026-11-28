<?php

namespace App\Application\Settings;

use App\Models\AuditLog;
use Closure;
use Dotenv\Exception\InvalidFileException;

/**
 * Resolves, validates and writes the console-managed environment variables
 * through {@see EnvironmentSettingsSchema} and {@see EnvironmentFile}.
 *
 * Secrets are write-only: their stored value is never handed to the view, and a
 * blank secret field always means "keep what is there".
 */
final class EnvironmentSettingsManager
{
    public function __construct(private readonly EnvironmentFile $file) {}

    public function file(): EnvironmentFile
    {
        return $this->file;
    }

    /**
     * Why the environment cannot be edited right now, if it cannot.
     */
    public function problem(): ?string
    {
        if (! $this->file->exists()) {
            return 'No .env file was found at '.$this->file->path().'. This deployment receives its configuration from the runtime environment, so credentials have to be set there.';
        }

        if (! $this->file->isWritable()) {
            return 'The .env file at '.$this->file->path().' is not writable by the web server (check ownership and permissions).';
        }

        try {
            $this->file->values();
        } catch (InvalidFileException $exception) {
            return 'The .env file could not be parsed: '.$exception->getMessage();
        }

        return null;
    }

    /**
     * Sections and providers with their current state, ready for the view.
     *
     * @return array{sections: array<string, array<string, mixed>>, configured: int, total: int}
     */
    public function sections(): array
    {
        try {
            $values = $this->file->values();
        } catch (InvalidFileException) {
            $values = [];
        }

        $sections = [];
        $configured = 0;
        $total = 0;

        foreach (EnvironmentSettingsSchema::sections() as $sectionId => $section) {
            $providers = [];

            foreach ($section['providers'] as $providerId => $provider) {
                $fields = [];
                $setCount = 0;

                foreach ($provider['keys'] as $key => $definition) {
                    $field = $this->resolveField($key, $definition, $values[$key] ?? null);
                    $fields[] = $field;
                    if ($field['is_set']) {
                        $setCount++;
                    }
                }

                $required = $provider['required'] ?? array_keys($provider['keys']);
                $missingRequired = array_values(array_filter(
                    $required,
                    static fn (string $key): bool => trim((string) ($values[$key] ?? '')) === '',
                ));

                $status = match (true) {
                    $missingRequired === [] => 'configured',
                    count($missingRequired) === count($required) && $setCount === 0 => 'missing',
                    default => 'incomplete',
                };

                $total++;
                if ($status === 'configured') {
                    $configured++;
                }

                $providers[$providerId] = [
                    'id' => $providerId,
                    'label' => $provider['label'],
                    'docs' => $provider['docs'] ?? null,
                    'status' => $status,
                    'set_count' => $setCount,
                    'key_count' => count($provider['keys']),
                    'missing_required' => $missingRequired,
                    'fields' => $fields,
                ];
            }

            $sections[$sectionId] = [
                'label' => $section['label'],
                'description' => $section['description'],
                'providers' => $providers,
            ];
        }

        return ['sections' => $sections, 'configured' => $configured, 'total' => $total];
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // Laravel omits array keys without an explicit rule from validated().
        $rules = ['values' => ['array'], 'clear' => ['array']];

        foreach (EnvironmentSettingsSchema::keys() as $key => $definition) {
            $name = 'values.'.$key;
            $rules[$name] = match ($definition['type']) {
                'secret' => ['nullable', 'string', 'max:4000'],
                'url' => ['nullable', 'string', 'max:500', 'regex:/^https?:\/\/\S+$/i'],
                'email' => ['nullable', 'email', 'max:190'],
                'integer' => ['nullable', 'integer', 'min:'.($definition['min'] ?? 0), 'max:'.($definition['max'] ?? 100000)],
                'boolean' => ['nullable', 'in:true,false,1,0'],
                'select' => ['nullable', 'in:'.implode(',', array_keys($definition['options']))],
                'list' => ['nullable', 'string', 'max:2000', $this->listRule()],
                default => ['nullable', 'string', 'max:255'],
            };

            $rules['clear.'.$key] = ['nullable', 'boolean'];
        }

        return $rules;
    }

    /**
     * Apply a validated submission and report what changed.
     *
     * @param  array<string, mixed>  $validated
     * @return list<array{key: string, before: ?string, after: ?string}>
     */
    public function save(array $validated): array
    {
        $submitted = (array) ($validated['values'] ?? []);
        $cleared = (array) ($validated['clear'] ?? []);
        $current = $this->file->values();

        $write = [];
        $changes = [];

        foreach (EnvironmentSettingsSchema::keys() as $key => $definition) {
            $now = $current[$key] ?? null;
            $isCleared = filter_var($cleared[$key] ?? false, FILTER_VALIDATE_BOOLEAN);

            // Only touch what the screen actually sent: a key that is absent
            // from the submission (another tab, an older form, a partial API
            // call) must keep its stored value.
            if (! array_key_exists($key, $submitted) && ! $isCleared) {
                continue;
            }

            $raw = $submitted[$key] ?? '';
            $raw = is_string($raw) ? trim($raw) : '';

            if (filter_var($cleared[$key] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                if ($now !== null) {
                    $write[$key] = null;
                    $changes[] = ['key' => $key, 'before' => $now, 'after' => null];
                }

                continue;
            }

            if ($definition['type'] === 'secret') {
                // Write-only: empty means "keep the stored secret".
                if ($raw === '') {
                    continue;
                }
                if ($now !== null && hash_equals($now, $raw)) {
                    continue;
                }

                $write[$key] = $raw;
                $changes[] = ['key' => $key, 'before' => $now, 'after' => $raw];

                continue;
            }

            // An empty boolean is not a "false": the picker always sends one of
            // true/false, so nothing was chosen.
            if ($definition['type'] === 'boolean' && $raw === '') {
                continue;
            }

            $value = $this->normalise($definition, $raw);

            if ($value === '') {
                if ($now === null) {
                    continue;
                }

                $write[$key] = null;
                $changes[] = ['key' => $key, 'before' => $now, 'after' => null];

                continue;
            }

            // Same stored value, or the built-in default the app already uses.
            if ($now === $value || ($now === null && $value === $this->defaultFor($definition))) {
                continue;
            }

            $write[$key] = $value;
            $changes[] = ['key' => $key, 'before' => $now, 'after' => $value];
        }

        if ($write !== []) {
            $this->file->put($write);
        }

        return $changes;
    }

    /**
     * One audit row per changed variable. Secret values are redacted.
     *
     * @param  list<array{key: string, before: ?string, after: ?string}>  $changes
     */
    public function audit(array $changes, ?int $adminId, ?string $ip): void
    {
        $definitions = EnvironmentSettingsSchema::keys();

        foreach ($changes as $change) {
            $secret = ($definitions[$change['key']]['type'] ?? null) === 'secret';

            AuditLog::create([
                'actor_admin_id' => $adminId,
                'action' => 'environment.updated',
                'entity_type' => 'environment',
                'entity_id' => $change['key'],
                'before_values' => [
                    'key' => $change['key'],
                    'set' => $change['before'] !== null,
                    'value' => $secret ? ($change['before'] === null ? null : '[redacted]') : $change['before'],
                ],
                'after_values' => [
                    'key' => $change['key'],
                    'set' => $change['after'] !== null,
                    'value' => $secret ? ($change['after'] === null ? null : '[redacted]') : $change['after'],
                ],
                'ip_hash' => $ip !== null ? hash('sha256', $ip) : null,
                'created_at' => now(),
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function resolveField(string $key, array $definition, ?string $current): array
    {
        $default = $this->defaultFor($definition);
        $isSecret = $definition['type'] === 'secret';
        $isSet = $current !== null && trim($current) !== '';

        return [
            'key' => $key,
            'name' => 'values['.$key.']',
            'rule_name' => 'values.'.$key,
            'clear_name' => 'clear['.$key.']',
            'label' => $definition['label'],
            'type' => $definition['type'],
            'help' => $definition['help'] ?? null,
            'is_secret' => $isSecret,
            'is_set' => $isSet,
            // Secrets never leave the server: the view only learns whether a
            // value exists, never the value itself.
            'display' => $isSecret ? '' : ($current ?? $default ?? ''),
            'default' => $default,
            'effective' => $isSecret ? null : ($current ?? $default ?? ''),
            'options' => $definition['options'] ?? [],
            'min' => $definition['min'] ?? null,
            'max' => $definition['max'] ?? null,
            'suffix' => $definition['suffix'] ?? null,
        ];
    }

    private function normalise(array $definition, string $raw): string
    {
        return match ($definition['type']) {
            'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
            'integer' => $raw === '' ? '' : (string) (int) $raw,
            'list' => implode(',', array_values(array_unique(array_filter(array_map(
                static fn (string $item): string => strtoupper(trim($item)),
                explode(',', $raw),
            ), static fn (string $item): bool => $item !== '')))),
            default => $raw,
        };
    }

    private function defaultFor(array $definition): ?string
    {
        if (! array_key_exists('default', $definition)) {
            return null;
        }

        $default = $definition['default'];

        return is_bool($default) ? ($default ? 'true' : 'false') : (string) $default;
    }

    private function listRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            foreach (explode(',', (string) $value) as $item) {
                $item = trim($item);
                if ($item !== '' && mb_strlen($item) > 100) {
                    $fail('Each entry must be a single value of at most 100 characters.');

                    return;
                }
            }
        };
    }
}
