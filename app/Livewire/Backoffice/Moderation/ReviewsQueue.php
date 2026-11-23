<?php

namespace App\Livewire\Backoffice\Moderation;

use App\Application\Actions\Moderation\DecideReviewSubmission;
use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use App\Models\ReviewSubmission;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backoffice')]
#[Title('Review queue')]
class ReviewsQueue extends Component
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
        $this->decide($id, 'approved');
    }

    public function reject(int $id): void
    {
        $admin = $this->authorizeAdmin();
        $submission = ReviewSubmission::query()->with('task.campaign.app', 'reviewer', 'answers.question', 'riskSignals')
            ->findOrFail($id);
        $reason = trim((string) ($this->reasons[$id] ?? ''));
        if ($reason === '') {
            $this->addError('reasons.'.$id, 'A reason is required to reject.');

            return;
        }
        try {
            app(DecideReviewSubmission::class)->handle($admin, $submission, 'rejected', 'backoffice', $reason);
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: collect($e->errors())->flatten()->first() ?? 'Could not record the decision.', type: 'error');

            return;
        }
        unset($this->reasons[$id]);
        $this->dispatch('toast', message: 'Review rejected.', type: 'success');
    }

    private function decide(int $id, string $decision): void
    {
        $admin = $this->authorizeAdmin();
        $submission = ReviewSubmission::query()->findOrFail($id);
        try {
            app(DecideReviewSubmission::class)->handle($admin, $submission, $decision, null, null);
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: collect($e->errors())->flatten()->first() ?? 'Could not record the decision.', type: 'error');

            return;
        }
        $this->dispatch('toast', message: 'Review approved.', type: 'success');
    }

    public function render(): View
    {
        $this->authorizeAdmin();

        $search = trim($this->search);

        $query = ReviewSubmission::query()
            ->with(['reviewer:id,full_name,username,email', 'task.campaign.app:id,name,short_code', 'answers.question', 'riskSignals'])
            ->where('status', 'pending_review')
            ->when($search !== '', fn ($q) => $q->where(fn ($rows) => $rows
                ->whereHas('reviewer', fn ($u) => $u->where('full_name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))
                ->orWhereHas('task.campaign.app', fn ($a) => $a->where('name', 'like', "%{$search}%"))))
            ->oldest('submitted_at');

        return view('livewire.backoffice.moderation.reviews-queue', [
            'submissions' => $query->paginate(10)->withQueryString(),
        ]);
    }
}
