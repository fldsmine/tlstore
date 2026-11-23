<?php

namespace App\Livewire\Backoffice\AuditLog;

use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\Admin;
use App\Models\AuditLog as AuditLogModel;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Audit log')]
class AuditLogIndex extends Component
{
    use AuthorizesAdminActions;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url(as: 'admin')]
    public string $admin = 'all';

    #[Url(as: 'entity')]
    public string $entity = '';

    #[Url(as: 'from')]
    public string $from = '';

    #[Url(as: 'to')]
    public string $to = '';

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorizeAdmin();

        $search = trim($this->search);
        $entity = trim($this->entity);

        $query = AuditLogModel::query()
            ->with('adminActor:id,full_name,email')
            ->when($search !== '', fn (Builder $q) => $q->where('action', 'like', "%{$search}%"))
            ->when($entity !== '', fn (Builder $q) => $q->where('entity_type', 'like', "%{$entity}%"))
            ->when($this->admin !== 'all' && ctype_digit($this->admin), fn (Builder $q) => $q->where('actor_admin_id', (int) $this->admin))
            ->when(preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->from) === 1, fn (Builder $q) => $q->where('created_at', '>=', $this->from.' 00:00:00'))
            ->when(preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->to) === 1, fn (Builder $q) => $q->where('created_at', '<=', $this->to.' 23:59:59'))
            ->latest('created_at');

        return view('livewire.backoffice.audit-log.audit-log-index', [
            'logs' => $query->paginate(20)->withQueryString(),
            'admins' => Admin::query()->orderBy('full_name')->get(['id', 'full_name']),
        ]);
    }
}
