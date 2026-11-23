<?php

namespace App\Livewire\Backoffice\Moderation;

use App\Application\Actions\Moderation\DecideAdShare;
use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\AdShareEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Share queue')]
class SharesQueue extends Component
{
    use AuthorizesAdminActions;
    use WithPagination;

    #[Url]
    public string $search = '';

    /** @var array<int, string> */
    public array $reasons = [];

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function approve(int $id): void
    {
        $admin = $this->authorizeAdmin();
        $event = AdShareEvent::query()->findOrFail($id);
        try {
            app(DecideAdShare::class)->handle($admin, $event, 'approved', null, null);
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: collect($e->errors())->flatten()->first() ?? 'Could not record the decision.', type: 'error');

            return;
        }
        $this->dispatch('toast', message: 'Share approved.', type: 'success');
    }

    public function reject(int $id): void
    {
        $admin = $this->authorizeAdmin();
        $event = AdShareEvent::query()->findOrFail($id);
        $reason = trim((string) ($this->reasons[$id] ?? ''));
        if ($reason === '') {
            $this->addError('reasons.'.$id, 'A reason is required to reject.');

            return;
        }
        try {
            app(DecideAdShare::class)->handle($admin, $event, 'rejected', 'backoffice', $reason);
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: collect($e->errors())->flatten()->first() ?? 'Could not record the decision.', type: 'error');

            return;
        }
        unset($this->reasons[$id]);
        $this->dispatch('toast', message: 'Share rejected.', type: 'success');
    }

    public function render(): View
    {
        $this->authorizeAdmin();

        $search = trim($this->search);

        $query = AdShareEvent::query()
            ->with(['task.user:id,full_name,username,email', 'task.ad:id,title', 'riskSignals'])
            ->where('status', 'pending_validation')
            ->when($search !== '', fn ($q) => $q->where(fn ($rows) => $rows
                ->whereHas('task.user', fn ($u) => $u->where('full_name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))
                ->orWhereHas('task.ad', fn ($a) => $a->where('title', 'like', "%{$search}%"))
                ->orWhere('external_reference', 'like', "%{$search}%")))
            ->oldest('shared_at');

        return view('livewire.backoffice.moderation.shares-queue', [
            'events' => $query->paginate(10)->withQueryString(),
        ]);
    }
}
