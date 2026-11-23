<?php

namespace App\Livewire\Backoffice\Settings;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.backoffice')]
#[Title('Live Chat Settings — Backoffice')]
class ChatSetting extends Component
{
    use AuthorizesAdminActions;

    public bool $enabled = true;

    public string $historyRetention = '';

    public function mount(): void
    {
        $this->enabled = (bool) config('settings.chat.enabled');
        $this->historyRetention = (string) config('settings.chat.history_retention_days');
    }

    public function save(): void
    {
        $this->authorizeWrite();

        $this->validate([
            'historyRetention' => ['required', 'integer', 'min:1'],
        ], attributes: [
            'historyRetention' => 'history retention',
        ]);

        update_setting([
            'enable_chat' => $this->enabled,
            'chat_history_retention_days' => (int) $this->historyRetention,
        ]);

        $this->dispatch('toast', message: 'Live chat settings saved successfully', type: 'success');
    }

    public function render(): View
    {
        return view('livewire.backoffice.settings.chat-setting');
    }
}
