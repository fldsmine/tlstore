<?php

namespace App\Livewire\Backoffice\Settings;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.backoffice')]
#[Title('Analytics Settings — Backoffice')]
class AnalyticsSetting extends Component
{
    use AuthorizesAdminActions;

    public string $headerCode = '';

    public string $footerCode = '';

    public function mount(): void
    {
        $this->headerCode = (string) config('settings.analytics.header_code');
        $this->footerCode = (string) config('settings.analytics.footer_code');
    }

    public function save(): void
    {
        $this->authorizeWrite();

        $this->validate([
            'headerCode' => ['nullable', 'string', 'max:20000'],
            'footerCode' => ['nullable', 'string', 'max:20000'],
        ]);

        update_setting([
            'analytics_header_code' => $this->headerCode,
            'analytics_footer_code' => $this->footerCode,
        ]);

        $this->dispatch('toast', message: 'Analytics code saved successfully', type: 'success');
    }

    public function render(): View
    {
        return view('livewire.backoffice.settings.analytics-setting');
    }
}
