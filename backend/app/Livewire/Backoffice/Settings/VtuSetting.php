<?php

namespace App\Livewire\Backoffice\Settings;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\User;
use App\Services\Vas\BillProviderManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.backoffice')]
#[Title('Bill Payment Settings — Backoffice')]
class VtuSetting extends Component
{
    use AuthorizesAdminActions;

    public bool $enabled = true;

    public string $provider = 'vtpass';

    public array $wallets = [];

    public function mount(): void
    {
        $this->enabled = (bool) config('settings.vtu.enabled');
        $this->provider = (string) config('settings.vtu.provider', 'vtpass');

        foreach (array_keys((array) config('settings.vtu.wallets', [])) as $key) {
            $this->wallets[$key] = [
                'enabled' => (bool) config("settings.vtu.wallets.{$key}.enabled"),
                'min_ngn' => (string) config("settings.vtu.wallets.{$key}.min_ngn"),
                'max_ngn' => (string) config("settings.vtu.wallets.{$key}.max_ngn"),
                'max_purchases' => (string) config("settings.vtu.wallets.{$key}.max_purchases"),
            ];
        }
    }

    public function save(): void
    {
        $this->authorizeWrite();

        $validator = Validator::make(
            ['wallets' => $this->wallets, 'provider' => $this->provider],
            [
                'provider' => ['required', Rule::in(app(BillProviderManager::class)->names())],
                'wallets.*.min_ngn' => ['required', 'numeric', 'min:0'],
                'wallets.*.max_ngn' => ['required', 'numeric', 'min:0'],
                'wallets.*.max_purchases' => ['required', 'integer', 'min:0'],
            ],
            [
                'wallets.*.min_ngn.required' => 'Enter a min amount (0 for no floor).',
                'wallets.*.min_ngn.numeric' => 'The min amount must be a number.',
                'wallets.*.max_ngn.required' => 'Enter a max amount (0 for no cap).',
                'wallets.*.max_ngn.numeric' => 'The max amount must be a number.',
                'wallets.*.max_purchases.required' => 'Enter a purchase limit (0 for unlimited).',
                'wallets.*.max_purchases.integer' => 'The purchase limit must be a whole number.',
            ]
        );

        $validator->after(function ($v): void {
            foreach ($this->wallets as $key => $config) {
                $min = (float) ($config['min_ngn'] ?? 0);
                $max = (float) ($config['max_ngn'] ?? 0);
                if ($max > 0 && $min > $max) {
                    $v->errors()->add("wallets.{$key}.min_ngn", 'The minimum cannot be more than the maximum.');
                }
            }
        });

        $validator->validate();

        $payload = ['enable_vtu' => $this->enabled, 'bill_fulfilment_provider' => $this->provider];

        foreach ($this->wallets as $key => $config) {
            $payload["enable_{$key}_vtu"] = $config['enabled'] ?? false;
            $payload["{$key}_vtu_min_ngn"] = $config['min_ngn'] ?? 0;
            $payload["{$key}_vtu_max_ngn"] = $config['max_ngn'] ?? 0;
            $payload["{$key}_vtu_max_purchases"] = $config['max_purchases'] ?? 0;
        }

        update_setting($payload);

        $this->dispatch('toast', message: 'Bill payment settings saved successfully', type: 'success');
    }

    public function render(): View
    {
        $labels = ['vtpass' => 'VTpass', 'flutterwave' => 'Flutterwave'];

        return view('livewire.backoffice.settings.vtu-setting', [
            'walletLabels' => Arr::only(User::WALLET_LABELS, User::SPENDABLE_WALLETS),
            'providers' => collect(app(BillProviderManager::class)->names())
                ->mapWithKeys(fn (string $n): array => [$n => $labels[$n] ?? ucfirst($n)])
                ->all(),
        ]);
    }
}
