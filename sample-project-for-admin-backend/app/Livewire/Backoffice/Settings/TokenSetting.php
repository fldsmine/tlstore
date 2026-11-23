<?php

namespace App\Livewire\Backoffice\Settings;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.backoffice')]
#[Title('PIN-reset Settings — Backoffice')]
class TokenSetting extends Component
{
    use AuthorizesAdminActions;

    public bool $pinTokenEnabled = true;

    public string $pinResetTokenPrice = '';

    public string $pinResetTokenAgentPrice = '';

    public string $pinResetTokenForeignPrice = '';

    public string $pinResetTokenForeignAgentPrice = '';

    public function mount(): void
    {
        $this->pinTokenEnabled = (bool) config('settings.token.pin_reset.enabled');
        $this->pinResetTokenPrice = (string) config('settings.token.pin_reset.price');
        $this->pinResetTokenAgentPrice = (string) config('settings.token.pin_reset.agent_price');
        $this->pinResetTokenForeignPrice = (string) config('settings.token.pin_reset.foreign_price');
        $this->pinResetTokenForeignAgentPrice = (string) config('settings.token.pin_reset.foreign_agent_price');
    }

    public function save(): void
    {
        $this->authorizeWrite();

        $this->validate([
            'pinResetTokenPrice' => ['required', 'numeric', 'min:0'],
            'pinResetTokenAgentPrice' => ['required', 'numeric', 'min:0'],
            // Required, not nullable: settings are stored as strings, so a blank foreign value
            // would persist as "" and resolve to 0 instead of falling back to the Nigerian price.
            'pinResetTokenForeignPrice' => ['required', 'numeric', 'min:0'],
            'pinResetTokenForeignAgentPrice' => ['required', 'numeric', 'min:0'],
        ]);

        update_setting([
            'enable_pin_reset_token' => $this->pinTokenEnabled,
            'pin_reset_token_price' => $this->pinResetTokenPrice,
            'pin_reset_token_agent_price' => $this->pinResetTokenAgentPrice,
            'pin_reset_token_foreign_price' => $this->pinResetTokenForeignPrice,
            'pin_reset_token_foreign_agent_price' => $this->pinResetTokenForeignAgentPrice,
        ]);

        $this->dispatch('toast', message: 'PIN-reset pricing saved successfully', type: 'success');
    }

    public function render(): View
    {
        return view('livewire.backoffice.settings.token-setting');
    }
}
