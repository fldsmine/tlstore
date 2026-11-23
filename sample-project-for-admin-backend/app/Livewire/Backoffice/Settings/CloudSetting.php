<?php

namespace App\Livewire\Backoffice\Settings;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.backoffice')]
#[Title('Cloud Settings — Backoffice')]
class CloudSetting extends Component
{
    use AuthorizesAdminActions;

    public bool $enabled = true;

    public string $referralBonus = '';

    public string $onlineWindowSeconds = '';

    public string $broadcastExpiryMinutes = '';

    public function mount(): void
    {
        $this->enabled = (bool) config('settings.cloud.enabled');
        $this->referralBonus = (string) config('settings.cloud.referral_bonus');
        $this->onlineWindowSeconds = (string) config('settings.cloud.online_window_seconds');
        $this->broadcastExpiryMinutes = (string) config('settings.cloud.broadcast_expiry_minutes');
    }

    public function save(): void
    {
        $this->authorizeWrite();

        $this->validate([
            'referralBonus' => ['required', 'numeric', 'min:0'],
            'onlineWindowSeconds' => ['required', 'integer', 'min:5'],
            'broadcastExpiryMinutes' => ['required', 'integer', 'min:1'],
        ], attributes: [
            'referralBonus' => 'referral bonus',
            'onlineWindowSeconds' => 'online window',
            'broadcastExpiryMinutes' => 'broadcast expiry',
        ]);

        update_setting([
            'enable_cloud' => $this->enabled,
            'cloud_referral_bonus' => $this->referralBonus,
            'cloud_online_window_seconds' => (int) $this->onlineWindowSeconds,
            'cloud_broadcast_expiry_minutes' => (int) $this->broadcastExpiryMinutes,
        ]);

        $this->dispatch('toast', message: 'Cloud settings saved successfully', type: 'success');
    }

    public function render(): View
    {
        return view('livewire.backoffice.settings.cloud-setting');
    }
}
