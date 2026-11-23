<?php

namespace App\Livewire\Backoffice\Reports;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.backoffice')]
#[Title('Reports & exports')]
class ReportsIndex extends Component
{
    use AuthorizesAdminActions;

    #[Url(as: 'from')]
    public string $from = '';

    #[Url(as: 'to')]
    public string $to = '';

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function render(): View
    {
        $this->authorizeAdmin();

        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->from) === 1 ? $this->from : null;
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->to) === 1 ? $this->to : null;

        return view('livewire.backoffice.reports.reports-index', [
            'from' => $from,
            'to' => $to,
        ]);
    }
}
