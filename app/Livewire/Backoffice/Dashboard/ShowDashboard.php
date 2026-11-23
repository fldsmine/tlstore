<?php

namespace App\Livewire\Backoffice\Dashboard;

use App\Enums\AdShareEventStatus;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\ReviewSubmissionStatus;
use App\Enums\UserStatus;
use App\Enums\WithdrawalRequestStatus;
use App\Models\AdShareEvent;
use App\Models\AuditLog;
use App\Models\ManualDepositSubmission;
use App\Models\NotificationDelivery;
use App\Models\PaymentWebhookEvent;
use App\Models\ReviewSubmission;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use App\Livewire\Backoffice\Concerns\AuthorizesAdminActions;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.backoffice')]
#[Title('Dashboard')]
class ShowDashboard extends Component
{
    use AuthorizesAdminActions;

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function render(): View
    {
        $this->authorizeAdmin();

        $usersByStatus = User::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $usersTotal = (int) $usersByStatus->sum();
        $usersActive = (int) ($usersByStatus[UserStatus::Active->value] ?? 0);
        $usersBanned = (int) ($usersByStatus[UserStatus::Banned->value] ?? 0);
        $newUsersToday = (int) User::query()->whereDate('created_at', today())->count();

        $liabilityByCurrency = Wallet::query()
            ->selectRaw('currency, sum(balance_minor) as total_minor')
            ->groupBy('currency')
            ->orderByDesc('total_minor')
            ->get();

        $pendingWithdrawals = (int) WithdrawalRequest::query()
            ->where('status', WithdrawalRequestStatus::Pending->value)
            ->count();
        $pendingWithdrawalByCurrency = WithdrawalRequest::query()
            ->where('status', WithdrawalRequestStatus::Pending->value)
            ->selectRaw('currency, sum(amount_minor) as total_minor')
            ->groupBy('currency')
            ->pluck('total_minor', 'currency');

        $pendingReviews = (int) ReviewSubmission::query()
            ->where('status', ReviewSubmissionStatus::PendingReview->value)
            ->count();
        $pendingShares = (int) AdShareEvent::query()
            ->where('status', AdShareEventStatus::PendingValidation->value)
            ->count();
        $pendingDeposits = (int) ManualDepositSubmission::query()
            ->where('status', 'submitted')
            ->count();

        $failedDeliveriesToday = (int) NotificationDelivery::query()
            ->where('status', NotificationDeliveryStatus::Failed->value)
            ->whereDate('created_at', today())
            ->count();
        $webhookFailuresToday = (int) PaymentWebhookEvent::query()
            ->whereIn('processing_status', ['failed', 'rejected'])
            ->whereDate('received_at', today())
            ->count();

        return view('livewire.backoffice.dashboard.show-dashboard', [
            'usersTotal' => $usersTotal,
            'usersActive' => $usersActive,
            'usersBanned' => $usersBanned,
            'newUsersToday' => $newUsersToday,
            'liabilityByCurrency' => $liabilityByCurrency,
            'pendingWithdrawals' => $pendingWithdrawals,
            'pendingWithdrawalByCurrency' => $pendingWithdrawalByCurrency,
            'pendingReviews' => $pendingReviews,
            'pendingShares' => $pendingShares,
            'pendingDeposits' => $pendingDeposits,
            'failedDeliveriesToday' => $failedDeliveriesToday,
            'webhookFailuresToday' => $webhookFailuresToday,
            'recentUsers' => User::query()->latest('id')->limit(6)->get(['id', 'full_name', 'username', 'email', 'status', 'created_at']),
            'recentWithdrawals' => WithdrawalRequest::query()->with('user:id,username')->latest('id')->limit(6)
                ->get(['id', 'user_id', 'amount_minor', 'currency', 'status', 'requested_at']),
            'recentAuditLogs' => AuditLog::query()->with('adminActor:id,full_name')->latest('created_at')->limit(5)
                ->get(['id', 'actor_admin_id', 'action', 'entity_type', 'entity_id', 'created_at']),
        ]);
    }
}
