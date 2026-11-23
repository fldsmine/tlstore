<?php

namespace App\Livewire\Backoffice\Moderation;

use App\Application\Actions\Moderation\DecideManualDeposit;
use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\ManualDepositSubmission;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Manual deposits')]
class DepositsQueue extends Component
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
        $deposit = ManualDepositSubmission::query()->findOrFail($id);
        try {
            app(DecideManualDeposit::class)->handle($admin, $deposit, 'approved', null);
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: collect($e->errors())->flatten()->first() ?? 'Could not record the decision.', type: 'error');

            return;
        }
        $this->dispatch('toast', message: 'Deposit approved and funds credited.', type: 'success');
    }

    public function reject(int $id): void
    {
        $admin = $this->authorizeAdmin();
        $deposit = ManualDepositSubmission::query()->findOrFail($id);
        $reason = trim((string) ($this->reasons[$id] ?? ''));
        if ($reason === '') {
            $this->addError('reasons.'.$id, 'A reason is required to reject.');

            return;
        }
        try {
            app(DecideManualDeposit::class)->handle($admin, $deposit, 'rejected', $reason);
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: collect($e->errors())->flatten()->first() ?? 'Could not record the decision.', type: 'error');

            return;
        }
        unset($this->reasons[$id]);
        $this->dispatch('toast', message: 'Deposit rejected.', type: 'success');
    }

    public function render(): View
    {
        $this->authorizeAdmin();

        $search = trim($this->search);

        $query = ManualDepositSubmission::query()
            ->with(['user:id,full_name,username,email', 'paymentIntent:id,provider,provider_reference,amount_minor,currency'])
            ->where('status', 'submitted')
            ->when($search !== '', fn ($q) => $q->where(fn ($rows) => $rows
                ->whereHas('user', fn ($u) => $u->where('full_name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))
                ->orWhere('bank_reference', 'like', "%{$search}%")
                ->orWhereHas('paymentIntent', fn ($p) => $p->where('provider_reference', 'like', "%{$search}%"))))
            ->oldest('submitted_at');

        return view('livewire.backoffice.moderation.deposits-queue', [
            'deposits' => $query->paginate(10)->withQueryString(),
        ]);
    }
}
