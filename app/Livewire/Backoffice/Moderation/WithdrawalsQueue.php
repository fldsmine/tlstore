<?php

namespace App\Livewire\Backoffice\Moderation;

use App\Application\Actions\Moderation\SettleWithdrawal;
use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\WithdrawalRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Withdrawals')]
class WithdrawalsQueue extends Component
{
    use AuthorizesAdminActions;
    use WithPagination;

    #[Url]
    public string $search = '';

    /** @var array<int, string> */
    public array $reasons = [];

    /** @var array<int, string> */
    public array $references = [];

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function markPaid(int $id): void
    {
        $admin = $this->authorizeAdmin();
        $withdrawal = WithdrawalRequest::query()->findOrFail($id);
        $reference = trim((string) ($this->references[$id] ?? '')) ?: null;

        try {
            app(SettleWithdrawal::class)->handle($admin, $withdrawal, 'paid', $reference, null);
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: collect($e->errors())->flatten()->first() ?? 'Could not record the decision.', type: 'error');

            return;
        }
        unset($this->references[$id]);
        $this->dispatch('toast', message: 'Withdrawal marked as paid.', type: 'success');
    }

    public function reject(int $id): void
    {
        $admin = $this->authorizeAdmin();
        $withdrawal = WithdrawalRequest::query()->findOrFail($id);
        $reason = trim((string) ($this->reasons[$id] ?? ''));
        if ($reason === '') {
            $this->addError('reasons.'.$id, 'A reason is required to reject.');

            return;
        }

        try {
            app(SettleWithdrawal::class)->handle($admin, $withdrawal, 'rejected', null, $reason);
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: collect($e->errors())->flatten()->first() ?? 'Could not record the decision.', type: 'error');

            return;
        }
        unset($this->reasons[$id]);
        $this->dispatch('toast', message: 'Withdrawal rejected; reserved funds released.', type: 'success');
    }

    public function render(): View
    {
        $this->authorizeAdmin();

        $search = trim($this->search);

        $query = WithdrawalRequest::query()
            ->with(['user:id,full_name,username,email', 'option:id,name,currency'])
            ->where('status', 'pending')
            ->when($search !== '', fn ($q) => $q->where(fn ($rows) => $rows
                ->whereHas('user', fn ($u) => $u->where('full_name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))
                ->orWhereHas('option', fn ($o) => $o->where('name', 'like', "%{$search}%"))
                ->orWhere('provider_reference', 'like', "%{$search}%")))
            ->oldest('requested_at');

        return view('livewire.backoffice.moderation.withdrawals-queue', [
            'withdrawals' => $query->paginate(10)->withQueryString(),
        ]);
    }
}
