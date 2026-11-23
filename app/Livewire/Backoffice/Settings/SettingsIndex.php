<?php

namespace App\Livewire\Backoffice\Settings;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Operational settings. The full catalogue is listed read-only; only a small,
 * typed allowlist is editable here (everything else — provider secrets, payment
 * gateway maps, VAS service maps — must go through the API with its schema
 * validation, or the server environment). Secrets are never shown or editable.
 */
#[Layout('components.layouts.backoffice')]
#[Title('Operational settings')]
class SettingsIndex extends Component
{
    use AuthorizesAdminActions;

    /** Editable key => type. Anything not listed is read-only in this screen. */
    private const EDITABLE = [
        'vas.enabled' => 'bool',
        'vas.catalog_cache_minutes' => 'int:5:1440',
        'campaign.service_fee_percent' => 'float:0:100',
        'campaign.currency' => 'currency',
    ];

    /** @var array<string, string> */
    public array $inputs = [];

    public function mount(): void
    {
        $this->authorizeAdmin();
        foreach (self::EDITABLE as $key => $type) {
            $value = SystemSetting::query()->where('setting_key', $key)->value('value');
            $this->inputs[$key] = $this->valueToInput($value, $type);
        }
    }

    public function save(string $key): void
    {
        $admin = $this->authorizeAdmin();
        if (! array_key_exists($key, self::EDITABLE)) {
            $this->dispatch('toast', message: 'This setting is read-only here.', type: 'warning');

            return;
        }
        $raw = $this->inputs[$key] ?? '';

        $value = match (self::EDITABLE[$key]) {
            'bool' => filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'int:5:1440' => $this->parseInt($raw, 5, 1440),
            'float:0:100' => $this->parseFloat($raw, 0, 100),
            'currency' => preg_match('/^[A-Za-z]{3}$/', trim((string) $raw)) ? strtoupper(trim((string) $raw)) : null,
            default => null,
        };

        if ($value === null) {
            $this->addError('inputs.'.$key, 'Enter a valid value.');

            return;
        }

        DB::transaction(function () use ($admin, $key, $value): void {
            $setting = SystemSetting::query()->firstOrNew(['setting_key' => $key]);
            $before = $setting->exists ? ['value' => $setting->value, 'is_public' => $setting->is_public] : null;
            $setting->fill([
                'value' => $value,
                'is_public' => $setting->is_public ?? false,
                'updated_by_admin_id' => $admin->id,
            ])->save();

            AuditLog::query()->create([
                'actor_admin_id' => $admin->id,
                'action' => 'system_setting.updated',
                'entity_type' => 'system_setting',
                'entity_id' => $key,
                'before_values' => $before,
                'after_values' => ['value' => $setting->value, 'is_public' => $setting->is_public],
                'created_at' => now(),
            ]);
        });

        $this->dispatch('toast', message: "Saved {$key}.", type: 'success');
    }

    private function parseInt(string $raw, int $min, int $max): ?int
    {
        $raw = trim($raw);
        if (filter_var($raw, FILTER_VALIDATE_INT) === false) {
            return null;
        }
        $value = (int) $raw;

        return ($value >= $min && $value <= $max) ? $value : null;
    }

    private function parseFloat(string $raw, float $min, float $max): ?float
    {
        $raw = trim($raw);
        if (! is_numeric($raw)) {
            return null;
        }
        $value = (float) $raw;

        return ($value >= $min && $value <= $max) ? $value : null;
    }

    private function valueToInput(mixed $value, string $type): string
    {
        return match ($type) {
            'bool' => $value === true || $value === 1 || $value === '1' || $value === 'true' ? '1' : '0',
            default => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value,
        };
    }

    public function render(): View
    {
        $this->authorizeAdmin();

        $settings = SystemSetting::query()
            ->where('setting_key', 'not like', '%secret%')
            ->where('setting_key', 'not like', '%credential%')
            ->where('setting_key', 'not like', '%private_key%')
            ->orderBy('setting_key')
            ->get(['setting_key', 'value', 'is_public', 'updated_at']);

        return view('livewire.backoffice.settings.settings-index', [
            'settings' => $settings,
            'editable' => self::EDITABLE,
        ]);
    }
}
